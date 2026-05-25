<?php

declare(strict_types=1);

namespace Mercato\Vendors;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

final class Repository
{
    public function __construct(
        private readonly Resolver $tenantResolver,
        private readonly Outbox $outbox,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function register(array $data, int $ownerUserId): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $businessName = $this->cleanText((string) ($data['business_name'] ?? ''));
        $storeSlug = $this->slug((string) ($data['store_slug'] ?? $businessName));

        if ($businessName === '' || $storeSlug === '') {
            throw new RuntimeException('business_name and store_slug are required.');
        }

        // Profile fields populated during multi-step onboarding. All optional
        // at insert time so legacy callers (admin-only register) still work.
        $row = [
            'tenant_id' => $tenantId,
            'owner_user_id' => $ownerUserId,
            'business_name' => $businessName,
            'store_slug' => $storeSlug,
            'return_policy' => isset($data['return_policy']) ? $this->cleanText((string) $data['return_policy']) : null,
            'status' => 'pending',
        ];
        foreach ([
            'headline', 'bio', 'phone', 'contact_email', 'photo_url', 'cover_url',
            'languages', 'license_number', 'license_state', 'insurance_carrier',
        ] as $textKey) {
            if (isset($data[$textKey]) && $data[$textKey] !== '') {
                $row[$textKey] = $this->cleanText((string) $data[$textKey]);
            }
        }
        if (isset($data['years_experience'])) {
            $row['years_experience'] = (int) $data['years_experience'];
        }
        if (isset($data['hourly_rate_minor'])) {
            $row['hourly_rate_minor'] = (int) $data['hourly_rate_minor'];
        }
        if (isset($data['insurance_amount_minor'])) {
            $row['insurance_amount_minor'] = (int) $data['insurance_amount_minor'];
        }
        if (isset($data['currency']) && \is_string($data['currency'])) {
            $row['currency'] = \strtoupper(\substr($data['currency'], 0, 3));
        }

        $table = $wpdb->prefix . 'mercato_vendors';
        $inserted = $wpdb->insert($table, $row);

        if ($inserted === false) {
            throw new RuntimeException('Unable to register vendor: ' . (string) $wpdb->last_error);
        }

        $vendorId = (int) $wpdb->insert_id;

        // Attach the owner to vendor_staff so downstream permission checks
        // (Stripe Connect linking, payouts) treat them as the owner.
        $staff = $wpdb->prefix . 'mercato_vendor_staff';
        $wpdb->insert($staff, [
            'vendor_id' => $vendorId,
            'user_id' => $ownerUserId,
            'role' => 'owner',
        ]);

        $vendor = $this->find($vendorId);
        $this->outbox->publish('mercato.vendor.registered.v1', $vendor, (string) $vendor['vendor_id'], $tenantId);

        return $vendor;
    }

    /**
     * Update profile-only fields on a vendor. Used after onboarding to fill
     * in bio, hourly rate, license/insurance details. Status workflow lives
     * in setStatus() and is intentionally not touched here.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function updateProfile(int $vendorId, array $data): array
    {
        global $wpdb;
        $tenantId = $this->tenantResolver->currentTenantId();

        $update = [];
        foreach ([
            'business_name', 'headline', 'bio', 'phone', 'contact_email',
            'photo_url', 'cover_url', 'languages', 'license_number',
            'license_state', 'insurance_carrier', 'return_policy',
        ] as $textKey) {
            if (\array_key_exists($textKey, $data)) {
                $update[$textKey] = $data[$textKey] === null ? null : $this->cleanText((string) $data[$textKey]);
            }
        }
        if (\array_key_exists('years_experience', $data)) {
            $update['years_experience'] = $data['years_experience'] === null ? null : (int) $data['years_experience'];
        }
        if (\array_key_exists('hourly_rate_minor', $data)) {
            $update['hourly_rate_minor'] = $data['hourly_rate_minor'] === null ? null : (int) $data['hourly_rate_minor'];
        }
        if (\array_key_exists('insurance_amount_minor', $data)) {
            $update['insurance_amount_minor'] = $data['insurance_amount_minor'] === null ? null : (int) $data['insurance_amount_minor'];
        }
        if ($update === []) {
            return $this->find($vendorId);
        }

        $table = $wpdb->prefix . 'mercato_vendors';
        $updated = $wpdb->update($table, $update, [
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
        ]);
        if ($updated === false) {
            throw new RuntimeException('Unable to update vendor profile: ' . (string) $wpdb->last_error);
        }

        $vendor = $this->find($vendorId);
        $this->outbox->publish('mercato.vendor.profile.updated.v1', $vendor, (string) $vendorId, $tenantId);
        return $vendor;
    }

    /**
     * Create a primary or secondary physical location for a vendor.
     * service_radius_km is optional — when present, this location alone
     * is enough to satisfy proximity discovery.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function createLocation(int $vendorId, array $data): array
    {
        global $wpdb;
        $tenantId = $this->tenantResolver->currentTenantId();
        // Ensure caller can address this vendor under the current tenant.
        $this->find($vendorId);

        if (!isset($data['latitude'], $data['longitude'])) {
            throw new RuntimeException('latitude and longitude are required.');
        }

        $row = [
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'label' => isset($data['label']) ? $this->cleanText((string) $data['label']) : null,
            'address_line1' => isset($data['address_line1']) ? $this->cleanText((string) $data['address_line1']) : null,
            'city' => isset($data['city']) ? $this->cleanText((string) $data['city']) : null,
            'region' => isset($data['region']) ? $this->cleanText((string) $data['region']) : null,
            'postal_code' => isset($data['postal_code']) ? $this->cleanText((string) $data['postal_code']) : null,
            'country' => isset($data['country']) ? \strtoupper(\substr((string) $data['country'], 0, 2)) : null,
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'service_radius_km' => isset($data['service_radius_km']) ? (float) $data['service_radius_km'] : null,
            'is_primary' => !empty($data['is_primary']) ? 1 : 0,
        ];

        $table = $wpdb->prefix . 'mercato_vendor_locations';
        if ($row['is_primary'] === 1) {
            $wpdb->update($table, ['is_primary' => 0], ['tenant_id' => $tenantId, 'vendor_id' => $vendorId]);
        }
        $inserted = $wpdb->insert($table, $row);
        if ($inserted === false) {
            throw new RuntimeException('Unable to create vendor location: ' . (string) $wpdb->last_error);
        }

        return ['location_id' => (int) $wpdb->insert_id] + $row;
    }

    /**
     * Declare a service area (city/region/postal-code or geo radius) that the
     * vendor covers. Multiple per vendor. Optionally scoped to a specific
     * offering via product_id.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function createServiceArea(int $vendorId, array $data): array
    {
        global $wpdb;
        $tenantId = $this->tenantResolver->currentTenantId();
        $this->find($vendorId);

        $label = $this->cleanText((string) ($data['label'] ?? ''));
        if ($label === '') {
            throw new RuntimeException('Service area label is required.');
        }

        $row = [
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'product_id' => isset($data['product_id']) ? (int) $data['product_id'] : null,
            'label' => $label,
            'city' => isset($data['city']) ? $this->cleanText((string) $data['city']) : null,
            'region' => isset($data['region']) ? $this->cleanText((string) $data['region']) : null,
            'postal_code_prefix' => isset($data['postal_code_prefix']) ? $this->cleanText((string) $data['postal_code_prefix']) : null,
            'country' => isset($data['country']) ? \strtoupper(\substr((string) $data['country'], 0, 2)) : null,
            'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
            'radius_km' => isset($data['radius_km']) ? (float) $data['radius_km'] : null,
        ];

        $table = $wpdb->prefix . 'mercato_service_areas';
        $inserted = $wpdb->insert($table, $row);
        if ($inserted === false) {
            throw new RuntimeException('Unable to create service area: ' . (string) $wpdb->last_error);
        }

        return ['area_id' => (int) $wpdb->insert_id] + $row;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function locations(int $vendorId): array
    {
        global $wpdb;
        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_vendor_locations';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT location_id, label, address_line1, city, region, postal_code, country, latitude, longitude, service_radius_km, is_primary FROM `{$table}` WHERE tenant_id = %d AND vendor_id = %d ORDER BY is_primary DESC, location_id ASC",
            $tenantId,
            $vendorId
        ), ARRAY_A) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function serviceAreas(int $vendorId): array
    {
        global $wpdb;
        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_service_areas';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT area_id, product_id, label, city, region, postal_code_prefix, country, latitude, longitude, radius_km FROM `{$table}` WHERE tenant_id = %d AND vendor_id = %d ORDER BY area_id ASC",
            $tenantId,
            $vendorId
        ), ARRAY_A) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function list(?string $status = null): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_vendors';
        $tenantId = $this->tenantResolver->currentTenantId();

        if ($status !== null && $status !== '') {
            return $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `status` = %s ORDER BY `created_at` DESC", $tenantId, $status),
                ARRAY_A
            ) ?: [];
        }

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `{$table}` WHERE `tenant_id` = %d ORDER BY `created_at` DESC", $tenantId),
            ARRAY_A
        ) ?: [];
    }

    /**
     * @return array<string,mixed>
     */
    public function setStatus(int $vendorId, string $status, ?string $reason = null): array
    {
        global $wpdb;

        $allowed = ['pending', 'kyc_required', 'approved', 'rejected', 'suspended', 'closed'];
        if (!\in_array($status, $allowed, true)) {
            throw new RuntimeException('Invalid vendor status.');
        }

        $before = $this->find($vendorId);
        $table = $wpdb->prefix . 'mercato_vendors';
        $updated = $wpdb->update($table, [
            'status' => $status,
            'status_reason' => \in_array($status, ['rejected', 'suspended'], true) ? $this->cleanText((string) $reason) : null,
            'suspension_reason' => $status === 'suspended' ? $this->cleanText((string) $reason) : null,
        ], [
            'tenant_id' => $this->tenantResolver->currentTenantId(),
            'vendor_id' => $vendorId,
        ]);

        if ($updated === false) {
            throw new RuntimeException('Unable to update vendor: ' . (string) $wpdb->last_error);
        }

        $after = $this->find($vendorId);
        $this->outbox->publish('mercato.vendor.' . $status . '.v1', [
            'before' => $before,
            'after' => $after,
        ], (string) $vendorId);

        return $after;
    }

    /**
     * @return array<string,mixed>
     */
    public function onboardingChecklist(int $vendorId): array
    {
        global $wpdb;

        $vendor = $this->find($vendorId);
        $tenantId = $this->tenantResolver->currentTenantId();
        $kyc = $wpdb->prefix . 'mercato_kyc_cases';
        $stripe = $wpdb->prefix . 'mercato_stripe_accounts';
        $products = $wpdb->prefix . 'mercato_products';

        $kycStatus = (string) ($wpdb->get_var($wpdb->prepare(
            "SELECT `status` FROM `{$kyc}` WHERE `tenant_id` = %d AND `vendor_id` = %d ORDER BY `case_id` DESC LIMIT 1",
            $tenantId,
            $vendorId
        )) ?: '');
        $stripeCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$stripe}` WHERE `tenant_id` = %d AND `vendor_id` = %d",
            $tenantId,
            $vendorId
        ));
        $productCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$products}` WHERE `tenant_id` = %d AND `vendor_id` = %d",
            $tenantId,
            $vendorId
        ));

        $steps = [
            ['key' => 'profile', 'label' => 'Profile submitted', 'complete' => (string) $vendor['business_name'] !== '' && (string) $vendor['store_slug'] !== ''],
            ['key' => 'stripe', 'label' => 'Stripe account connected', 'complete' => $stripeCount > 0],
            ['key' => 'kyc', 'label' => 'KYC verified', 'complete' => $kycStatus === 'verified'],
            ['key' => 'approval', 'label' => 'Tenant approved', 'complete' => (string) $vendor['status'] === 'approved'],
            ['key' => 'first_product', 'label' => 'First product created', 'complete' => $productCount > 0],
        ];

        $completed = \count(\array_filter($steps, static fn (array $step): bool => (bool) $step['complete']));

        return [
            'vendor_id' => $vendorId,
            'status' => (string) $vendor['status'],
            'completed' => $completed,
            'total' => \count($steps),
            'percent' => (int) \round($completed * 100 / \count($steps)),
            'steps' => $steps,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    /**
     * Generate or reissue an email verification token for a vendor.
     * Returns the raw token so the caller can include it in the verify URL.
     */
    public function issueVerificationToken(int $vendorId): string
    {
        global $wpdb;
        $tenantId = $this->tenantResolver->currentTenantId();
        $token = \bin2hex(\random_bytes(32));
        $table = $wpdb->prefix . 'mercato_vendors';
        $updated = $wpdb->update($table, [
            'email_verification_token' => $token,
            'email_verification_sent_at' => \gmdate('Y-m-d H:i:s.v'),
        ], [
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
        ]);
        if ($updated === false) {
            throw new RuntimeException('Unable to issue verification token: ' . (string) $wpdb->last_error);
        }
        return $token;
    }

    /**
     * Confirm a vendor's email by token. Returns the matching vendor on
     * success; throws "TOKEN_INVALID" if no row matches. Idempotent: a
     * second call with the same token still succeeds (already verified).
     *
     * @return array<string,mixed>
     */
    public function verifyEmailToken(string $token): array
    {
        global $wpdb;
        $tenantId = $this->tenantResolver->currentTenantId();
        $token = \preg_replace('/[^a-f0-9]/i', '', $token) ?? '';
        if (\strlen($token) < 32) {
            throw new RuntimeException('TOKEN_INVALID');
        }

        $table = $wpdb->prefix . 'mercato_vendors';
        $vendor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE tenant_id = %d AND email_verification_token = %s",
            $tenantId,
            $token
        ), ARRAY_A);
        if (!\is_array($vendor)) {
            throw new RuntimeException('TOKEN_INVALID');
        }

        if (empty($vendor['email_verified_at'])) {
            $wpdb->update($table, [
                'email_verified_at' => \gmdate('Y-m-d H:i:s.v'),
            ], [
                'tenant_id' => $tenantId,
                'vendor_id' => (int) $vendor['vendor_id'],
            ]);
            $vendor = $this->find((int) $vendor['vendor_id']);
            $this->outbox->publish('mercato.vendor.email.verified.v1', $vendor, (string) $vendor['vendor_id'], $tenantId);
        }
        return $vendor;
    }

    public function find(int $vendorId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_vendors';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `vendor_id` = %d", $this->tenantResolver->currentTenantId(), $vendorId),
            ARRAY_A
        );

        if (!$row) {
            throw new RuntimeException('Vendor not found.');
        }

        return $row;
    }

    private function cleanText(string $value): string
    {
        return \function_exists('sanitize_text_field') ? \sanitize_text_field($value) : \trim($value);
    }

    private function slug(string $value): string
    {
        if (\function_exists('sanitize_title')) {
            return \sanitize_title($value);
        }

        return \strtolower((string) \preg_replace('/[^a-z0-9]+/i', '-', \trim($value)));
    }
}
