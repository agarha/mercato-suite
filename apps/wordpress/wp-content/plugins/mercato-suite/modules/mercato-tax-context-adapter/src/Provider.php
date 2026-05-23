<?php

declare(strict_types=1);

namespace Mercato\Adapters\TaxContext;

use Mercato\Core\Audit\Writer;
use Mercato\Core\Events\Outbox;
use Mercato\Core\ServiceProvider;
use Mercato\Core\Tenant\Resolver;

/**
 * TaxContext adapter.
 *
 * Passes service-delivery location into WC tax calculation flow so the
 * tax engine (WC core, TaxJar plugin, Avalara plugin, ...) receives the
 * right jurisdiction. Mercato does NOT compute tax itself
 * (CODEX_DIRECTIVE.md §5.3).
 *
 * Canonical hooks subscribed (CODEX_DIRECTIVE.md §4):
 *   - woocommerce_cart_calculate_fees     -> emit context event (read-only)
 *   - woocommerce_checkout_create_order   -> stamp service-location meta
 */
final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind(TaxContextAdapter::class, fn ($c): TaxContextAdapter => new TaxContextAdapter(
            $c->get(Resolver::class),
            $c->get(Outbox::class),
            $c->get(Writer::class),
        ));
    }

    public function boot(): void
    {
        if (!\function_exists('add_action')) {
            return;
        }

        $adapter = $this->container->get(TaxContextAdapter::class);

        \add_action('woocommerce_cart_calculate_fees', [$adapter, 'onCartCalculateFees'], 5, 1);
        \add_action('woocommerce_checkout_create_order', [$adapter, 'onCheckoutCreateOrder'], 20, 2);
    }
}
