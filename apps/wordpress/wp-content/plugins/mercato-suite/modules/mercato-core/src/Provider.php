<?php

declare(strict_types=1);

namespace Mercato\Core;

final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->instance(Container::class, $this->container);

        $this->container->bind(Tenant\Resolver::class, fn (): Tenant\Resolver => new Tenant\Resolver());
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
    }

    public function boot(): void
    {
        $this->container->get(WooCommerce\HookAdapter::class)->register();

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
        $query = static function (string $sql) use ($wpdb): array {
            return $wpdb->get_results($sql, ARRAY_A) ?: [];
        };
        $scalar = static function (string $sql) use ($wpdb): int {
            return (int) $wpdb->get_var($sql);
        };

        $features = [
            [
                'name' => 'Tenant platform',
                'status' => 'live',
                'evidence' => $this->container->get(Observability\Health::class)->readiness(),
            ],
            [
                'name' => 'Vendors and KYC',
                'status' => 'live',
                'evidence' => [
                    'approved_vendors' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_vendors` WHERE status = 'approved'"),
                    'verified_kyc_cases' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_kyc_cases` WHERE status = 'verified'"),
                ],
            ],
            [
                'name' => 'Catalog and media',
                'status' => 'live',
                'evidence' => [
                    'active_products' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_products` WHERE status = 'active'"),
                    'clean_media' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_media` WHERE scan_status = 'clean'"),
                ],
            ],
            [
                'name' => 'Orders and refunds',
                'status' => 'live',
                'evidence' => [
                    'suborders' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_suborders`"),
                    'refunds' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_refunds`"),
                    'shipment_rows' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_order_shipments`"),
                ],
            ],
            [
                'name' => 'Payouts and ledger',
                'status' => 'live',
                'evidence' => [
                    'payout_batches' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_payout_batches`"),
                    'stripe_transfers' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_stripe_transfers`"),
                    'ledger_entries' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_ledger_entries`"),
                    'reconciliation_runs' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_reconciliation_runs`"),
                ],
            ],
            [
                'name' => 'Notifications and events',
                'status' => 'live',
                'evidence' => [
                    'notification_deliveries' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_notification_deliveries`"),
                    'outbox_published' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_event_outbox` WHERE status = 'published'"),
                    'audit_events' => $scalar("SELECT COUNT(*) FROM `{$prefix}mercato_audit_log`"),
                ],
            ],
        ];

        return new \WP_REST_Response([
            'features' => $features,
            'recent_vendors' => $query("SELECT v.vendor_id, v.business_name, v.store_slug, v.status, COALESCE(k.status, 'not_started') AS kyc_status, v.stripe_account_id
                FROM `{$prefix}mercato_vendors` v
                LEFT JOIN `{$prefix}mercato_kyc_cases` k ON k.vendor_id = v.vendor_id AND k.tenant_id = v.tenant_id
                ORDER BY v.vendor_id DESC LIMIT 8"),
            'recent_products' => $query("SELECT product_id, vendor_id, title, sku, price_minor, stock_quantity, status FROM `{$prefix}mercato_products` ORDER BY product_id DESC LIMIT 8"),
            'recent_suborders' => $query("SELECT suborder_id, vendor_id, wc_order_id, status, payment_status, total_minor, refunded_minor, tracking_carrier, tracking_number FROM `{$prefix}mercato_suborders` ORDER BY suborder_id DESC LIMIT 8"),
            'recent_payouts' => $query("SELECT b.batch_id, b.status, b.total_minor, COUNT(i.payout_item_id) AS item_count, b.created_at
                FROM `{$prefix}mercato_payout_batches` b
                LEFT JOIN `{$prefix}mercato_payout_items` i ON i.batch_id = b.batch_id AND i.tenant_id = b.tenant_id
                GROUP BY b.batch_id, b.status, b.total_minor, b.created_at
                ORDER BY b.batch_id DESC LIMIT 8"),
            'recent_reconciliation' => $query("SELECT run_id, status, ledger_minor, provider_minor, drift_minor, created_at FROM `{$prefix}mercato_reconciliation_runs` ORDER BY run_id DESC LIMIT 5"),
            'recent_notifications' => $query("SELECT delivery_id, recipient, subject, status, created_at FROM `{$prefix}mercato_notification_deliveries` ORDER BY delivery_id DESC LIMIT 8"),
            'recent_audit' => $query("SELECT audit_id, action, entity_type, entity_id, occurred_at AS created_at FROM `{$prefix}mercato_audit_log` ORDER BY audit_id DESC LIMIT 10"),
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
        if (isset($_SERVER[$serverKey])) {
            return (string) $_SERVER[$serverKey];
        }

        return '';
    }

    public function registerAdminPages(): void
    {
        \add_menu_page(
            'Mercato',
            'Mercato',
            'manage_options',
            'mercato-admin',
            [$this, 'renderAdminApp'],
            'dashicons-store',
            56
        );

        \add_submenu_page(
            'mercato-admin',
            'Vendor Console',
            'Vendor Console',
            'read',
            'mercato-vendor',
            [$this, 'renderVendorApp']
        );
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
        if ($path !== '/' && $path !== '') {
            return;
        }

        global $wpdb;
        $productsTable = $wpdb->prefix . 'mercato_products';
        $vendorsTable = $wpdb->prefix . 'mercato_vendors';
        $subordersTable = $wpdb->prefix . 'mercato_suborders';
        $commissionsTable = $wpdb->prefix . 'mercato_commissions';

        $products = $wpdb->get_results(
            "SELECT p.product_id, p.title, p.description, p.price_minor, p.stock_quantity, p.status, v.business_name, v.store_slug
             FROM `{$productsTable}` p
             INNER JOIN `{$vendorsTable}` v ON v.vendor_id = p.vendor_id AND v.tenant_id = p.tenant_id
             WHERE p.status = 'active'
             ORDER BY p.created_at DESC
             LIMIT 8",
            ARRAY_A
        ) ?: [];

        $vendorCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$vendorsTable}` WHERE status = 'approved'");
        $productCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$productsTable}` WHERE status = 'active'");
        $suborderCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$subordersTable}`");
        $takeRateMinor = (int) $wpdb->get_var("SELECT COALESCE(SUM(platform_fee_minor), 0) FROM `{$commissionsTable}`");

        \status_header(200);
        \nocache_headers();
        echo $this->storefrontHtml($products, $vendorCount, $productCount, $suborderCount, $takeRateMinor);
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

    /**
     * @param list<array<string,mixed>> $products
     */
    private function storefrontHtml(array $products, int $vendorCount, int $productCount, int $suborderCount, int $takeRateMinor): string
    {
        $esc = static fn (mixed $value): string => \esc_html((string) $value);
        $money = static fn (mixed $minor): string => '$' . \number_format(((int) $minor) / 100, 2);
        $cards = '';
        foreach ($products as $index => $product) {
            $tone = ['market-blue', 'market-green', 'market-red', 'market-gold'][$index % 4];
            $cards .= '<article class="product-card">
                <div class="product-media ' . $tone . '"><span>' . $esc(\mb_substr((string) $product['title'], 0, 1)) . '</span></div>
                <div class="product-body">
                    <p class="vendor-name">' . $esc($product['business_name']) . '</p>
                    <h3>' . $esc($product['title']) . '</h3>
                    <p>' . $esc($product['description'] ?: 'Curated marketplace product ready for vendor fulfillment.') . '</p>
                    <div class="product-meta"><strong>' . $money($product['price_minor']) . '</strong><span>' . $esc($product['stock_quantity']) . ' in stock</span></div>
                </div>
            </article>';
        }

        if ($cards === '') {
            $cards = '<article class="empty-state"><h3>No active demo products yet</h3><p>Run <code>tools\\seed-demo-data.ps1</code> to populate realistic vendors and products.</p></article>';
        }

        return '<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mercato Marketplace Demo</title>
  <style>
    :root{--ink:#17202a;--muted:#5f6f7f;--line:#d9e2ec;--panel:#fff;--wash:#f6f8fb;--blue:#155e75;--green:#286140;--red:#8f3d3d;--gold:#8a5a18}
    *{box-sizing:border-box}body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--ink);background:var(--wash)}a{color:inherit}
    .topbar{height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 40px;background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:3}
    .brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:18px}.mark{width:32px;height:32px;border-radius:8px;background:#17202a;color:#fff;display:grid;place-items:center}.nav{display:flex;gap:18px;color:var(--muted);font-size:13px}.nav a{text-decoration:none}
    .hero{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(360px,.95fr);gap:34px;align-items:center;padding:56px 40px 36px;max-width:1260px;margin:0 auto}
    .hero h1{font-size:48px;line-height:1.03;margin:0 0 16px;letter-spacing:0}.hero p{font-size:17px;line-height:1.6;color:var(--muted);max-width:680px;margin:0}
    .hero-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}.button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;border-radius:8px;padding:0 16px;text-decoration:none;font-weight:700;border:1px solid #17202a;background:#17202a;color:#fff}.button.secondary{background:#fff;color:#17202a}
    .demo-board{background:#fff;border:1px solid var(--line);border-radius:8px;padding:16px;box-shadow:0 20px 50px rgba(15,23,42,.08)}.board-row{display:grid;grid-template-columns:1fr auto;gap:12px;padding:12px;border-bottom:1px solid #eef2f6}.board-row:last-child{border-bottom:0}.board-row span{color:var(--muted);font-size:13px}.board-row strong{font-size:22px}
    .section{max-width:1260px;margin:0 auto;padding:24px 40px}.section-head{display:flex;align-items:end;justify-content:space-between;gap:18px;margin-bottom:16px}.section h2{font-size:24px;margin:0}.section p{color:var(--muted);margin:6px 0 0}
    .product-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.product-card{background:#fff;border:1px solid var(--line);border-radius:8px;overflow:hidden}.product-media{height:132px;display:grid;place-items:center;color:#fff}.product-media span{font-size:42px;font-weight:800}.market-blue{background:#155e75}.market-green{background:#286140}.market-red{background:#8f3d3d}.market-gold{background:#8a5a18}
    .product-body{padding:14px}.vendor-name{text-transform:uppercase;font-size:11px;letter-spacing:.04em;color:var(--muted);margin:0 0 6px}.product-body h3{font-size:16px;margin:0 0 7px}.product-body p{font-size:13px;line-height:1.45}.product-meta{display:flex;align-items:center;justify-content:space-between;margin-top:12px}.product-meta span{font-size:12px;color:var(--muted)}
    .workflow{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.step{background:#fff;border:1px solid var(--line);border-radius:8px;padding:16px}.step b{display:block;font-size:13px;color:var(--muted);margin-bottom:8px}.step strong{display:block;font-size:17px;margin-bottom:6px}.step p{font-size:13px;line-height:1.45}
    .empty-state{grid-column:1/-1;background:#fff;border:1px solid var(--line);border-radius:8px;padding:24px}.footer{padding:30px 40px;color:var(--muted);font-size:13px;text-align:center}
    @media(max-width:960px){.hero{grid-template-columns:1fr}.product-grid,.workflow{grid-template-columns:repeat(2,minmax(0,1fr))}.nav{display:none}}@media(max-width:620px){.topbar,.hero,.section{padding-left:18px;padding-right:18px}.hero h1{font-size:34px}.product-grid,.workflow{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <header class="topbar"><div class="brand"><div class="mark">M</div>Mercato</div><nav class="nav"><a href="/wp-admin/admin.php?page=mercato-admin">Admin</a><a href="/wp-admin/admin.php?page=mercato-vendor">Vendor Console</a><a href="/wp-admin/plugins.php">Plugin</a></nav></header>
  <main>
    <section class="hero">
      <div><h1>Multi-vendor marketplace operations, packaged for tenants.</h1><p>Mercato gives each tenant a managed marketplace with vendor onboarding, catalog publishing, multi-vendor order splitting, commissions, payouts, reconciliation, notifications, and media storage.</p><div class="hero-actions"><a class="button" href="/wp-admin/admin.php?page=mercato-admin">Open admin console</a><a class="button secondary" href="/wp-admin/admin.php?page=mercato-vendor">Open vendor console</a></div></div>
      <aside class="demo-board"><div class="board-row"><span>Approved vendors</span><strong>' . $vendorCount . '</strong></div><div class="board-row"><span>Active products</span><strong>' . $productCount . '</strong></div><div class="board-row"><span>Suborders processed</span><strong>' . $suborderCount . '</strong></div><div class="board-row"><span>Platform fees tracked</span><strong>' . $money($takeRateMinor) . '</strong></div></aside>
    </section>
    <section class="section"><div class="section-head"><div><h2>Live demo catalog</h2><p>Products below are loaded from the Mercato plugin tables inside this Docker container.</p></div></div><div class="product-grid">' . $cards . '</div></section>
    <section class="section"><div class="section-head"><div><h2>Marketplace workflow</h2><p>The local E2E path validates the operational flow behind this storefront.</p></div></div><div class="workflow"><div class="step"><b>01</b><strong>Onboard vendors</strong><p>Register, review, approve, reject, suspend, and track KYC/payout readiness.</p></div><div class="step"><b>02</b><strong>Publish catalog</strong><p>Products are owned by vendors and projected into WooCommerce for checkout.</p></div><div class="step"><b>03</b><strong>Split orders</strong><p>Parent Woo orders become vendor suborders with tax, shipping, discount, and tracking allocation.</p></div><div class="step"><b>04</b><strong>Pay and reconcile</strong><p>Stripe Connect payouts, commission reversals, reports, and trial balance evidence stay linked.</p></div></div></section>
  </main>
  <footer class="footer">Local Mercato Docker demo at http://localhost:8092</footer>
</body>
</html>';
    }
}
