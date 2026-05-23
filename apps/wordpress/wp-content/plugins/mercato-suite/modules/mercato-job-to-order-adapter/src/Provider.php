<?php

declare(strict_types=1);

namespace Mercato\Adapters\JobToOrder;

use Mercato\Core\Audit\Writer;
use Mercato\Core\Events\Outbox;
use Mercato\Core\ServiceProvider;
use Mercato\Core\Tenant\Resolver;

/**
 * JobToOrder adapter.
 *
 * Bridges Mercato award -> WooCommerce order.
 *
 * Trigger: when a provider bid is awarded inside Mercato, this adapter
 * mints a WC order so the buyer has a payable cart/checkout entry.
 *
 * The adapter does NOT own bid logic, commission math, or job state.
 * It calls wc_create_order() and attaches mercato_job_id / tenant_id
 * line-item meta. Downstream lifecycle handling lives in the
 * OrderToJob adapter and in mercato-jobs / mercato-commissions.
 *
 * Hook subscriptions: NONE (called by mercato-orders when a bid is awarded).
 *
 * See CODEX_DIRECTIVE.md §3.3 and §4.
 */
final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind(JobToOrderAdapter::class, fn ($c): JobToOrderAdapter => new JobToOrderAdapter(
            $c->get(Resolver::class),
            $c->get(Outbox::class),
            $c->get(Writer::class),
        ));
    }

    public function boot(): void
    {
        // No WC hook subscriptions: this adapter is invoked directly
        // by the bid award use-case in mercato-orders. Keeping the boot
        // method explicit so future drift-scans see "no hooks" plainly.
    }
}
