<?php

declare(strict_types=1);

namespace Mercato\Products;

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
            \register_rest_route('mercato/v1', '/products', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'list'],
                    'permission_callback' => [Permissions::class, 'canRead'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'create'],
                    'permission_callback' => [Permissions::class, 'canManage'],
                ],
            ]);

            \register_rest_route('mercato/v1', '/categories', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'categories'],
                    'permission_callback' => [Permissions::class, 'canRead'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'createCategory'],
                    'permission_callback' => [Permissions::class, 'canManage'],
                ],
            ]);

            \register_rest_route('mercato/v1', '/products/(?P<id>\d+)/categories', [
                'methods' => 'POST',
                'callback' => [$this, 'assignCategories'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);

            \register_rest_route('mercato/v1', '/products/(?P<id>\d+)/offerings', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'offerings'],
                    'permission_callback' => [Permissions::class, 'canRead'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'upsertOffering'],
                    'permission_callback' => [Permissions::class, 'canManage'],
                ],
            ]);

            \register_rest_route('mercato/v1', '/vendors/(?P<vendor_id>\d+)/locations', [
                'methods' => 'POST',
                'callback' => [$this, 'addVendorLocation'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);

            \register_rest_route('mercato/v1', '/products/(?P<id>\d+)/archive', [
                'methods' => 'POST',
                'callback' => [$this, 'archive'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);
        });
    }

    public function list(WP_REST_Request $request): WP_REST_Response
    {
        $vendorId = $request->get_param('vendor_id');
        $categoryId = $request->get_param('category_id');
        $latitude = $request->get_param('latitude');
        $longitude = $request->get_param('longitude');
        $radiusKm = $request->get_param('radius_km');

        return new WP_REST_Response($this->repo()->list(
            $vendorId === null ? null : (int) $vendorId,
            $categoryId === null ? null : (int) $categoryId,
            $latitude === null ? null : (float) $latitude,
            $longitude === null ? null : (float) $longitude,
            $radiusKm === null ? null : (float) $radiusKm,
        ), 200);
    }

    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $product = $this->repo()->create((array) $request->get_json_params());
                $this->audit('product.created', 'product', (int) $product['product_id'], null, $product);
                return new WP_REST_Response($product, 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_product_create_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function archive(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $product = $this->repo()->archive((int) $request->get_param('id'));
                $this->audit('product.archived', 'product', (int) $product['product_id'], null, $product);
                return new WP_REST_Response($product, 200);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_product_archive_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function categories(): WP_REST_Response
    {
        return new WP_REST_Response($this->repo()->categories(), 200);
    }

    public function createCategory(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $category = $this->repo()->createCategory((array) $request->get_json_params());
                $this->audit('category.created', 'category', (int) $category['category_id'], null, $category);
                return new WP_REST_Response($category, 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_category_create_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function assignCategories(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $ids = $this->repo()->assignCategories((int) $request->get_param('id'), (array) (((array) $request->get_json_params())['category_ids'] ?? []));
                $payload = ['product_id' => (int) $request->get_param('id'), 'category_ids' => $ids];
                $this->audit('product.categories.updated', 'product', (int) $payload['product_id'], null, $payload);
                return new WP_REST_Response($payload, 200);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_product_categories_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function offerings(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->repo()->offerings((int) $request->get_param('id')), 200);
    }

    public function upsertOffering(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $data = (array) $request->get_json_params();
                $offering = $this->repo()->upsertOffering((int) $request->get_param('id'), (int) ($data['vendor_id'] ?? 0), $data);
                $this->audit('product.offering.upserted', 'product', (int) $request->get_param('id'), null, $offering);
                return new WP_REST_Response($offering, 200);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_product_offering_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function addVendorLocation(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $location = $this->repo()->addVendorLocation((int) $request->get_param('vendor_id'), (array) $request->get_json_params());
                $this->audit('vendor.location.created', 'vendor', (int) $request->get_param('vendor_id'), null, $location);
                return new WP_REST_Response($location, 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_vendor_location_failed', $e->getMessage(), ['status' => 400]);
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
