<?php

declare(strict_types=1);

namespace Mercato\Products;

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
            \register_rest_route('mercato/v1', '/products', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'list'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'create'],
                    'permission_callback' => '__return_true',
                ],
            ]);

            \register_rest_route('mercato/v1', '/products/(?P<id>\d+)/archive', [
                'methods' => 'POST',
                'callback' => [$this, 'archive'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public function list(WP_REST_Request $request): WP_REST_Response
    {
        $vendorId = $request->get_param('vendor_id');
        return new WP_REST_Response($this->repo()->list($vendorId === null ? null : (int) $vendorId), 200);
    }

    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->repo()->create((array) $request->get_json_params()), 201);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_product_create_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function archive(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->repo()->archive((int) $request->get_param('id')), 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_product_archive_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    private function repo(): Repository
    {
        return $this->container->get(Repository::class);
    }
}
