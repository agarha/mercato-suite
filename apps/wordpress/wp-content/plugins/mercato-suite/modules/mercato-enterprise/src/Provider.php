<?php

declare(strict_types=1);

namespace Mercato\Enterprise;

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
            \register_rest_route('mercato/v1', '/enterprise/tenants', [
                'methods' => 'POST',
                'callback' => [$this, 'provision'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);
            \register_rest_route('mercato/v1', '/enterprise/capabilities', [
                'methods' => 'GET',
                'callback' => [$this, 'capabilities'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);
            \register_rest_route('mercato/v1', '/enterprise/features/(?P<feature>[A-Za-z0-9_.-]+)', [
                'methods' => 'POST',
                'callback' => [$this, 'setFlag'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);
            \register_rest_route('mercato/v1', '/enterprise/branding', [
                'methods' => 'POST',
                'callback' => [$this, 'branding'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);
        });
    }

    public function provision(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->repo()->provision((array) $request->get_json_params()), 201);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_tenant_provision_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function capabilities(): WP_REST_Response
    {
        return new WP_REST_Response($this->repo()->currentCapabilityToken(), 200);
    }

    public function setFlag(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->repo()->setFlag(
                (string) $request->get_param('feature'),
                (bool) $request->get_param('enabled'),
                $request->get_param('limit_value') === null ? null : (int) $request->get_param('limit_value'),
            ), 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_feature_toggle_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function branding(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->repo()->setBranding((array) $request->get_json_params()), 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_branding_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    private function repo(): Repository
    {
        return $this->container->get(Repository::class);
    }
}
