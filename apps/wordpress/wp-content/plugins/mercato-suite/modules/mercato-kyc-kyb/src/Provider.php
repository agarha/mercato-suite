<?php

declare(strict_types=1);

namespace Mercato\KycKyb;

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
        $this->container->bind(Repository::class, fn ($c): Repository => new Repository($c->get(Resolver::class), $c->get(Outbox::class)));
    }

    public function boot(): void
    {
        if (!\function_exists('register_rest_route')) {
            return;
        }
        \add_action('rest_api_init', function (): void {
            \register_rest_route('mercato/v1', '/kyc/(?P<vendor_id>\d+)/start', [
                'methods' => 'POST',
                'callback' => [$this, 'start'],
                'permission_callback' => '__return_true',
            ]);
            \register_rest_route('mercato/v1', '/kyc/(?P<vendor_id>\d+)/status', [
                'methods' => 'POST',
                'callback' => [$this, 'status'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public function start(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->container->get(Repository::class)->start((int) $request->get_param('vendor_id')), 201);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_kyc_start_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function status(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->container->get(Repository::class)->updateStatus((int) $request->get_param('vendor_id'), (string) $request->get_param('status')), 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_kyc_status_failed', $e->getMessage(), ['status' => 400]);
        }
    }
}
