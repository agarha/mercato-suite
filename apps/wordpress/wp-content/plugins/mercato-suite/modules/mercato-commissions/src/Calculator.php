<?php

declare(strict_types=1);

namespace Mercato\Commissions;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

final class Calculator
{
    private const DEFAULT_RATE_BPS = 1000;
    private const DEFAULT_HOLD_DAYS = 7;

    public function __construct(
        private readonly Resolver $tenantResolver,
        private readonly Outbox $outbox,
    ) {
    }

    /**
     * @param array<string,mixed> $suborder
     * @return array<string,mixed>
     */
    public function recordForSuborder(array $suborder): array
    {
        global $wpdb;

        $tenantId = (int) ($suborder['tenant_id'] ?? $this->tenantResolver->currentTenantId());
        $vendorId = (int) $suborder['vendor_id'];
        $suborderId = (int) $suborder['suborder_id'];
        $grossMinor = (int) $suborder['total_minor'];
        $currency = (string) ($suborder['currency'] ?? 'USD');
        $rateBps = $this->rateFor($tenantId, $vendorId);
        $commissionMinor = (int) \round($grossMinor * $rateBps / 10000);
        $vendorNetMinor = $grossMinor - $commissionMinor;
        $availableAt = \gmdate('Y-m-d H:i:s.v', \time() + (self::DEFAULT_HOLD_DAYS * 86400));

        $table = $wpdb->prefix . 'mercato_commissions';
        $result = $wpdb->replace($table, [
            'tenant_id' => $tenantId,
            'suborder_id' => $suborderId,
            'vendor_id' => $vendorId,
            'currency' => $currency,
            'gross_minor' => $grossMinor,
            'commission_minor' => $commissionMinor,
            'vendor_net_minor' => $vendorNetMinor,
            'rate_bps' => $rateBps,
            'status' => 'pending',
            'available_at' => $availableAt,
        ]);

        if ($result === false) {
            throw new RuntimeException('Unable to record commission: ' . (string) $wpdb->last_error);
        }

        $commission = $this->findBySuborder($tenantId, $suborderId);
        $this->outbox->publish('mercato.commission.recorded.v1', $commission, (string) $commission['commission_id'], $tenantId);

        if (\function_exists('do_action')) {
            \do_action('mercato_commission_recorded', $commission);
        }

        return $commission;
    }

    public function makeAvailable(int $tenantId, int $commissionId): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_commissions';
        $wpdb->update($table, ['status' => 'available'], ['tenant_id' => $tenantId, 'commission_id' => $commissionId]);
    }

    /**
     * @param array<string,mixed> $refund
     * @return array<string,mixed>
     */
    public function reverseForRefund(array $refund): array
    {
        global $wpdb;

        $tenantId = (int) ($refund['tenant_id'] ?? $this->tenantResolver->currentTenantId());
        $suborderId = (int) $refund['suborder_id'];
        $refundId = (int) $refund['refund_id'];
        $amount = (int) $refund['amount_minor'];
        $commission = $this->findBySuborder($tenantId, $suborderId);
        $grossMinor = (int) $commission['gross_minor'];
        if ($grossMinor < 1) {
            throw new RuntimeException('Commission has no refundable gross amount.');
        }

        $commissionReversal = (int) \round(((int) $commission['commission_minor']) * $amount / $grossMinor);
        $vendorNetReversal = \max(0, $amount - $commissionReversal);
        $reversals = $wpdb->prefix . 'mercato_commission_reversals';
        $wpdb->replace($reversals, [
            'tenant_id' => $tenantId,
            'commission_id' => (int) $commission['commission_id'],
            'refund_id' => $refundId,
            'suborder_id' => $suborderId,
            'vendor_id' => (int) $commission['vendor_id'],
            'currency' => (string) $commission['currency'],
            'gross_reversal_minor' => $amount,
            'commission_reversal_minor' => $commissionReversal,
            'vendor_net_reversal_minor' => $vendorNetReversal,
        ]);

        if ($wpdb->insert_id < 1 && $wpdb->last_error !== '') {
            throw new RuntimeException('Unable to record commission reversal: ' . (string) $wpdb->last_error);
        }

        $totalReversed = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(`gross_reversal_minor`), 0) FROM `{$reversals}` WHERE `tenant_id` = %d AND `commission_id` = %d",
            $tenantId,
            (int) $commission['commission_id']
        ));

        if ($totalReversed >= $grossMinor) {
            $wpdb->update($wpdb->prefix . 'mercato_commissions', ['status' => 'reversed'], [
                'tenant_id' => $tenantId,
                'commission_id' => (int) $commission['commission_id'],
            ]);
        }

        $this->reduceVendorBalance($tenantId, (int) $commission['vendor_id'], (string) $commission['currency'], $vendorNetReversal);
        $reversal = $this->findReversal($tenantId, $refundId, (int) $commission['commission_id']);
        $this->outbox->publish('mercato.commission.reversed.v1', $reversal, (string) $reversal['reversal_id'], $tenantId);

        return $reversal;
    }

    /**
     * @return array<string,mixed>
     */
    private function findBySuborder(int $tenantId, int $suborderId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_commissions';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `suborder_id` = %d", $tenantId, $suborderId),
            ARRAY_A
        );

        if (!$row) {
            throw new RuntimeException('Commission not found.');
        }

        return $row;
    }

    private function reduceVendorBalance(int $tenantId, int $vendorId, string $currency, int $amount): void
    {
        global $wpdb;

        if ($amount < 1) {
            return;
        }

        $table = $wpdb->prefix . 'mercato_vendor_balances';
        $balance = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `vendor_id` = %d AND `currency` = %s",
            $tenantId,
            $vendorId,
            $currency
        ), ARRAY_A);

        if (!\is_array($balance)) {
            return;
        }

        $pending = (int) $balance['pending_minor'];
        $available = (int) $balance['available_minor'];
        $pendingReduction = \min($pending, $amount);
        $availableReduction = \min($available, $amount - $pendingReduction);
        $wpdb->update($table, [
            'pending_minor' => $pending - $pendingReduction,
            'available_minor' => $available - $availableReduction,
        ], [
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'currency' => $currency,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function findReversal(int $tenantId, int $refundId, int $commissionId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_commission_reversals';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `refund_id` = %d AND `commission_id` = %d",
            $tenantId,
            $refundId,
            $commissionId
        ), ARRAY_A);

        if (!\is_array($row)) {
            throw new RuntimeException('Commission reversal not found.');
        }

        return $row;
    }

    private function rateFor(int $tenantId, int $vendorId): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_commission_rules';
        $rate = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT `rate_bps` FROM `{$table}` WHERE `tenant_id` = %d AND (`vendor_id` = %d OR `vendor_id` IS NULL) AND `active` = 1 ORDER BY `vendor_id` DESC, `priority` ASC LIMIT 1",
                $tenantId,
                $vendorId
            )
        );

        return $rate === null ? self::DEFAULT_RATE_BPS : (int) $rate;
    }
}
