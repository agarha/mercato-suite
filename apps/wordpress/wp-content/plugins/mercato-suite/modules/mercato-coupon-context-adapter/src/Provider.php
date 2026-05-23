<?php

declare(strict_types=1);

namespace Mercato\Adapters\CouponContext;

use Mercato\Core\Audit\Writer;
use Mercato\Core\Events\Outbox;
use Mercato\Core\ServiceProvider;
use Mercato\Core\Tenant\Resolver;

/**
 * CouponContext adapter.
 *
 * Restricts WooCommerce coupon validation to the current tenant /
 * provider / job context. Per CODEX_DIRECTIVE.md §5.1 Mercato MUST NOT
 * implement its own coupon engine — instead it filters WC coupons via
 * the one canonical hook below.
 *
 * Canonical hooks subscribed (CODEX_DIRECTIVE.md §4):
 *   - woocommerce_coupon_is_valid -> filter validity for tenant scope
 */
final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind(CouponContextAdapter::class, fn ($c): CouponContextAdapter => new CouponContextAdapter(
            $c->get(Resolver::class),
            $c->get(Outbox::class),
            $c->get(Writer::class),
        ));
    }

    public function boot(): void
    {
        if (!\function_exists('add_filter')) {
            return;
        }

        $adapter = $this->container->get(CouponContextAdapter::class);

        \add_filter('woocommerce_coupon_is_valid', [$adapter, 'filterIsValid'], 20, 3);
    }
}
