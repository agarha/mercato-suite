<?php

declare(strict_types=1);

namespace Mercato\Payouts;

use Mercato\Core\Events\Outbox;
use Mercato\Core\ServiceProvider;
use Mercato\Core\Tenant\Resolver;
use WP_REST_Response;

final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind(Ledger::class, fn ($c): Ledger => new Ledger(
            $c->get(Resolver::class),
            $c->get(Outbox::class),
        ));
    }

    public function boot(): void
    {
        if (!\function_exists('add_action')) {
            return;
        }

        \add_action('mercato_commission_recorded', [$this->container->get(Ledger::class), 'recordCommission'], 10, 1);

        \add_action('rest_api_init', function (): void {
            \register_rest_route('mercato/v1', '/payouts/batches', [
                'methods' => 'POST',
                'callback' => fn (): WP_REST_Response => new WP_REST_Response($this->container->get(Ledger::class)->triggerBatch(), 201),
                'permission_callback' => fn (): bool => \function_exists('current_user_can') && \current_user_can('manage_options'),
            ]);
        });
    }
}
