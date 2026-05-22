<?php

declare(strict_types=1);

namespace Mercato\Payouts;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Audit\Writer;
use Mercato\Core\Rest\Permissions;
use Mercato\Core\ServiceProvider;
use Mercato\Core\Tenant\Resolver;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

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
                'callback' => [$this, 'triggerBatch'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);

            \register_rest_route('mercato/v1', '/payouts/reconciliation', [
                'methods' => 'POST',
                'callback' => [$this, 'reconcile'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);
        });
    }

    public function reconcile(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function (): WP_REST_Response {
                $run = $this->container->get(Ledger::class)->reconcile();
                $this->container->get(Writer::class)->log('payout.reconciled', 'reconciliation_run', (int) $run['run_id'], null, $run);
                return new WP_REST_Response($run, 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_payout_reconciliation_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function triggerBatch(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function (): WP_REST_Response {
                $batch = $this->container->get(Ledger::class)->triggerBatch();
                $this->container->get(Writer::class)->log('payout.batch.scheduled', 'payout_batch', (int) $batch['batch_id'], null, $batch);
                return new WP_REST_Response($batch, 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_payout_batch_failed', $e->getMessage(), ['status' => 400]);
        }
    }
}
