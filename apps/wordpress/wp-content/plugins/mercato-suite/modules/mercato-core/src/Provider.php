<?php

declare(strict_types=1);

namespace Mercato\Core;

final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->instance(Container::class, $this->container);

        $this->container->bind(Tenant\Resolver::class, fn (): Tenant\Resolver => new Tenant\Resolver());
        $this->container->bind(DB\Migrator::class, fn (): DB\Migrator => new DB\Migrator($this->container));
        $this->container->bind(Events\Outbox::class, fn (): Events\Outbox => new Events\Outbox($this->container->get(Tenant\Resolver::class)));
        $this->container->bind(Idempotency\Store::class, fn (): Idempotency\Store => new Idempotency\Store($this->container->get(Tenant\Resolver::class)));
        $this->container->bind(Audit\Writer::class, fn (): Audit\Writer => new Audit\Writer($this->container->get(Tenant\Resolver::class)));
        $this->container->bind(RBAC\Engine::class, fn (): RBAC\Engine => new RBAC\Engine($this->container->get(Tenant\Resolver::class)));
        $this->container->bind(WooCommerce\HookAdapter::class, fn (): WooCommerce\HookAdapter => new WooCommerce\HookAdapter($this->container->get(Events\Outbox::class)));
    }

    public function boot(): void
    {
        $this->container->get(WooCommerce\HookAdapter::class)->register();
    }
}
