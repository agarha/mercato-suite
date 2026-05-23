<?php

declare(strict_types=1);

namespace Mercato\Core\Storefront;

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
     * Services index page with optional full-text query + category filter.
     *
     * @return array<string,mixed>
     */
    public function servicesPage(int $tenantId, string $query = '', int $categoryId = 0): array
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $products = $prefix . 'mercato_products';
        $vendors = $prefix . 'mercato_vendors';
        $categories = $prefix . 'mercato_categories';
        $productCategories = $prefix . 'mercato_product_categories';

        // Build SQL + params in lockstep so placeholders and values stay aligned.
        $sql = "SELECT p.product_id, p.title, p.description, p.price_minor, p.stock_quantity, v.business_name, v.store_slug
                FROM `{$products}` p
                INNER JOIN `{$vendors}` v ON v.vendor_id = p.vendor_id AND v.tenant_id = p.tenant_id";
        $params = [];

        if ($categoryId > 0) {
            $sql .= " INNER JOIN `{$productCategories}` pc ON pc.product_id = p.product_id AND pc.tenant_id = p.tenant_id AND pc.category_id = %d";
            $params[] = $categoryId;
        }

        $sql .= " WHERE p.tenant_id = %d AND p.status = 'active'";
        $params[] = $tenantId;

        if ($query !== '') {
            $like = '%' . $wpdb->esc_like($query) . '%';
            $sql .= " AND (p.title LIKE %s OR p.description LIKE %s OR v.business_name LIKE %s)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY p.created_at DESC LIMIT 60";

        return [
            'categories' => $wpdb->get_results($wpdb->prepare(
                "SELECT category_id, name FROM `{$categories}` WHERE tenant_id = %d AND parent_id IS NULL ORDER BY sort_order ASC, name ASC",
                $tenantId
            ), ARRAY_A) ?: [],
            'services' => $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function providersPage(int $tenantId): array
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $vendors = $prefix . 'mercato_vendors';
        $products = $prefix . 'mercato_products';
        $jobs = $prefix . 'mercato_jobs';

        return [
            'providers' => $wpdb->get_results($wpdb->prepare("SELECT v.vendor_id, v.business_name, v.store_slug, v.status, v.stripe_account_id, (SELECT COUNT(*) FROM `{$products}` p WHERE p.tenant_id = v.tenant_id AND p.vendor_id = v.vendor_id AND p.status = 'active') AS service_count, (SELECT COUNT(*) FROM `{$jobs}` j WHERE j.tenant_id = v.tenant_id AND j.vendor_id = v.vendor_id) AS job_count FROM `{$vendors}` v WHERE v.tenant_id = %d AND v.status = 'approved' ORDER BY v.business_name ASC LIMIT 60", $tenantId), ARRAY_A) ?: [],
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

        $provider = $wpdb->get_row($wpdb->prepare("SELECT vendor_id, business_name, store_slug, status, stripe_account_id FROM `{$vendors}` WHERE tenant_id = %d AND store_slug = %s AND status = 'approved'", $tenantId, $slug), ARRAY_A);
        if (!\is_array($provider) || empty($provider)) {
            return null;
        }
        $vendorId = (int) $provider['vendor_id'];

        return [
            'provider' => $provider,
            'services' => $wpdb->get_results($wpdb->prepare("SELECT product_id, title, description, price_minor, stock_quantity FROM `{$products}` WHERE tenant_id = %d AND vendor_id = %d AND status = 'active' ORDER BY created_at DESC LIMIT 24", $tenantId, $vendorId), ARRAY_A) ?: [],
            'recent_jobs' => $wpdb->get_results($wpdb->prepare("SELECT job_id, status, updated_at FROM `{$jobs}` WHERE tenant_id = %d AND vendor_id = %d ORDER BY job_id DESC LIMIT 10", $tenantId, $vendorId), ARRAY_A) ?: [],
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
}
