<?php

declare(strict_types=1);

namespace Mercato\Adapters\SubscriptionBridge;

use Mercato\Core\Audit\Writer;
use Mercato\Core\Events\Outbox;
use Mercato\Core\ServiceProvider;

/**
 * SubscriptionBridge adapter.
 *
 * Maps WooCommerce Subscriptions cadence onto Mercato recurring service
 * jobs. Per CODEX_DIRECTIVE.md §5.2 Mercato MUST NOT implement its own
 * subscription cadence or dunning engine — WC Subscriptions owns that.
 *
 * Canonical hooks subscribed (CODEX_DIRECTIVE.md §4):
 *   - woocommerce_subscription_status_updated -> emit status change event
 *   - woocommerce_subscription_renewal_payment_complete -> emit renewed event
 *
 * The renewal hook (`woocommerce_subscription_renewal_payment_complete`)
 * is a derivative of `woocommerce_payment_complete` and is the canonical
 * signal in the WC Subscriptions plugin per
 * docs_v2/13_woocommerce_compat/WooCommerce_HPOS_Compat.md §4. If WC
 * Subscriptions is not installed, the boot() check no-ops.
 */
final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind(SubscriptionBridgeAdapter::class, fn ($c): SubscriptionBridgeAdapter => new SubscriptionBridgeAdapter(
            $c->get(Outbox::class),
            $c->get(Writer::class),
        ));
    }

    public function boot(): void
    {
        if (!\function_exists('add_action')) {
            return;
        }

        if (!\class_exists('WC_Subscriptions') && !\function_exists('wcs_get_subscription')) {
            return; // WC Subscriptions plugin not active — adapter idle.
        }

        $adapter = $this->container->get(SubscriptionBridgeAdapter::class);

        \add_action('woocommerce_subscription_status_updated', [$adapter, 'onStatusUpdated'], 20, 3);
        \add_action('woocommerce_subscription_renewal_payment_complete', [$adapter, 'onRenewalPaymentComplete'], 20, 2);
    }
}
