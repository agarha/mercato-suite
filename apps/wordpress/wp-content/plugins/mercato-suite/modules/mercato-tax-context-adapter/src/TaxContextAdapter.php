<?php

declare(strict_types=1);

namespace Mercato\Adapters\TaxContext;

use Mercato\Core\Audit\Writer;
use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;

/**
 * Bridges service-delivery location into WC tax flow.
 *
 * For physical goods, WC infers jurisdiction from the shipping address.
 * For services, the "service location" is where the job is performed —
 * not where the buyer lives. This adapter:
 *
 *   1. Reads the service-location hint set by mercato-orders / mercato-bids
 *      into a request-scoped global before checkout starts.
 *   2. Stamps that location onto the WC order line items via
 *      _mercato_service_location_* meta during checkout creation, so the
 *      WC/TaxJar/Avalara tax plugin can pick it up via its own
 *      meta-aware lookup.
 *
 * It does NOT compute tax. It does NOT replace the WC tax engine.
 */
final class TaxContextAdapter
{
    public function __construct(
        private readonly Resolver $tenants,
        private readonly Outbox $outbox,
        private readonly Writer $audit,
    ) {
    }

    /**
     * @param mixed $cart WC_Cart (kept loose to avoid hard WC type dep in unit tests)
     */
    public function onCartCalculateFees($cart = null): void
    {
        $location = $this->currentServiceLocation();
        if ($location === null) {
            return;
        }

        $payload = [
            'tenant_id' => $this->tenants->currentTenantId(),
            'service_country' => $location['country'],
            'service_postcode' => $location['postcode'],
            'service_state' => $location['state'],
            'service_city' => $location['city'],
        ];

        $this->outbox->publish(
            'mercato.adapter.tax_context.applied.v1',
            $payload,
            (int) ($payload['tenant_id'] ?? 0)
        );
    }

    /**
     * @param mixed $order WC_Order
     * @param array<string,mixed> $data checkout data
     */
    public function onCheckoutCreateOrder($order, array $data): void
    {
        if (!\is_object($order) || !\method_exists($order, 'update_meta_data')) {
            return;
        }

        $location = $this->currentServiceLocation();
        if ($location === null) {
            return;
        }

        $order->update_meta_data('_mercato_service_country', $location['country']);
        $order->update_meta_data('_mercato_service_state', $location['state']);
        $order->update_meta_data('_mercato_service_city', $location['city']);
        $order->update_meta_data('_mercato_service_postcode', $location['postcode']);

        $this->audit->log('adapter.tax_context.stamped', 'order', 0, null, $location);
    }

    /**
     * @return array{country:string,state:string,city:string,postcode:string}|null
     */
    private function currentServiceLocation(): ?array
    {
        $loc = $GLOBALS['mercato_service_location'] ?? null;
        if (!\is_array($loc)) {
            return null;
        }

        return [
            'country' => (string) ($loc['country'] ?? ''),
            'state' => (string) ($loc['state'] ?? ''),
            'city' => (string) ($loc['city'] ?? ''),
            'postcode' => (string) ($loc['postcode'] ?? ''),
        ];
    }
}
