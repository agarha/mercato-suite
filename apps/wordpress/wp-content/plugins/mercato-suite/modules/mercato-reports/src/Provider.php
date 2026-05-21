<?php

declare(strict_types=1);

namespace Mercato\Reports;

use Mercato\Core\ServiceProvider;
use Mercato\Core\Tenant\Resolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind(Repository::class, fn ($c): Repository => new Repository($c->get(Resolver::class)));
    }

    public function boot(): void
    {
        if (!\function_exists('register_rest_route')) {
            return;
        }

        \add_action('rest_api_init', function (): void {
            \register_rest_route('mercato/v1', '/reports/dashboard', [
                'methods' => 'GET',
                'callback' => [$this, 'dashboard'],
                'permission_callback' => '__return_true',
            ]);
            \register_rest_route('mercato/v1', '/reports/vendors', [
                'methods' => 'GET',
                'callback' => [$this, 'vendors'],
                'permission_callback' => '__return_true',
            ]);
            \register_rest_route('mercato/v1', '/reports/export', [
                'methods' => 'POST',
                'callback' => [$this, 'export'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public function dashboard(): WP_REST_Response
    {
        return new WP_REST_Response($this->repo()->dashboard(), 200);
    }

    public function vendors(WP_REST_Request $request): WP_REST_Response
    {
        $vendorId = $request->get_param('vendor_id');
        return new WP_REST_Response($this->repo()->vendorSummary($vendorId === null ? null : (int) $vendorId), 200);
    }

    public function export(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->repo()->createCsvExport((string) ($request->get_param('report_type') ?: 'dashboard')), 201);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_report_export_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    private function repo(): Repository
    {
        return $this->container->get(Repository::class);
    }
}
