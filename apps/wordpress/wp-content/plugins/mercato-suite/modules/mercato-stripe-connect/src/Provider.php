<?php

declare(strict_types=1);

namespace Mercato\StripeConnect;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Audit\Writer;
use Mercato\Core\Rest\Permissions;
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
                    'permission_callback' => [Permissions::class, 'canManage'],
                ],
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'account'],
                    'permission_callback' => [Permissions::class, 'canRead'],
                ],
            ]);

            \register_rest_route('mercato/v1', '/stripe/webhook', [
                'methods' => 'POST',
                'callback' => [$this, 'webhook'],
                'permission_callback' => [Permissions::class, 'canWebhook'],
            ]);

            \register_rest_route('mercato/v1', '/stripe/payout-batches/(?P<batch_id>\d+)/execute', [
                'methods' => 'POST',
                'callback' => [$this, 'executeBatch'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);

            \register_rest_route('mercato/v1', '/stripe/payment-intents', [
                'methods' => 'POST',
                'callback' => [$this, 'createPaymentIntent'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);

            \register_rest_route('mercato/v1', '/stripe/refunds', [
                'methods' => 'POST',
                'callback' => [$this, 'createRefund'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);
        });
    }

    public function createAccount(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $account = $this->repo()->createAccount((int) $request->get_param('vendor_id'), (array) $request->get_json_params());
                $this->audit('stripe.account.created', 'vendor', (int) $account['vendor_id'], null, $account);
                return new WP_REST_Response($account, 201);
            });
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
                (string) $request->get_header('stripe-signature'),
                (string) $request->get_body()
            ), 202);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_stripe_webhook_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function executeBatch(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $result = $this->repo()->executePayoutBatch((int) $request->get_param('batch_id'));
                $this->audit('stripe.payout_batch.executed', 'payout_batch', (int) $result['batch_id'], null, $result);
                return new WP_REST_Response($result, 200);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_stripe_transfer_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function createPaymentIntent(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $intent = $this->repo()->createPaymentIntent((array) $request->get_json_params());
                $this->audit('stripe.payment_intent.created', 'order', (int) $intent['wc_order_id'], null, $intent);
                return new WP_REST_Response($intent, 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_stripe_payment_intent_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function createRefund(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $refund = $this->repo()->createRefund((array) $request->get_json_params());
                $this->audit('stripe.refund.created', 'order', (int) $refund['wc_order_id'], null, $refund);
                return new WP_REST_Response($refund, 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_stripe_refund_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    private function repo(): Repository
    {
        return $this->container->get(Repository::class);
    }

    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    private function audit(string $action, string $entityType, int $entityId, ?array $before, ?array $after): void
    {
        $this->container->get(Writer::class)->log($action, $entityType, $entityId, $before, $after);
    }
}
