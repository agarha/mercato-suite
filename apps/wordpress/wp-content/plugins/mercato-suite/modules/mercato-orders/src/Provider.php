<?php

declare(strict_types=1);

namespace Mercato\Orders;

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

        $splitter = $this->container->get(Splitter::class);
        \add_action('woocommerce_checkout_order_processed', [$splitter, 'split'], 20, 1);
        \add_action('woocommerce_payment_complete', [$splitter, 'markPaymentComplete'], 20, 1);

        if (\function_exists('register_rest_route')) {
            \add_action('rest_api_init', function (): void {
                \register_rest_route('mercato/v1', '/orders/(?P<wc_order_id>\d+)/payment-complete', [
                    'methods' => 'POST',
                    'callback' => [$this, 'paymentComplete'],
                    'permission_callback' => [Permissions::class, 'canManage'],
                ]);

                \register_rest_route('mercato/v1', '/orders/suborders/(?P<suborder_id>\d+)/refund', [
                    'methods' => 'POST',
                    'callback' => [$this, 'refundSuborder'],
                    'permission_callback' => [Permissions::class, 'canManage'],
                ]);
            });
        }
    }

    public function paymentComplete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $suborders = $this->container->get(Splitter::class)->markPaymentComplete((int) $request->get_param('wc_order_id'));
            $this->audit('order.payment.completed', 'order', (int) $request->get_param('wc_order_id'), null, ['suborders' => $suborders]);
            return new WP_REST_Response($suborders, 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_payment_complete_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function refundSuborder(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $refund = $this->container->get(Splitter::class)->refundSuborder(
                (int) $request->get_param('suborder_id'),
                (array) $request->get_json_params()
            );
            $this->audit('order.refund.created', 'refund', (int) $refund['refund_id'], null, $refund);
            return new WP_REST_Response($refund, 201);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_refund_failed', $e->getMessage(), ['status' => 400]);
        }
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
