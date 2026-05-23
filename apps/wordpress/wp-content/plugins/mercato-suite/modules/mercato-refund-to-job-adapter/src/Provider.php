<?php

declare(strict_types=1);

namespace Mercato\Adapters\RefundToJob;

use Mercato\Core\Audit\Writer;
use Mercato\Core\Events\Outbox;
use Mercato\Core\ServiceProvider;

/**
 * RefundToJob adapter.
 *
 * Listens to WC refund events and emits Mercato events so commission
 * accruals and provider payout obligations can be reversed.
 *
 * Canonical hooks subscribed (CODEX_DIRECTIVE.md §4):
 *   - woocommerce_refund_created   -> reverse commission
 *   - woocommerce_order_refunded   -> post-refund side effects
 *
 * Does NOT perform the reversal itself — that is mercato-commissions
 * and mercato-payouts work. This adapter just bridges the WC signal.
 */
final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind(RefundToJobAdapter::class, fn ($c): RefundToJobAdapter => new RefundToJobAdapter(
            $c->get(Outbox::class),
            $c->get(Writer::class),
        ));
    }

    public function boot(): void
    {
        if (!\function_exists('add_action')) {
            return;
        }

        $adapter = $this->container->get(RefundToJobAdapter::class);

        \add_action('woocommerce_refund_created', [$adapter, 'onRefundCreated'], 20, 2);
        \add_action('woocommerce_order_refunded', [$adapter, 'onOrderRefunded'], 20, 2);
    }
}
