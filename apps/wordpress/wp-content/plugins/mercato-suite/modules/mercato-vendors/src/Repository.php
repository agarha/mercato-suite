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
            'suspension_reason' => $status === 'suspended' ? $reason : null,
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
