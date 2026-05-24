<?php

declare(strict_types=1);

namespace Mercato\Core;

final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->instance(Container::class, $this->container);

        $this->container->bind(Tenant\Resolver::class, fn (): Tenant\Resolver => new Tenant\Resolver());
        $this->container->bind(Tenant\IntegrationSettings::class, fn (): Tenant\IntegrationSettings => new Tenant\IntegrationSettings($this->container->get(Tenant\Resolver::class)));
        $this->container->bind(DB\Migrator::class, fn (): DB\Migrator => new DB\Migrator($this->container));
        $this->container->bind(Events\Outbox::class, fn (): Events\Outbox => new Events\Outbox($this->container->get(Tenant\Resolver::class)));
        $this->container->bind(Idempotency\Store::class, fn (): Idempotency\Store => new Idempotency\Store($this->container->get(Tenant\Resolver::class)));
        $this->container->bind(Audit\Writer::class, fn (): Audit\Writer => new Audit\Writer($this->container->get(Tenant\Resolver::class)));
        $this->container->bind(Observability\Health::class, fn (): Observability\Health => new Observability\Health(
            $this->container->get(Tenant\Resolver::class),
            $this->container->get(DB\Migrator::class),
        ));
        $this->container->bind(RBAC\Engine::class, fn (): RBAC\Engine => new RBAC\Engine($this->container->get(Tenant\Resolver::class)));
        $this->container->bind(WooCommerce\HookAdapter::class, fn (): WooCommerce\HookAdapter => new WooCommerce\HookAdapter($this->container->get(Events\Outbox::class)));
        $this->container->bind(Storefront\Config::class, fn (): Storefront\Config => new Storefront\Config());
        $this->container->bind(Storefront\Repository::class, fn (): Storefront\Repository => new Storefront\Repository());
        $this->container->bind(Storefront\Renderer::class, fn ($c): Storefront\Renderer => new Storefront\Renderer(
            $c->get(Tenant\Resolver::class),
            $c->get(Storefront\Config::class),
            $c->get(Storefront\Repository::class),
        ));
        $this->container->bind(Geo\Provider::class, fn ($c): Geo\Provider => new Geo\Provider(
            $c->get(Tenant\Resolver::class),
        ));
    }

    public function boot(): void
    {
        $this->container->get(WooCommerce\HookAdapter::class)->register();
        $this->container->get(Geo\Provider::class)->boot();

        if (\function_exists('add_action')) {
            \add_action('init', [$this, 'serveMetricsEndpoint']);
            \add_action('admin_menu', [$this, 'registerAdminPages']);
            \add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
            \add_action('rest_api_init', [$this, 'registerHealthRoutes']);
            \add_action('send_headers', [$this, 'sendSecurityHeaders']);
            \add_action('template_redirect', [$this, 'renderDemoStorefront']);
        }
    }

    public function registerHealthRoutes(): void
    {
        \register_rest_route('mercato/v1', '/health/live', [
            'methods' => 'GET',
            'callback' => fn (): \WP_REST_Response => new \WP_REST_Response($this->container->get(Observability\Health::class)->live(), 200),
            'permission_callback' => [Rest\Permissions::class, 'canPublicHealth'],
        ]);

        \register_rest_route('mercato/v1', '/health/readiness', [
            'methods' => 'GET',
            'callback' => [$this, 'readiness'],
            'permission_callback' => [Rest\Permissions::class, 'canManage'],
        ]);

        \register_rest_route('mercato/v1', '/metrics', [
            'methods' => 'GET',
            'callback' => [$this, 'metrics'],
            'permission_callback' => [Rest\Permissions::class, 'canManage'],
        ]);

        \register_rest_route('mercato/v1', '/demo/features', [
            'methods' => 'GET',
            'callback' => [$this, 'demoFeatures'],
            'permission_callback' => [Rest\Permissions::class, 'canRead'],
        ]);
    }

    public function readiness(): \WP_REST_Response
    {
        $health = $this->container->get(Observability\Health::class)->readiness();
        return new \WP_REST_Response($health, $health['status'] === 'ok' ? 200 : 503);
    }

    public function metrics(): \WP_REST_Response
    {
        $response = new \WP_REST_Response($this->container->get(Observability\Health::class)->prometheus(), 200);
        $response->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
        return $response;
    }

    public function demoFeatures(): \WP_REST_Response
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $tenantId = $this->container->get(Tenant\Resolver::class)->currentTenantId();

        $scalar = static fn (string $sql, int $tid): int => (int) $wpdb->get_var($wpdb->prepare($sql, $tid));
        $query = static function (string $sql, int $tid) use ($wpdb): array {
            return $wpdb->get_results($wpdb->prepare($sql, $tid), ARRAY_A) ?: [];
        };

        $features = [
            ['name' => 'Tenant platform', 'status' => 'live', 'evidence' => $this->container->get(Observability\Health::class)->readiness()],
            ['name' => 'Vendors and KYC', 'status' => 'live', 'evidence' => [
                'approved_vendors' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_vendors` WHERE tenant_id = %d AND status = 'approved'", $tenantId),
                'verified_kyc_cases' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_kyc_cases` WHERE tenant_id = %d AND status = 'verified'", $tenantId),
            ]],
            ['name' => 'Catalog and media', 'status' => 'live', 'evidence' => [
                'active_products' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_products` WHERE tenant_id = %d AND status = 'active'", $tenantId),
                'clean_media' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_media` WHERE tenant_id = %d AND scan_status = 'clean'", $tenantId),
            ]],
            ['name' => 'Orders and refunds', 'status' => 'live', 'evidence' => [
                'suborders' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_suborders` WHERE tenant_id = %d", $tenantId),
                'refunds' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_refunds` WHERE tenant_id = %d", $tenantId),
                'shipment_rows' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_order_shipments` s INNER JOIN `{$prefix}mercato_suborders` so ON so.suborder_id = s.suborder_id WHERE so.tenant_id = %d", $tenantId),
            ]],
            ['name' => 'Payouts and ledger', 'status' => 'live', 'evidence' => [
                'payout_batches' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_payout_batches` WHERE tenant_id = %d", $tenantId),
                'stripe_transfers' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_stripe_transfers` WHERE tenant_id = %d", $tenantId),
                'ledger_entries' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_ledger_entries` WHERE tenant_id = %d", $tenantId),
                'reconciliation_runs' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_reconciliation_runs` WHERE tenant_id = %d", $tenantId),
            ]],
            ['name' => 'Notifications and events', 'status' => 'live', 'evidence' => [
                'notification_deliveries' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_notification_deliveries` WHERE tenant_id = %d", $tenantId),
                'outbox_published' => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}mercato_event_outbox` WHERE status = 'published'"),
                'audit_events' => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}mercato_audit_log`"),
            ]],
        ];

        return new \WP_REST_Response([
            'features' => $features,
            'recent_vendors' => $query(
                "SELECT v.vendor_id, v.business_name, v.store_slug, v.status, COALESCE(k.status, 'not_started') AS kyc_status, v.stripe_account_id
                 FROM `{$prefix}mercato_vendors` v
                 LEFT JOIN `{$prefix}mercato_kyc_cases` k ON k.vendor_id = v.vendor_id AND k.tenant_id = v.tenant_id
                 WHERE v.tenant_id = %d
                 ORDER BY v.vendor_id DESC LIMIT 8",
                $tenantId
            ),
            'recent_products' => $query("SELECT product_id, vendor_id, title, sku, price_minor, stock_quantity, status FROM `{$prefix}mercato_products` WHERE tenant_id = %d ORDER BY product_id DESC LIMIT 8", $tenantId),
            'recent_suborders' => $query("SELECT suborder_id, vendor_id, wc_order_id, status, payment_status, total_minor, refunded_minor, tracking_carrier, tracking_number FROM `{$prefix}mercato_suborders` WHERE tenant_id = %d ORDER BY suborder_id DESC LIMIT 8", $tenantId),
            'recent_payouts' => $query(
                "SELECT b.batch_id, b.status, b.total_minor, COUNT(i.payout_item_id) AS item_count, b.created_at
                 FROM `{$prefix}mercato_payout_batches` b
                 LEFT JOIN `{$prefix}mercato_payout_items` i ON i.batch_id = b.batch_id
                 WHERE b.tenant_id = %d
                 GROUP BY b.batch_id, b.status, b.total_minor, b.created_at
                 ORDER BY b.batch_id DESC LIMIT 8",
                $tenantId
            ),
            'recent_reconciliation' => $query("SELECT run_id, status, ledger_minor, provider_minor, drift_minor, created_at FROM `{$prefix}mercato_reconciliation_runs` WHERE tenant_id = %d ORDER BY run_id DESC LIMIT 5", $tenantId),
            'recent_notifications' => $query("SELECT delivery_id, recipient, subject, status, created_at FROM `{$prefix}mercato_notification_deliveries` WHERE tenant_id = %d ORDER BY delivery_id DESC LIMIT 8", $tenantId),
            'recent_audit' => $query("SELECT audit_id, action, entity_type, entity_id, occurred_at AS created_at FROM `{$prefix}mercato_audit_log` WHERE tenant_id = %d ORDER BY audit_id DESC LIMIT 10", $tenantId),
        ], 200);
    }

    public function serveMetricsEndpoint(): void
    {
        $path = (string) \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if ($path !== '/metrics') {
            return;
        }
        if (!$this->metricsAuthorized()) {
            \status_header(403);
            echo "forbidden\n";
            exit;
        }
        \header('Content-Type: text/plain; version=0.0.4; charset=utf-8');
        echo $this->container->get(Observability\Health::class)->prometheus();
        exit;
    }

    private function metricsAuthorized(): bool
    {
        $token = (string) \getenv('MERCATO_METRICS_TOKEN');
        $authorization = $this->requestHeader('authorization');
        if ($token !== '' && \str_starts_with($authorization, 'Bearer ')) {
            return \hash_equals($token, \substr($authorization, 7));
        }
        $testSecret = (string) \getenv('MERCATO_TEST_API_SECRET');
        return $testSecret !== ''
            && !\str_contains($testSecret, 'replace_me')
            && \hash_equals($testSecret, $this->requestHeader('x-mercato-test-secret'));
    }

    private function requestHeader(string $name): string
    {
        $serverKey = 'HTTP_' . \strtoupper(\str_replace('-', '_', $name));
        return isset($_SERVER[$serverKey]) ? (string) $_SERVER[$serverKey] : '';
    }

    public function registerAdminPages(): void
    {
        \add_menu_page('Mercato', 'Mercato', 'manage_options', 'mercato-admin', [$this, 'renderAdminApp'], 'dashicons-store', 56);
        \add_submenu_page('mercato-admin', 'Vendor Console', 'Vendor Console', 'read', 'mercato-vendor', [$this, 'renderVendorApp']);
    }

    public function enqueueAdminAssets(string $hook): void
    {
        if (!\str_contains($hook, 'mercato')) {
            return;
        }
        $baseUrl = \plugin_dir_url(\MERCATO_SUITE_FILE);
        $baseDir = \plugin_dir_path(\MERCATO_SUITE_FILE);
        $css = 'assets/css/mercato-admin.css';
        $js = 'assets/js/mercato-admin.js';
        $cssVersion = \file_exists($baseDir . $css) ? (string) \filemtime($baseDir . $css) : \MERCATO_SUITE_VERSION;
        $jsVersion = \file_exists($baseDir . $js) ? (string) \filemtime($baseDir . $js) : \MERCATO_SUITE_VERSION;
        \wp_enqueue_style('mercato-admin', $baseUrl . $css, [], $cssVersion);
        \wp_enqueue_script('mercato-admin', $baseUrl . $js, [], $jsVersion, true);
        \wp_localize_script('mercato-admin', 'MercatoAdmin', [
            'restBase' => \esc_url_raw(\rest_url('mercato/v1')),
            'nonce' => \wp_create_nonce('wp_rest'),
            'page' => \str_contains($hook, 'mercato-vendor') ? 'vendor' : 'admin',
        ]);
    }

    public function renderAdminApp(): void
    {
        echo '<div id="mercato-admin-root" class="mercato-shell"></div>';
    }

    public function renderVendorApp(): void
    {
        echo '<div id="mercato-vendor-root" class="mercato-shell"></div>';
    }

    public function renderDemoStorefront(): void
    {
        if (\is_admin() || \wp_doing_ajax()) {
            return;
        }

        $path = (string) \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $renderer = $this->container->get(Storefront\Renderer::class);
        $html = $renderer->renderForPath($path);
        if ($html === null) {
            return;
        }

        \status_header(200);
        \nocache_headers();
        echo $html;
        exit;
    }

    public function sendSecurityHeaders(): void
    {
        if (\headers_sent()) {
            return;
        }
        \header("Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
        \header('X-Content-Type-Options: nosniff');
        \header('X-Frame-Options: SAMEORIGIN');
        \header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
