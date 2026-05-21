<?php

declare(strict_types=1);

namespace Mercato\StripeConnect;

use Mercato\Core\Events\Outbox;
use Mercato\Core\ServiceProvider;
use Mercato\Core\Tenant\Resolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind(Repository::class, fn ($c): Repository => new Repository(
            $c->get(Resolver::class),
            $c->get(Outbox::class),
        ));
    }

    public function boot(): void
    {
        if (!\function_exists('register_rest_route')) {
            return;
        }

        \add_action('rest_api_init', function (): void {
            \register_rest_route('mercato/v1', '/stripe/vendors/(?P<vendor_id>\d+)/account', [
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'createAccount'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'account'],
                    'permission_callback' => '__return_true',
                ],
            ]);

            \register_rest_route('mercato/v1', '/stripe/webhook', [
                'methods' => 'POST',
                'callback' => [$this, 'webhook'],
                'permission_callback' => '__return_true',
            ]);

            \register_rest_route('mercato/v1', '/stripe/payout-batches/(?P<batch_id>\d+)/execute', [
                'methods' => 'POST',
                'callback' => [$this, 'executeBatch'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public function createAccount(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->repo()->createAccount((int) $request->get_param('vendor_id'), (array) $request->get_json_params()), 201);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_stripe_account_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function account(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->repo()->account((int) $request->get_param('vendor_id')), 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_stripe_account_missing', $e->getMessage(), ['status' => 404]);
        }
    }

    public function webhook(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->repo()->recordWebhook(
                (array) $request->get_json_params(),
                (string) $request->get_header('stripe-signature')
            ), 202);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_stripe_webhook_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function executeBatch(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->repo()->executePayoutBatch((int) $request->get_param('batch_id')), 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_stripe_transfer_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    private function repo(): Repository
    {
        return $this->container->get(Repository::class);
    }
}
