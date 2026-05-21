<?php

declare(strict_types=1);

namespace Mercato\Orders;

use Mercato\Core\Events\Outbox;
use Mercato\Core\ServiceProvider;
use Mercato\Core\Tenant\Resolver;

final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind(Splitter::class, fn ($c): Splitter => new Splitter(
            $c->get(Resolver::class),
            $c->get(Outbox::class),
        ));
    }

    public function boot(): void
    {
        if (!\function_exists('add_action')) {
            return;
        }

        \add_action('woocommerce_checkout_order_processed', [$this->container->get(Splitter::class), 'split'], 20, 1);
    }
}
