<?php

declare(strict_types=1);

namespace Mercato\Core\Geo;

use Mercato\Core\Rest\Permissions;
use Mercato\Core\Tenant\Resolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Region picker REST surface used by the signup form's cascading
 * dropdowns and by the storefront discovery widgets.
 *
 * Endpoints (all tenant-scoped at the SQL layer):
 *   GET /mercato/v1/geo/regions?type=province           - all top-level rows
 *   GET /mercato/v1/geo/regions?parent=ontario          - children of a region
 *   GET /mercato/v1/geo/regions?parent_id=42&type=city  - same, by id
 *   GET /mercato/v1/geo/regions?q=toron                 - autocomplete search
 *
 * Returns arrays of {region_id, parent_id, type, code, name, slug, latitude,
 * longitude, radius_km, population}. Capped at 200 results per call.
 */
final class Provider
{
    public function __construct(private readonly Resolver $tenantResolver)
    {
    }

    public function boot(): void
    {
        if (!\function_exists('register_rest_route')) {
            return;
        }

        \add_action('rest_api_init', function (): void {
            \register_rest_route('mercato/v1', '/geo/regions', [
                'methods' => 'GET',
                'callback' => [$this, 'listRegions'],
                'permission_callback' => [Permissions::class, 'canPublicHealth'],
            ]);
        });
    }

    public function listRegions(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;
        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_geo_regions';

        $type = $request->get_param('type');
        $parentSlug = $request->get_param('parent');
        $parentId = $request->get_param('parent_id');
        $q = (string) $request->get_param('q');

        if (\function_exists('sanitize_text_field')) {
            $q = \sanitize_text_field($q);
        }
        if (\strlen($q) > 100) {
            $q = \substr($q, 0, 100);
        }

        $sql = "SELECT region_id, parent_id, type, code, name, slug, country_code, latitude, longitude, radius_km, population FROM `{$table}` WHERE tenant_id = %d";
        $params = [$tenantId];

        // Resolve a slug parent into an id if given.
        if ($parentId === null && $parentSlug !== null && $parentSlug !== '') {
            $parentId = $wpdb->get_var($wpdb->prepare(
                "SELECT region_id FROM `{$table}` WHERE tenant_id = %d AND slug = %s",
                $tenantId,
                (string) $parentSlug
            ));
        }

        if ($parentId !== null && $parentId !== '') {
            $sql .= " AND parent_id = %d";
            $params[] = (int) $parentId;
        }

        if ($type !== null && $type !== '') {
            $sql .= " AND type = %s";
            $params[] = (string) $type;
        }

        if ($q !== '') {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $sql .= " AND name LIKE %s";
            $params[] = $like;
        }

        $sql .= " ORDER BY sort_order ASC, name ASC LIMIT 200";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
        return new WP_REST_Response($rows, 200);
    }
}
