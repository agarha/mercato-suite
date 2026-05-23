<?php

declare(strict_types=1);

namespace Mercato\Adapters\OrderToJob;

use Mercato\Core\Audit\Writer;
use Mercato\Core\Events\Outbox;

/**
 * Translates WC order events into Mercato job events.
 *
 * Stays read-only against WC: never updates an order, never changes
 * payment state, never extends WC_Payment_Gateway. Just observes and
 * emits Mercato events that downstream modules consume.
 */
final class OrderToJobAdapter
{
    public function __construct(
        private readonly Outbox $outbox,
        private readonly Writer $audit,
    ) {
    }

    public function onNewOrder(int $orderId): void
    {
        $context = $this->resolveJobContext($orderId);
        if ($context === null) {
            return; // Not a Mercato job-backed order; ignore.
        }

        $payload = [
            'wc_order_id' => $orderId,
            'job_id' => $context['job_id'],
            'tenant_id' => $context['tenant_id'],
            'provider_id' => $context['provider_id'],
        ];

        $this->outbox->publish('mercato.adapter.order_to_job.linked.v1', $payload, $context['tenant_id']);
        $this->audit->log('adapter.order_to_job.linked', 'job', $context['job_id'], null, $payload);
    }

    public function onPaymentComplete(int $orderId): void
    {
        $context = $this->resolveJobContext($orderId);
        if ($context === null) {
            return;
        }

        $payload = [
            'wc_order_id' => $orderId,
            'job_id' => $context['job_id'],
            'tenant_id' => $context['tenant_id'],
            'provider_id' => $context['provider_id'],
            'paid_at' => \current_time('mysql', true),
        ];

        $this->outbox->publish('mercato.adapter.order_to_job.paid.v1', $payload, $context['tenant_id']);
        $this->audit->log('adapter.order_to_job.paid', 'job', $context['job_id'], null, $payload);
    }

    /**
     * @param mixed $order  WC_Order|null passed by the hook
     */
    public function onStatusChanged(int $orderId, string $from, string $to, $order = null): void
    {
        $context = $this->resolveJobContext($orderId);
        if ($context === null) {
            return;
        }

        $payload = [
            'wc_order_id' => $orderId,
            'job_id' => $context['job_id'],
            'tenant_id' => $context['tenant_id'],
            'from' => $from,
            'to' => $to,
        ];

        $this->outbox->publish('mercato.adapter.order_to_job.status_synced.v1', $payload, $context['tenant_id']);
        $this->audit->log('adapter.order_to_job.status_synced', 'job', $context['job_id'], null, $payload);
    }

    /**
     * Reads job linkage off WC order meta. Returns null for orders that
     * are not backed by a Mercato job (e.g. legacy WooCommerce orders).
     *
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
}
