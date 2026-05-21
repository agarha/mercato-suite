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
        $reversals = $wpdb->prefix . 'mercato_commission_reversals';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*,
                    GREATEST(0, c.`vendor_net_minor` - COALESCE(SUM(r.`vendor_net_reversal_minor`), 0)) AS releasable_minor
                 FROM `{$commissions}` c
                 LEFT JOIN `{$reversals}` r
                   ON r.`tenant_id` = c.`tenant_id`
                  AND r.`commission_id` = c.`commission_id`
                 WHERE c.`tenant_id` = %d
                   AND c.`status` = 'pending'
                   AND c.`available_at` <= UTC_TIMESTAMP(3)
                 GROUP BY c.`commission_id`",
                $tenantId
            ),
            ARRAY_A
        ) ?: [];

        foreach ($rows as $row) {
            $amount = (int) $row['releasable_minor'];
            if ($amount < 1) {
                $wpdb->update($commissions, ['status' => 'reversed'], ['commission_id' => (int) $row['commission_id']]);
                continue;
            }

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

            $wpdb->update($balances, [
                'available_minor' => 0,
                'paid_minor' => (int) $vendor['paid_minor'] + $amount,
            ], [
                'tenant_id' => $tenantId,
                'vendor_id' => (int) $vendor['vendor_id'],
                'currency' => (string) $vendor['currency'],
            ]);
        }

        $event = ['batch_id' => $batchId, 'tenant_id' => $tenantId, 'total_minor' => $total, 'item_count' => \count($vendors)];
        $this->outbox->publish('mercato.payout.scheduled.v1', $event, (string) $batchId, $tenantId);

        return $event;
    }

    /**
     * @return array<string,mixed>
     */
    public function reconcile(?int $tenantId = null): array
    {
        global $wpdb;

        $tenantId ??= $this->tenantResolver->currentTenantId();
        $items = $wpdb->prefix . 'mercato_payout_items';
        $transfers = $wpdb->prefix . 'mercato_stripe_transfers';
        $runs = $wpdb->prefix . 'mercato_reconciliation_runs';

        $ledgerMinor = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount_minor), 0) FROM `{$items}` WHERE tenant_id = %d AND status = 'succeeded'",
            $tenantId
        ));
        $providerMinor = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount_minor), 0) FROM `{$transfers}` WHERE tenant_id = %d AND status = 'succeeded'",
            $tenantId
        ));
        $driftMinor = $ledgerMinor - $providerMinor;
        $status = \abs($driftMinor) <= 1 ? 'passed' : 'failed';
        $upload = \function_exists('wp_upload_bits')
            ? \wp_upload_bits('mercato-reconciliation-' . $tenantId . '-' . \gmdate('Ymd-His') . '.csv', null, $this->reconciliationCsv($tenantId, $ledgerMinor, $providerMinor, $driftMinor, $status))
            : ['url' => null, 'error' => null];

        $wpdb->insert($runs, [
            'tenant_id' => $tenantId,
            'status' => $status,
            'ledger_minor' => $ledgerMinor,
            'provider_minor' => $providerMinor,
            'drift_minor' => $driftMinor,
            'report_url' => empty($upload['error']) ? ($upload['url'] ?? null) : null,
        ]);

        $run = [
            'run_id' => (int) $wpdb->insert_id,
            'tenant_id' => $tenantId,
            'status' => $status,
            'ledger_minor' => $ledgerMinor,
            'provider_minor' => $providerMinor,
            'drift_minor' => $driftMinor,
            'report_url' => empty($upload['error']) ? ($upload['url'] ?? null) : null,
        ];
        $this->outbox->publish('mercato.payout.reconciled.v1', $run, (string) $run['run_id'], $tenantId);

        return $run;
    }

    private function reconciliationCsv(int $tenantId, int $ledgerMinor, int $providerMinor, int $driftMinor, string $status): string
    {
        return "tenant_id,status,ledger_minor,provider_minor,drift_minor,generated_at\n"
            . $tenantId . ',' . $status . ',' . $ledgerMinor . ',' . $providerMinor . ',' . $driftMinor . ',' . \gmdate('c') . "\n";
    }
}
