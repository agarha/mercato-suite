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

        $table = $wpdb->prefix . 'mercato_vendors';
        $inserted = $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'owner_user_id' => $ownerUserId,
            'business_name' => $businessName,
            'store_slug' => $storeSlug,
            'return_policy' => isset($data['return_policy']) ? $this->cleanText((string) $data['return_policy']) : null,
            'status' => 'pending',
        ]);

        if ($inserted === false) {
            throw new RuntimeException('Unable to register vendor: ' . (string) $wpdb->last_error);
        }

        $vendor = $this->find((int) $wpdb->insert_id);
        $this->outbox->publish('mercato.vendor.registered.v1', $vendor, (string) $vendor['vendor_id'], $tenantId);

        return $vendor;
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
