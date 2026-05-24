<?php

declare(strict_types=1);

namespace Mercato\Core\Storefront;

use Mercato\Core\Geo\Geocoder;

/**
 * Storefront data access. Every method is tenant-scoped at the SQL layer.
 */
final class Repository
{
    /**
     * @return array<string,mixed>
     */
    public function snapshot(int $tenantId): array
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $products = $prefix . 'mercato_products';
        $vendors = $prefix . 'mercato_vendors';
        $suborders = $prefix . 'mercato_suborders';
        $commissions = $prefix . 'mercato_commissions';
        $payouts = $prefix . 'mercato_payout_batches';
        $notifications = $prefix . 'mercato_notification_deliveries';
        $categories = $prefix . 'mercato_categories';
        $jobs = $prefix . 'mercato_jobs';
        $bookings = $prefix . 'mercato_booking_requests';
        $estimates = $prefix . 'mercato_estimates';
        $referrals = $prefix . 'mercato_referrals';
        $serviceRequests = $prefix . 'mercato_service_requests';
        $serviceBids = $prefix . 'mercato_service_bids';
        $flags = $prefix . 'mercato_tenant_feature_flags';
        $integrations = $prefix . 'mercato_tenant_integrations';

        return [
            'products' => $wpdb->get_results($wpdb->prepare("SELECT p.product_id, p.title, p.description, p.price_minor, p.stock_quantity, p.status, v.business_name, v.store_slug FROM `{$products}` p INNER JOIN `{$vendors}` v ON v.vendor_id = p.vendor_id AND v.tenant_id = p.tenant_id WHERE p.tenant_id = %d AND p.status = 'active' ORDER BY p.created_at DESC LIMIT 8", $tenantId), ARRAY_A) ?: [],
            'vendor_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$vendors}` WHERE tenant_id = %d AND status = 'approved'", $tenantId)),
            'product_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$products}` WHERE tenant_id = %d AND status = 'active'", $tenantId)),
            'suborder_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$suborders}` WHERE tenant_id = %d", $tenantId)),
            'take_rate_minor' => (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(platform_fee_minor), 0) FROM `{$commissions}` WHERE tenant_id = %d", $tenantId)),
            'vendors' => $wpdb->get_results($wpdb->prepare("SELECT vendor_id, business_name, store_slug, status, stripe_account_id FROM `{$vendors}` WHERE tenant_id = %d AND status = 'approved' ORDER BY vendor_id DESC LIMIT 6", $tenantId), ARRAY_A) ?: [],
            'orders' => $wpdb->get_results($wpdb->prepare("SELECT suborder_id, vendor_id, wc_order_id, status, payment_status, total_minor, refunded_minor, tracking_carrier, tracking_number FROM `{$suborders}` WHERE tenant_id = %d ORDER BY suborder_id DESC LIMIT 5", $tenantId), ARRAY_A) ?: [],
            'latest_payout' => $wpdb->get_row($wpdb->prepare("SELECT batch_id, status, total_minor, created_at FROM `{$payouts}` WHERE tenant_id = %d ORDER BY batch_id DESC LIMIT 1", $tenantId), ARRAY_A) ?: [],
            'latest_notification' => $wpdb->get_row($wpdb->prepare("SELECT delivery_id, recipient, subject, status FROM `{$notifications}` WHERE tenant_id = %d ORDER BY delivery_id DESC LIMIT 1", $tenantId), ARRAY_A) ?: [],
            'categories' => $wpdb->get_results($wpdb->prepare("SELECT p.category_id, p.name, COUNT(c.category_id) AS child_count FROM `{$categories}` p LEFT JOIN `{$categories}` c ON c.tenant_id = p.tenant_id AND c.parent_id = p.category_id WHERE p.tenant_id = %d AND p.parent_id IS NULL GROUP BY p.category_id, p.name, p.sort_order ORDER BY p.sort_order ASC, p.name ASC LIMIT 16", $tenantId), ARRAY_A) ?: [],
            'subcategories' => $wpdb->get_results($wpdb->prepare("SELECT c.name, p.name AS parent_name FROM `{$categories}` c INNER JOIN `{$categories}` p ON p.tenant_id = c.tenant_id AND p.category_id = c.parent_id WHERE c.tenant_id = %d ORDER BY p.sort_order ASC, c.sort_order ASC, c.name ASC LIMIT 42", $tenantId), ARRAY_A) ?: [],
            'jobs' => $wpdb->get_results($wpdb->prepare("SELECT j.job_id, j.status, j.assigned_user_id, j.updated_at, v.business_name FROM `{$jobs}` j LEFT JOIN `{$vendors}` v ON v.tenant_id = j.tenant_id AND v.vendor_id = j.vendor_id WHERE j.tenant_id = %d ORDER BY j.job_id DESC LIMIT 5", $tenantId), ARRAY_A) ?: [],
            'booking_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$bookings}` WHERE tenant_id = %d", $tenantId)),
            'job_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$jobs}` WHERE tenant_id = %d", $tenantId)),
            'estimate_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$estimates}` WHERE tenant_id = %d", $tenantId)),
            'referral_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$referrals}` WHERE tenant_id = %d", $tenantId)),
            'requests' => $wpdb->get_results($wpdb->prepare("SELECT r.request_id, r.title, r.city, r.region, r.budget_max_minor, r.currency, r.bid_mode, r.status, COUNT(b.bid_id) AS bid_count FROM `{$serviceRequests}` r LEFT JOIN `{$serviceBids}` b ON b.tenant_id = r.tenant_id AND b.request_id = r.request_id WHERE r.tenant_id = %d GROUP BY r.request_id, r.title, r.city, r.region, r.budget_max_minor, r.currency, r.bid_mode, r.status ORDER BY r.request_id DESC LIMIT 5", $tenantId), ARRAY_A) ?: [],
            'request_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$serviceRequests}` WHERE tenant_id = %d", $tenantId)),
            'bid_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$serviceBids}` WHERE tenant_id = %d", $tenantId)),
            'feature_flags' => $wpdb->get_results($wpdb->prepare("SELECT feature_key, enabled FROM `{$flags}` WHERE tenant_id = %d ORDER BY feature_key ASC", $tenantId), ARRAY_A) ?: [],
            'integrations' => $wpdb->get_results($wpdb->prepare("SELECT provider_key, status FROM `{$integrations}` WHERE tenant_id = %d ORDER BY provider_key ASC", $tenantId), ARRAY_A) ?: [],
        ];
    }

    /**
     * Services index page with optional full-text query + category filter
     * + optional geo proximity filter. When lat/lng are null the geo step
     * is skipped and behaviour matches the legacy non-geo variant.
     *
     * @return array<string,mixed>
     */
    public function servicesPage(int $tenantId, string $query = '', int $categoryId = 0, ?float $lat = null, ?float $lng = null, float $radiusKm = 25.0, string $listingType = ''): array
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $products = $prefix . 'mercato_products';
        $vendors = $prefix . 'mercato_vendors';
        $categories = $prefix . 'mercato_categories';
        $productCategories = $prefix . 'mercato_product_categories';
        $offerings = $prefix . 'mercato_vendor_service_offerings';

        // p.listing_type comes from migration 0004 — service|rental|digital|physical.
        // Older rows default to 'service' which keeps Gigsii correct.
        $sql = "SELECT p.product_id, p.title, p.description, p.price_minor, p.stock_quantity, p.vendor_id,
                       p.listing_type, p.min_rental_window_minutes, p.max_rental_window_minutes,
                       p.deposit_minor, p.replacement_value_minor,
                       v.business_name, v.store_slug, v.headline, v.photo_url, v.years_experience,
                       o.offering_id, o.pricing_type, o.unit_label, o.summary, o.duration_minutes
                FROM `{$products}` p
                INNER JOIN `{$vendors}` v ON v.vendor_id = p.vendor_id AND v.tenant_id = p.tenant_id
                LEFT JOIN `{$offerings}` o ON o.tenant_id = p.tenant_id AND o.product_id = p.product_id AND o.vendor_id = p.vendor_id AND o.status = 'active'";
        $params = [];

        if ($categoryId > 0) {
            $sql .= " INNER JOIN `{$productCategories}` pc ON pc.product_id = p.product_id AND pc.tenant_id = p.tenant_id AND pc.category_id = %d";
            $params[] = $categoryId;
        }

        $sql .= " WHERE p.tenant_id = %d AND p.status = 'active' AND v.status = 'approved'";
        $params[] = $tenantId;

        if ($query !== '') {
            $like = '%' . $wpdb->esc_like($query) . '%';
            $sql .= " AND (p.title LIKE %s OR p.description LIKE %s OR v.business_name LIKE %s OR v.bio LIKE %s)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($listingType !== '' && \in_array($listingType, ['service', 'rental', 'digital', 'physical'], true)) {
            $sql .= " AND p.listing_type = %s";
            $params[] = $listingType;
        }

        $sql .= " ORDER BY p.created_at DESC LIMIT 120";

        $services = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];

        if ($lat !== null && $lng !== null) {
            $services = $this->applyGeoFilter($tenantId, $services, $lat, $lng, $radiusKm);
        }

        return [
            'categories' => $wpdb->get_results($wpdb->prepare(
                "SELECT category_id, name FROM `{$categories}` WHERE tenant_id = %d AND parent_id IS NULL ORDER BY sort_order ASC, name ASC",
                $tenantId
            ), ARRAY_A) ?: [],
            'services' => $services,
        ];
    }

    /**
     * Discovery feed for the homepage / "find pros near me" widget.
     * Returns lightweight provider cards with their best-fit service.
     *
     * @return list<array<string,mixed>>
     */
    public function discovery(int $tenantId, ?float $lat = null, ?float $lng = null, float $radiusKm = 25.0, int $categoryId = 0, int $limit = 12): array
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $vendors = $prefix . 'mercato_vendors';
        $products = $prefix . 'mercato_products';
        $productCategories = $prefix . 'mercato_product_categories';

        $sql = "SELECT DISTINCT v.vendor_id, v.business_name, v.store_slug, v.headline, v.photo_url,
                       v.years_experience, v.hourly_rate_minor, v.currency, v.bio
                FROM `{$vendors}` v
                INNER JOIN `{$products}` p ON p.tenant_id = v.tenant_id AND p.vendor_id = v.vendor_id AND p.status = 'active'";
        $params = [];

        if ($categoryId > 0) {
            $sql .= " INNER JOIN `{$productCategories}` pc ON pc.tenant_id = p.tenant_id AND pc.product_id = p.product_id AND pc.category_id = %d";
            $params[] = $categoryId;
        }

        $sql .= " WHERE v.tenant_id = %d AND v.status = 'approved' ORDER BY v.vendor_id DESC LIMIT %d";
        $params[] = $tenantId;
        $params[] = $limit * 4;

        $providers = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];

        if ($lat !== null && $lng !== null) {
            $providers = $this->applyGeoFilter($tenantId, $providers, $lat, $lng, $radiusKm);
        }

        return \array_slice($providers, 0, $limit);
    }

    /**
     * Filter and annotate a list of rows that have `vendor_id` against
     * each provider's vendor_locations + service_areas. A row passes if
     * either: the provider has zero geo data declared (treated as
     * "unlimited reach") OR at least one of their locations/areas is
     * within radiusKm of the buyer point.
     *
     * Mutates each row with a `distance_km` and `serves_area` flag.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function applyGeoFilter(int $tenantId, array $rows, float $lat, float $lng, float $radiusKm): array
    {
        if ($rows === []) {
            return $rows;
        }

        global $wpdb;
        $locations = $wpdb->prefix . 'mercato_vendor_locations';
        $areas = $wpdb->prefix . 'mercato_service_areas';

        $vendorIds = \array_values(\array_unique(\array_map(static fn (array $r): int => (int) ($r['vendor_id'] ?? 0), $rows)));
        if ($vendorIds === []) {
            return $rows;
        }
        $placeholders = \implode(',', \array_fill(0, \count($vendorIds), '%d'));

        $locRows = $wpdb->get_results($wpdb->prepare(
            "SELECT vendor_id, latitude, longitude, service_radius_km FROM `{$locations}` WHERE tenant_id = %d AND vendor_id IN ({$placeholders})",
            \array_merge([$tenantId], $vendorIds)
        ), ARRAY_A) ?: [];
        $areaRows = $wpdb->get_results($wpdb->prepare(
            "SELECT vendor_id, latitude, longitude, radius_km FROM `{$areas}` WHERE tenant_id = %d AND vendor_id IN ({$placeholders}) AND latitude IS NOT NULL AND longitude IS NOT NULL",
            \array_merge([$tenantId], $vendorIds)
        ), ARRAY_A) ?: [];

        $byVendor = [];
        foreach ($locRows as $r) {
            $vid = (int) $r['vendor_id'];
            $byVendor[$vid][] = [
                'lat' => (float) $r['latitude'],
                'lng' => (float) $r['longitude'],
                'radius_km' => $r['service_radius_km'] === null ? null : (float) $r['service_radius_km'],
            ];
        }
        foreach ($areaRows as $r) {
            $vid = (int) $r['vendor_id'];
            $byVendor[$vid][] = [
                'lat' => (float) $r['latitude'],
                'lng' => (float) $r['longitude'],
                'radius_km' => $r['radius_km'] === null ? null : (float) $r['radius_km'],
            ];
        }

        $kept = [];
        foreach ($rows as $row) {
            $vid = (int) ($row['vendor_id'] ?? 0);
            if (!isset($byVendor[$vid])) {
                $row['distance_km'] = null;
                $row['serves_area'] = false;
                $kept[] = $row;
                continue;
            }

            $minDistance = INF;
            $servesArea = false;
            foreach ($byVendor[$vid] as $pt) {
                $d = Geocoder::distanceKm($lat, $lng, $pt['lat'], $pt['lng']);
                $effectiveRadius = $pt['radius_km'] ?? $radiusKm;
                if ($d <= $effectiveRadius) {
                    $servesArea = true;
                    $minDistance = \min($minDistance, $d);
                } elseif ($d < $minDistance) {
                    $minDistance = $d;
                }
            }

            if ($servesArea || $minDistance <= $radiusKm) {
                $row['distance_km'] = $minDistance === INF ? null : \round($minDistance, 1);
                $row['serves_area'] = $servesArea;
                $kept[] = $row;
            }
        }

        \usort($kept, static function (array $a, array $b): int {
            $da = $a['distance_km'] ?? INF;
            $db = $b['distance_km'] ?? INF;
            return $da <=> $db;
        });

        return $kept;
    }

    /**
     * @return array<string,mixed>
     */
    public function providersPage(int $tenantId, ?float $lat = null, ?float $lng = null, float $radiusKm = 25.0, int $categoryId = 0): array
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $vendors = $prefix . 'mercato_vendors';
        $products = $prefix . 'mercato_products';
        $jobs = $prefix . 'mercato_jobs';
        $categories = $prefix . 'mercato_categories';
        $productCategories = $prefix . 'mercato_product_categories';
        $reviews = $prefix . 'mercato_reviews';

        $sql = "SELECT v.vendor_id, v.business_name, v.store_slug, v.status, v.stripe_account_id,
                       v.headline, v.bio, v.photo_url, v.years_experience, v.hourly_rate_minor,
                       v.currency, v.background_check_status, v.verified_at, v.license_number,
                       v.insurance_amount_minor,
                       (SELECT COUNT(*) FROM `{$products}` p WHERE p.tenant_id = v.tenant_id AND p.vendor_id = v.vendor_id AND p.status = 'active') AS service_count,
                       (SELECT COUNT(*) FROM `{$jobs}` j WHERE j.tenant_id = v.tenant_id AND j.vendor_id = v.vendor_id) AS job_count,
                       (SELECT COALESCE(AVG(rating), 0) FROM `{$reviews}` r WHERE r.tenant_id = v.tenant_id AND r.vendor_id = v.vendor_id AND r.status = 'published') AS avg_rating,
                       (SELECT COUNT(*) FROM `{$reviews}` r WHERE r.tenant_id = v.tenant_id AND r.vendor_id = v.vendor_id AND r.status = 'published') AS review_count
                FROM `{$vendors}` v";
        $params = [];
        if ($categoryId > 0) {
            $sql .= " INNER JOIN `{$products}` p ON p.tenant_id = v.tenant_id AND p.vendor_id = v.vendor_id AND p.status = 'active'
                      INNER JOIN `{$productCategories}` pc ON pc.tenant_id = p.tenant_id AND pc.product_id = p.product_id AND pc.category_id = %d";
            $params[] = $categoryId;
        }
        $sql .= " WHERE v.tenant_id = %d AND v.status = 'approved' GROUP BY v.vendor_id ORDER BY v.verified_at IS NULL, v.business_name ASC LIMIT 120";
        $params[] = $tenantId;

        $providers = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];

        if ($lat !== null && $lng !== null) {
            $providers = $this->applyGeoFilter($tenantId, $providers, $lat, $lng, $radiusKm);
        }

        return [
            'providers' => $providers,
            'categories' => $wpdb->get_results($wpdb->prepare(
                "SELECT category_id, name FROM `{$categories}` WHERE tenant_id = %d AND parent_id IS NULL ORDER BY sort_order ASC, name ASC",
                $tenantId
            ), ARRAY_A) ?: [],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function providerDetail(int $tenantId, string $slug): ?array
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $vendors = $prefix . 'mercato_vendors';
        $products = $prefix . 'mercato_products';
        $jobs = $prefix . 'mercato_jobs';
        $reviews = $prefix . 'mercato_reviews';
        $offerings = $prefix . 'mercato_vendor_service_offerings';
        $serviceAreas = $prefix . 'mercato_service_areas';
        $vendorLocations = $prefix . 'mercato_vendor_locations';
        $portfolio = $prefix . 'mercato_vendor_portfolio';

        $provider = $wpdb->get_row($wpdb->prepare(
            "SELECT vendor_id, business_name, store_slug, status, stripe_account_id,
                    headline, bio, years_experience, hourly_rate_minor, currency,
                    phone, contact_email, photo_url, cover_url, languages,
                    license_number, license_state, insurance_amount_minor, insurance_carrier,
                    background_check_status, verified_at
             FROM `{$vendors}`
             WHERE tenant_id = %d AND store_slug = %s AND status = 'approved'",
            $tenantId,
            $slug
        ), ARRAY_A);
        if (!\is_array($provider) || empty($provider)) {
            return null;
        }
        $vendorId = (int) $provider['vendor_id'];

        $reviewsTableExists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $reviews)) === $reviews;
        $reviewSummary = ['avg_rating' => 0, 'review_count' => 0];
        $recentReviews = [];
        if ($reviewsTableExists) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count FROM `{$reviews}` WHERE tenant_id = %d AND vendor_id = %d AND status = 'published'", $tenantId, $vendorId), ARRAY_A);
            if (\is_array($row)) {
                $reviewSummary = $row;
            }
            $recentReviews = $wpdb->get_results($wpdb->prepare("SELECT review_id, rating, title, body, buyer_user_id, created_at FROM `{$reviews}` WHERE tenant_id = %d AND vendor_id = %d AND status = 'published' ORDER BY created_at DESC LIMIT 5", $tenantId, $vendorId), ARRAY_A) ?: [];
        }

        $services = $wpdb->get_results($wpdb->prepare(
            "SELECT p.product_id, p.title, p.description, p.price_minor, p.stock_quantity,
                    p.listing_type, p.min_rental_window_minutes, p.max_rental_window_minutes,
                    p.deposit_minor, p.replacement_value_minor,
                    o.offering_id, o.pricing_type, o.unit_label, o.summary, o.duration_minutes,
                    o.lead_time_minutes, o.capacity, o.min_charge_minor
             FROM `{$products}` p
             LEFT JOIN `{$offerings}` o ON o.tenant_id = p.tenant_id AND o.product_id = p.product_id AND o.vendor_id = p.vendor_id AND o.status = 'active'
             WHERE p.tenant_id = %d AND p.vendor_id = %d AND p.status = 'active'
             ORDER BY p.created_at DESC LIMIT 60",
            $tenantId,
            $vendorId
        ), ARRAY_A) ?: [];

        $serviceAreasExist = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $serviceAreas)) === $serviceAreas;
        $areas = $serviceAreasExist
            ? ($wpdb->get_results($wpdb->prepare(
                "SELECT area_id, label, city, region, postal_code_prefix, country, latitude, longitude, radius_km FROM `{$serviceAreas}` WHERE tenant_id = %d AND vendor_id = %d ORDER BY area_id ASC",
                $tenantId,
                $vendorId
            ), ARRAY_A) ?: [])
            : [];

        $locationsExist = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $vendorLocations)) === $vendorLocations;
        $locations = $locationsExist
            ? ($wpdb->get_results($wpdb->prepare(
                "SELECT location_id, label, city, region, postal_code, country, latitude, longitude, service_radius_km, is_primary FROM `{$vendorLocations}` WHERE tenant_id = %d AND vendor_id = %d ORDER BY is_primary DESC, location_id ASC",
                $tenantId,
                $vendorId
            ), ARRAY_A) ?: [])
            : [];

        $portfolioExists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $portfolio)) === $portfolio;
        $portfolioRows = $portfolioExists
            ? ($wpdb->get_results($wpdb->prepare(
                "SELECT portfolio_id, caption, photo_url FROM `{$portfolio}` WHERE tenant_id = %d AND vendor_id = %d ORDER BY sort_order ASC, portfolio_id ASC LIMIT 24",
                $tenantId,
                $vendorId
            ), ARRAY_A) ?: [])
            : [];

        return [
            'provider' => $provider,
            'services' => $services,
            'service_areas' => $areas,
            'locations' => $locations,
            'portfolio' => $portfolioRows,
            'recent_jobs' => $wpdb->get_results($wpdb->prepare("SELECT job_id, status, updated_at FROM `{$jobs}` WHERE tenant_id = %d AND vendor_id = %d ORDER BY job_id DESC LIMIT 10", $tenantId, $vendorId), ARRAY_A) ?: [],
            'review_average' => \round((float) $reviewSummary['avg_rating'], 2),
            'review_count' => (int) $reviewSummary['review_count'],
            'reviews' => $recentReviews,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function requestNewPage(int $tenantId): array
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $categories = $prefix . 'mercato_categories';

        return [
            'categories' => $wpdb->get_results($wpdb->prepare("SELECT category_id, name FROM `{$categories}` WHERE tenant_id = %d AND parent_id IS NULL ORDER BY sort_order ASC, name ASC", $tenantId), ARRAY_A) ?: [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function signupPage(int $tenantId): array
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $categories = $prefix . 'mercato_categories';
        $vendors = $prefix . 'mercato_vendors';

        return [
            'categories' => $wpdb->get_results($wpdb->prepare(
                "SELECT c.category_id, c.name, c.slug, c.parent_id, p.name AS parent_name FROM `{$categories}` c LEFT JOIN `{$categories}` p ON p.tenant_id = c.tenant_id AND p.category_id = c.parent_id WHERE c.tenant_id = %d ORDER BY p.sort_order ASC, p.name ASC, c.sort_order ASC, c.name ASC",
                $tenantId
            ), ARRAY_A) ?: [],
            'approved_provider_count' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$vendors}` WHERE tenant_id = %d AND status = 'approved'", $tenantId)),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function accountPage(int $tenantId, int $userId): array
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $suborders = $prefix . 'mercato_suborders';
        $serviceRequests = $prefix . 'mercato_service_requests';

        if ($userId <= 0) {
            return ['user_id' => 0, 'orders' => [], 'requests' => []];
        }

        return [
            'user_id' => $userId,
            'orders' => $wpdb->get_results($wpdb->prepare("SELECT suborder_id, wc_order_id, status, payment_status, total_minor, refunded_minor, tracking_carrier, tracking_number FROM `{$suborders}` WHERE tenant_id = %d AND buyer_user_id = %d ORDER BY suborder_id DESC LIMIT 20", $tenantId, $userId), ARRAY_A) ?: [],
            'requests' => $wpdb->get_results($wpdb->prepare("SELECT request_id, title, city, region, budget_max_minor, currency, bid_mode, status FROM `{$serviceRequests}` WHERE tenant_id = %d AND created_by_user_id = %d ORDER BY request_id DESC LIMIT 20", $tenantId, $userId), ARRAY_A) ?: [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function providerDashboard(int $tenantId, int $userId): array
    {
        if ($userId <= 0) {
            return ['vendor' => null, 'reason' => 'not_signed_in'];
        }

        global $wpdb;
        $prefix = $wpdb->prefix;
        $vendors = $prefix . 'mercato_vendors';
        $products = $prefix . 'mercato_products';
        $jobs = $prefix . 'mercato_jobs';
        $kycCases = $prefix . 'mercato_kyc_cases';
        $payouts = $prefix . 'mercato_payout_batches';
        $reviews = $prefix . 'mercato_reviews';

        $vendor = $wpdb->get_row($wpdb->prepare(
            "SELECT vendor_id, business_name, store_slug, status, stripe_account_id
             FROM `{$vendors}`
             WHERE tenant_id = %d AND owner_user_id = %d
             LIMIT 1",
            $tenantId,
            $userId
        ), ARRAY_A);

        if (!\is_array($vendor) || empty($vendor)) {
            return ['vendor' => null, 'reason' => 'not_a_provider'];
        }

        $vendorId = (int) $vendor['vendor_id'];

        $servicesCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$products}` WHERE tenant_id = %d AND vendor_id = %d AND status = 'active'",
            $tenantId,
            $vendorId
        ));

        $jobsCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$jobs}` WHERE tenant_id = %d AND vendor_id = %d",
            $tenantId,
            $vendorId
        ));

        $recentJobs = $wpdb->get_results($wpdb->prepare(
            "SELECT job_id, status, assigned_user_id, updated_at
             FROM `{$jobs}`
             WHERE tenant_id = %d AND vendor_id = %d
             ORDER BY job_id DESC LIMIT 10",
            $tenantId,
            $vendorId
        ), ARRAY_A) ?: [];

        $kycStatus = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM `{$kycCases}` WHERE tenant_id = %d AND vendor_id = %d ORDER BY case_id DESC LIMIT 1",
            $tenantId,
            $vendorId
        ));

        $latestPayout = $wpdb->get_row($wpdb->prepare(
            "SELECT batch_id, status, total_minor, created_at
             FROM `{$payouts}`
             WHERE tenant_id = %d ORDER BY batch_id DESC LIMIT 1",
            $tenantId
        ), ARRAY_A) ?: [];

        $reviewSummary = ['avg' => 0, 'count' => 0];
        $recentReviews = [];
        $reviewsExists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $reviews)) === $reviews;
        if ($reviewsExists) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count
                 FROM `{$reviews}` WHERE tenant_id = %d AND vendor_id = %d AND status = 'published'",
                $tenantId,
                $vendorId
            ), ARRAY_A);
            if (\is_array($row)) {
                $reviewSummary = [
                    'avg' => \round((float) $row['avg_rating'], 2),
                    'count' => (int) $row['review_count'],
                ];
            }
            $recentReviews = $wpdb->get_results($wpdb->prepare(
                "SELECT review_id, rating, title, body, buyer_user_id, created_at
