<?php

declare(strict_types=1);

namespace Mercato\Commissions;

use Mercato\Core\Events\Outbox;
use Mercato\Core\ServiceProvider;
use Mercato\Core\Tenant\Resolver;

final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind(Calculator::class, fn ($c): Calculator => new Calculator(
            $c->get(Resolver::class),
            $c->get(Outbox::class),
        ));
    }

    public function boot(): void
    {
        if (!\function_exists('add_action')) {
            return;
        }

        \add_action('mercato_suborder_created', [$this->container->get(Calculator::class), 'recordForSuborder'], 10, 1);
    }
}
