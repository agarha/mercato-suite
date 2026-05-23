<?php

declare(strict_types=1);

namespace Mercato\Adapters\OrderToJob;

use Mercato\Core\Audit\Writer;
use Mercato\Core\Events\Outbox;
use Mercato\Core\ServiceProvider;

/**
 * OrderToJob adapter.
 *
 * Listens to WooCommerce order lifecycle hooks and advances the
 * corresponding Mercato job state.
 *
 * Canonical hooks subscribed (CODEX_DIRECTIVE.md §4):
 *   - woocommerce_new_order            -> link new order to Mercato job
 *   - woocommerce_payment_complete     -> transition job to 'paid', notify provider
 *   - woocommerce_order_status_changed -> reflect status into job state machine
 *
 * The adapter only reads from WC orders and writes to its own outbox.
 * Job state transitions, commission accrual, and provider notification
 * are owned by mercato-jobs / mercato-commissions / mercato-notifications,
 * which subscribe to the events emitted here.
 */
final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind(OrderToJobAdapter::class, fn ($c): OrderToJobAdapter => new OrderToJobAdapter(
            $c->get(Outbox::class),
            $c->get(Writer::class),
        ));
    }

    public function boot(): void
    {
        if (!\function_exists('add_action')) {
            return;
        }

        $adapter = $this->container->get(OrderToJobAdapter::class);

        \add_action('woocommerce_new_order', [$adapter, 'onNewOrder'], 20, 1);
        \add_action('woocommerce_payment_complete', [$adapter, 'onPaymentComplete'], 20, 1);
        \add_action('woocommerce_order_status_changed', [$adapter, 'onStatusChanged'], 20, 4);
    }
}
