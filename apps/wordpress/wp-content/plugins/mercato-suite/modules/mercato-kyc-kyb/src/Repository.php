<?php

declare(strict_types=1);

namespace Mercato\KycKyb;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

final class Repository
{
    public function __construct(private readonly Resolver $tenantResolver, private readonly Outbox $outbox)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function start(int $vendorId): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_kyc_cases';
        $reference = 'vs_' . \strtolower(\bin2hex(\random_bytes(8)));
        $wpdb->replace($table, [
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'provider' => 'stripe_identity',
            'provider_reference' => $reference,
            'status' => 'processing',
        ]);
        $case = $this->findByVendor($vendorId);
        $this->outbox->publish('mercato.kyc.started.v1', $case, (string) $case['case_id'], $tenantId);
        return $case;
    }

    /**
     * @return array<string,mixed>
     */
    public function updateStatus(int $vendorId, string $status): array
    {
        global $wpdb;

        $allowed = ['required', 'processing', 'verified', 'rejected'];
        if (!\in_array($status, $allowed, true)) {
            throw new RuntimeException('Invalid KYC status.');
        }

        $table = $wpdb->prefix . 'mercato_kyc_cases';
        $wpdb->update($table, ['status' => $status], [
            'tenant_id' => $this->tenantResolver->currentTenantId(),
            'vendor_id' => $vendorId,
            'provider' => 'stripe_identity',
        ]);
        $case = $this->findByVendor($vendorId);
        $this->outbox->publish('mercato.kyc.' . $status . '.v1', $case, (string) $case['case_id']);
        return $case;
    }

    /**
     * @return array<string,mixed>
     */
    private function findByVendor(int $vendorId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_kyc_cases';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `vendor_id` = %d", $this->tenantResolver->currentTenantId(), $vendorId), ARRAY_A);
        if (!$row) {
            throw new RuntimeException('KYC case not found.');
        }
        return $row;
    }
}
