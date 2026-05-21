<?php

declare(strict_types=1);

namespace Mercato\Payouts;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

final class Ledger
{
    public function __construct(
        private readonly Resolver $tenantResolver,
        private readonly Outbox $outbox,
    ) {
    }

    /**
     * @param array<string,mixed> $commission
     */
    public function recordCommission(array $commission): void
    {
        global $wpdb;

        $tenantId = (int) $commission['tenant_id'];
        $vendorId = (int) $commission['vendor_id'];
        $currency = (string) $commission['currency'];
        $amount = (int) $commission['vendor_net_minor'];
        $table = $wpdb->prefix . 'mercato_vendor_balances';

        $sql = $wpdb->prepare(
            "INSERT INTO `{$table}` (`tenant_id`, `vendor_id`, `currency`, `pending_minor`)
             VALUES (%d, %d, %s, %d)
             ON DUPLICATE KEY UPDATE `pending_minor` = `pending_minor` + VALUES(`pending_minor`)",
            $tenantId,
            $vendorId,
            $currency,
            $amount
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to update vendor balance: ' . (string) $wpdb->last_error);
        }
    }

    public function releasePending(?int $tenantId = null): int
    {
        global $wpdb;

        $tenantId ??= $this->tenantResolver->currentTenantId();
        $commissions = $wpdb->prefix . 'mercato_commissions';
        $balances = $wpdb->prefix . 'mercato_vendor_balances';
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `{$commissions}` WHERE `tenant_id` = %d AND `status` = 'pending' AND `available_at` <= UTC_TIMESTAMP(3)", $tenantId),
            ARRAY_A
        ) ?: [];

        foreach ($rows as $row) {
            $amount = (int) $row['vendor_net_minor'];
            $wpdb->query($wpdb->prepare(
                "UPDATE `{$balances}` SET `pending_minor` = GREATEST(0, `pending_minor` - %d), `available_minor` = `available_minor` + %d WHERE `tenant_id` = %d AND `vendor_id` = %d AND `currency` = %s",
                $amount,
                $amount,
                $tenantId,
                (int) $row['vendor_id'],
                (string) $row['currency']
            ));
            $wpdb->update($commissions, ['status' => 'available'], ['commission_id' => (int) $row['commission_id']]);
        }

        return \count($rows);
    }

    /**
     * @return array<string,mixed>
     */
    public function triggerBatch(?int $tenantId = null): array
    {
        global $wpdb;

        $tenantId ??= $this->tenantResolver->currentTenantId();
        $this->releasePending($tenantId);

        $balances = $wpdb->prefix . 'mercato_vendor_balances';
        $vendors = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `{$balances}` WHERE `tenant_id` = %d AND `available_minor` > 0", $tenantId),
            ARRAY_A
        ) ?: [];

        $total = \array_sum(\array_map(static fn (array $row): int => (int) $row['available_minor'], $vendors));
        $batches = $wpdb->prefix . 'mercato_payout_batches';
        $items = $wpdb->prefix . 'mercato_payout_items';

        $wpdb->insert($batches, [
            'tenant_id' => $tenantId,
            'status' => 'scheduled',
            'currency' => 'USD',
            'total_minor' => $total,
        ]);

        $batchId = (int) $wpdb->insert_id;

        foreach ($vendors as $vendor) {
            $amount = (int) $vendor['available_minor'];
            $wpdb->insert($items, [
                'batch_id' => $batchId,
                'tenant_id' => $tenantId,
                'vendor_id' => (int) $vendor['vendor_id'],
                'currency' => (string) $vendor['currency'],
                'amount_minor' => $amount,
                'status' => 'scheduled',
            ]);
        }

        $event = ['batch_id' => $batchId, 'tenant_id' => $tenantId, 'total_minor' => $total, 'item_count' => \count($vendors)];
        $this->outbox->publish('mercato.payout.scheduled.v1', $event, (string) $batchId, $tenantId);

        return $event;
    }
}
