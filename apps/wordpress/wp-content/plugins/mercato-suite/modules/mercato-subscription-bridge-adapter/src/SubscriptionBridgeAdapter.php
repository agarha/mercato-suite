<?php

declare(strict_types=1);

namespace Mercato\Adapters\SubscriptionBridge;

use Mercato\Core\Audit\Writer;
use Mercato\Core\Events\Outbox;

/**
 * Bridges WC Subscriptions events to Mercato recurring-job events.
 *
 * Mercato-side schedulers/dispatchers subscribe to .renewed.v1 to spin
 * up the next service job in the recurrence. .cancelled.v1 stops
 * future occurrences. Cadence + dunning stay with WC Subscriptions.
 */
final class SubscriptionBridgeAdapter
{
    public function __construct(
        private readonly Outbox $outbox,
        private readonly Writer $audit,
    ) {
    }

    /**
     * @param mixed  $subscription WC_Subscription
     * @param string $newStatus
     * @param string $oldStatus
     */
    public function onStatusUpdated($subscription, string $newStatus, string $oldStatus): void
    {
        $context = $this->resolveContext($subscription);
        if ($context === null) {
            return;
        }

        $payload = [
            'subscription_id' => $context['subscription_id'],
            'tenant_id' => $context['tenant_id'],
            'provider_id' => $context['provider_id'],
            'recurring_service_id' => $context['recurring_service_id'],
            'from' => $oldStatus,
            'to' => $newStatus,
        ];

        $this->outbox->publish(
            'mercato.adapter.subscription_bridge.status_changed.v1',
            $payload,
            $context['tenant_id']
        );
        $this->audit->log('adapter.subscription_bridge.status_changed', 'subscription', $context['subscription_id'], null, $payload);

        if (\in_array($newStatus, ['cancelled', 'expired'], true)) {
            $this->outbox->publish(
                'mercato.adapter.subscription_bridge.cancelled.v1',
                $payload,
                $context['tenant_id']
            );
        }
    }

    /**
     * @param mixed $subscription WC_Subscription
     * @param mixed $lastOrder    WC_Order
     */
    public function onRenewalPaymentComplete($subscription, $lastOrder = null): void
    {
        $context = $this->resolveContext($subscription);
        if ($context === null) {
            return;
        }

        $payload = [
            'subscription_id' => $context['subscription_id'],
            'tenant_id' => $context['tenant_id'],
            'provider_id' => $context['provider_id'],
            'recurring_service_id' => $context['recurring_service_id'],
            'wc_order_id' => \is_object($lastOrder) && \method_exists($lastOrder, 'get_id')
                ? (int) $lastOrder->get_id()
                : 0,
        ];

        $this->outbox->publish(
            'mercato.adapter.subscription_bridge.renewed.v1',
            $payload,
            $context['tenant_id']
        );
        $this->audit->log('adapter.subscription_bridge.renewed', 'subscription', $context['subscription_id'], null, $payload);
    }

    /**
     * @param mixed $subscription
     *
     * @return array{subscription_id:int,tenant_id:int,provider_id:int,recurring_service_id:int}|null
     */
    private function resolveContext($subscription): ?array
    {
        if (!\is_object($subscription) || !\method_exists($subscription, 'get_id') || !\method_exists($subscription, 'get_meta')) {
            return null;
        }

        $tenantId = (int) $subscription->get_meta('_mercato_tenant_id');
        $recurringServiceId = (int) $subscription->get_meta('_mercato_recurring_service_id');
        if ($tenantId <= 0 || $recurringServiceId <= 0) {
            return null; // Not a Mercato-managed subscription; ignore.
        }

        return [
            'subscription_id' => (int) $subscription->get_id(),
            'tenant_id' => $tenantId,
            'provider_id' => (int) $subscription->get_meta('_mercato_provider_id'),
            'recurring_service_id' => $recurringServiceId,
        ];
    }
}
