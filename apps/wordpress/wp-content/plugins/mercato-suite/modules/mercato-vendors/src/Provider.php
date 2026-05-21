<?php

declare(strict_types=1);

namespace Mercato\Vendors;

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
            \register_rest_route('mercato/v1', '/vendors', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'list'],
                    'permission_callback' => [Permissions::class, 'canManage'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'registerVendor'],
                    'permission_callback' => [Permissions::class, 'canPublicRegister'],
                ],
            ]);

            \register_rest_route('mercato/v1', '/vendors/(?P<id>\d+)/status', [
                'methods' => 'POST',
                'callback' => [$this, 'setStatus'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);
        });
    }

    public function list(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->repo()->list((string) $request->get_param('status')), 200);
    }

    public function registerVendor(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $ownerUserId = \function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
            $vendor = $this->repo()->register((array) $request->get_json_params(), $ownerUserId);
            $this->audit('vendor.registered', 'vendor', (int) $vendor['vendor_id'], null, $vendor);
            return new WP_REST_Response($vendor, 201);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_vendor_registration_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function setStatus(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $vendor = $this->repo()->setStatus(
                (int) $request->get_param('id'),
                (string) $request->get_param('status'),
                $request->get_param('reason') === null ? null : (string) $request->get_param('reason')
            );
            $this->audit('vendor.status.updated', 'vendor', (int) $vendor['vendor_id'], null, $vendor);
            return new WP_REST_Response($vendor, 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_vendor_status_failed', $e->getMessage(), ['status' => 400]);
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
