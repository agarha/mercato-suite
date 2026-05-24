<?php

declare(strict_types=1);

namespace Mercato\Reviews;

use Mercato\Core\Audit\Writer;
use Mercato\Core\Events\Outbox;
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
            \register_rest_route('mercato/v1', '/vendors/(?P<vendor_id>\d+)/reviews', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'list'],
                    'permission_callback' => [Permissions::class, 'canRead'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'create'],
                    'permission_callback' => [Permissions::class, 'canWrite'],
                ],
            ]);

            \register_rest_route('mercato/v1', '/modules/mercato-reviews', [
                'methods' => 'GET',
                'callback' => fn (): WP_REST_Response => new WP_REST_Response(['module' => 'mercato-reviews', 'status' => 'live'], 200),
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
        });
    }

    public function list(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $vendorId = (int) $request->get_param('vendor_id');
            $limit = \min(50, \max(1, (int) ($request->get_param('limit') ?: 20)));
            $data = $this->repo()->forVendor($vendorId, $limit);
            return new WP_REST_Response($data, 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_reviews_list_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $vendorId = (int) $request->get_param('vendor_id');
                $body = (array) $request->get_json_params();
                $userId = \function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
                $review = $this->repo()->create($vendorId, $userId, $body);
                $this->container->get(Writer::class)->log('review.created', 'vendor', $vendorId, null, $review);
                return new WP_REST_Response($review, 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_reviews_create_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    private function repo(): Repository
    {
        return $this->container->get(Repository::class);
    }
}
