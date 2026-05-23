<?php

declare(strict_types=1);

namespace Mercato\Adapters\RefundToJob;

use Mercato\Core\Audit\Writer;
use Mercato\Core\Events\Outbox;

/**
 * Bridges WC refund events into Mercato events.
 *
 * mercato-commissions subscribes to .reversed.v1 to write the reversal
 * journal entry. mercato-payouts subscribes to .reversed.v1 to adjust
 * the open payout balance. mercato-notifications subscribes to fire
 * the buyer/provider refund notice.
 */
final class RefundToJobAdapter
{
    public function __construct(
        private readonly Outbox $outbox,
        private readonly Writer $audit,
    ) {
    }

    /**
     * @param int            $refundId WC refund post ID
     * @param array<string,mixed> $args     refund args (parent order id, amount, reason, ...)
     */
    public function onRefundCreated(int $refundId, array $args): void
    {
        $orderId = (int) ($args['order_id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $context = $this->resolveJobContext($orderId);
        if ($context === null) {
            return;
        }

        $amountCents = $this->normaliseAmount($args['amount'] ?? 0);

        $payload = [
            'wc_order_id' => $orderId,
            'wc_refund_id' => $refundId,
            'job_id' => $context['job_id'],
            'tenant_id' => $context['tenant_id'],
            'provider_id' => $context['provider_id'],
            'amount_cents' => $amountCents,
            'reason' => (string) ($args['reason'] ?? ''),
        ];

        $this->outbox->publish('mercato.adapter.refund_to_job.reversed.v1', $payload, $context['tenant_id']);
        $this->audit->log('adapter.refund_to_job.reversed', 'job', $context['job_id'], null, $payload);
    }

    public function onOrderRefunded(int $orderId, int $refundId): void
    {
        $context = $this->resolveJobContext($orderId);
        if ($context === null) {
            return;
        }

        $payload = [
            'wc_order_id' => $orderId,
            'wc_refund_id' => $refundId,
            'job_id' => $context['job_id'],
            'tenant_id' => $context['tenant_id'],
        ];

        $this->outbox->publish('mercato.adapter.refund_to_job.post_refund.v1', $payload, $context['tenant_id']);
        $this->audit->log('adapter.refund_to_job.post_refund', 'job', $context['job_id'], null, $payload);
    }

    /**
     * @return array{job_id:int,tenant_id:int,provider_id:int}|null
     */
    private function resolveJobContext(int $orderId): ?array
    {
        if (!\function_exists('wc_get_order')) {
            return null;
        }

        $order = \wc_get_order($orderId);
        if (!$order) {
            return null;
        }

        $jobId = (int) $order->get_meta('_mercato_job_id');
        if ($jobId <= 0) {
            return null;
        }

        return [
            'job_id' => $jobId,
            'tenant_id' => (int) $order->get_meta('_mercato_tenant_id'),
            'provider_id' => (int) $order->get_meta('_mercato_provider_id'),
        ];
    }

    private function normaliseAmount(mixed $amount): int
    {
        if (\is_int($amount)) {
            return $amount * 100;
        }
        if (\is_numeric($amount)) {
            return (int) \round(((float) $amount) * 100);
        }
        return 0;
    }
}
