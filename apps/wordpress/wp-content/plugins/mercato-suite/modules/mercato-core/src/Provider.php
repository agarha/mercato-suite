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
        if ($path !== '/' && $path !== '' && \preg_match('#^/t/[a-z0-9][a-z0-9-]{0,63}/?$#i', $path) !== 1) {
            return;
        }

        global $wpdb;
        $productsTable = $wpdb->prefix . 'mercato_products';
        $vendorsTable = $wpdb->prefix . 'mercato_vendors';
        $subordersTable = $wpdb->prefix . 'mercato_suborders';
        $commissionsTable = $wpdb->prefix . 'mercato_commissions';
        $tenantId = $this->container->get(Tenant\Resolver::class)->currentTenantId();

        $products = $wpdb->get_results(
            $wpdb->prepare("SELECT p.product_id, p.title, p.description, p.price_minor, p.stock_quantity, p.status, v.business_name, v.store_slug
             FROM `{$productsTable}` p
             INNER JOIN `{$vendorsTable}` v ON v.vendor_id = p.vendor_id AND v.tenant_id = p.tenant_id
             WHERE p.tenant_id = %d AND p.status = 'active'
             ORDER BY p.created_at DESC
             LIMIT 8", $tenantId),
            ARRAY_A
        ) ?: [];

        $vendorCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$vendorsTable}` WHERE tenant_id = %d AND status = 'approved'", $tenantId));
        $productCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$productsTable}` WHERE tenant_id = %d AND status = 'active'", $tenantId));
        $suborderCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$subordersTable}` WHERE tenant_id = %d", $tenantId));
        $takeRateMinor = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(platform_fee_minor), 0) FROM `{$commissionsTable}` WHERE tenant_id = %d", $tenantId));

        \status_header(200);
        \nocache_headers();
        echo $this->storefrontHtml($products, $vendorCount, $productCount, $suborderCount, $takeRateMinor, $this->storefrontConfig($tenantId));
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
     * @return array<string,mixed>
     */
    private function storefrontConfig(int $tenantId): array
    {
        global $wpdb;

        $config = $this->defaultStorefrontConfig();
        $config['tenant_id'] = $tenantId;
        $table = $wpdb->prefix . 'mercato_tenant_settings';
        $settingsJson = $wpdb->get_var($wpdb->prepare("SELECT `settings` FROM `{$table}` WHERE `tenant_id` = %d", $tenantId));
        if (\is_string($settingsJson) && $settingsJson !== '') {
            $settings = \json_decode($settingsJson, true);
            if (\is_array($settings) && isset($settings['storefront']) && \is_array($settings['storefront'])) {
                $config = $this->mergeStorefrontConfig($config, $settings['storefront']);
                $config['tenant_id'] = $tenantId;
            }
        }

        return $config;
    }

    /**
     * @return array<string,mixed>
     */
    private function defaultStorefrontConfig(): array
    {
        return [
            'tenant_id' => 1,
            'brand' => 'Mercato',
            'mark' => 'M',
            'title' => 'Mercato Marketplace Demo',
            'hero_headline' => 'Multi-vendor marketplace operations, packaged for tenants.',
            'hero_copy' => 'Mercato gives each tenant a managed marketplace with vendor onboarding, catalog publishing, multi-vendor order splitting, commissions, payouts, reconciliation, notifications, and media storage.',
            'primary_cta' => 'Open admin console',
            'secondary_cta' => 'Open vendor console',
            'positioning_headline' => 'Why this stands out',
            'positioning_copy' => 'Mercato is positioned as a hosted marketplace operating system, not only a single-site vendor plugin.',
            'catalog_headline' => 'Buyer marketplace',
            'catalog_copy' => 'A real storefront-style catalog loaded from vendor-owned Mercato product records.',
            'catalog_badge' => 'Multi-vendor catalog',
            'vendor_headline' => 'Vendor directory',
            'vendor_copy' => 'Approved sellers with payout onboarding and tenant-scoped store identity.',
            'vendor_badge' => 'KYC + Stripe Connect',
            'buyer_headline' => 'Buyer checkout and account',
            'buyer_copy' => 'Demo of what a buyer sees: cart economics, multi-vendor fulfillment, refund state, and tracking.',
            'seller_headline' => 'Seller portal experience',
            'seller_copy' => 'Public preview of the vendor workflow backed by the same admin/vendor APIs.',
            'workflow_headline' => 'Marketplace workflow',
            'workflow_copy' => 'The local E2E path validates the operational flow behind this storefront.',
            'footer' => 'Local Mercato Docker demo',
            'item_empty_title' => 'No active demo products yet',
            'item_empty_copy' => 'Run tools\\seed-demo-data.ps1 to populate realistic vendors and products.',
            'item_fallback_copy' => 'Curated marketplace product ready for vendor fulfillment.',
            'item_quantity_label' => 'in stock',
            'vendor_status_label' => 'Stripe connected',
            'nav' => [
                ['href' => '#categories', 'label' => 'Categories'],
                ['href' => '#shop', 'label' => 'Shop'],
                ['href' => '#vendors', 'label' => 'Vendors'],
                ['href' => '#buyer', 'label' => 'Buyer Account'],
                ['href' => '#operations', 'label' => 'Operations'],
                ['href' => '#seller', 'label' => 'Seller Portal'],
                ['href' => '/wp-admin/admin.php?page=mercato-admin', 'label' => 'Admin'],
            ],
            'metric_labels' => [
                'vendors' => 'Approved vendors',
                'products' => 'Active products',
                'orders' => 'Suborders processed',
                'take' => 'Platform fees tracked',
            ],
            'positioning_cards' => [
                ['eyebrow' => '01', 'title' => 'Multi-tenant by design', 'copy' => 'One platform can host many tenant marketplaces with tenant-aware data, audit, metrics, and controls.'],
                ['eyebrow' => '02', 'title' => 'Finance-grade operations', 'copy' => 'Stripe Connect, commissions, payout batches, refund reversals, reconciliation, and trial balance evidence.'],
                ['eyebrow' => '03', 'title' => 'Vendor + buyer workflows', 'copy' => 'Vendor onboarding, catalog, media, suborders, tracking, notifications, and buyer account visibility.'],
                ['eyebrow' => '04', 'title' => 'Portable hosting path', 'copy' => 'Start on Hetzner with Docker, then move to AWS/Kubernetes when sales justify enterprise scale.'],
            ],
            'seller_steps' => [
                ['eyebrow' => 'Apply', 'title' => 'Storefront onboarding', 'copy' => 'Business profile, return policy, KYC, payout account, and tenant approval.'],
                ['eyebrow' => 'Sell', 'title' => 'Catalog workspace', 'copy' => 'Create products, SKU, price, stock, media upload, and WooCommerce projection.'],
                ['eyebrow' => 'Fulfill', 'title' => 'Suborders and tracking', 'copy' => 'Vendors see only their own suborders, update shipment status, and track refunds.'],
                ['eyebrow' => 'Get paid', 'title' => '__PAYOUT_SUMMARY__', 'copy' => 'Commission, ledger, payout batch, and Stripe transfer evidence are linked.'],
                ['eyebrow' => 'Message', 'title' => '__NOTIFICATION_SUMMARY__', 'copy' => 'Notifications are delivered through the local mail/event pipeline.'],
                ['eyebrow' => 'Operate', 'title' => 'Reports and audit', 'copy' => 'Tenant dashboards, reconciliation, audit log, and outbox health are visible in admin.'],
            ],
            'workflow_steps' => [
                ['eyebrow' => '01', 'title' => 'Onboard vendors', 'copy' => 'Register, review, approve, reject, suspend, and track KYC/payout readiness.'],
                ['eyebrow' => '02', 'title' => 'Publish catalog', 'copy' => 'Products are owned by vendors and projected into WooCommerce for checkout.'],
                ['eyebrow' => '03', 'title' => 'Split orders', 'copy' => 'Parent Woo orders become vendor suborders with tax, shipping, discount, and tracking allocation.'],
                ['eyebrow' => '04', 'title' => 'Pay and reconcile', 'copy' => 'Stripe Connect payouts, commission reversals, reports, and trial balance evidence stay linked.'],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    private function mergeStorefrontConfig(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (\is_array($value) && isset($base[$key]) && \is_array($base[$key])) {
                $base[$key] = $this->mergeStorefrontConfig($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param list<array<string,mixed>> $products
     * @param array<string,mixed> $config
     */
    private function storefrontHtml(array $products, int $vendorCount, int $productCount, int $suborderCount, int $takeRateMinor, array $config): string
    {
        $esc = static fn (mixed $value): string => \esc_html((string) $value);
        $money = static fn (mixed $minor): string => '$' . \number_format(((int) $minor) / 100, 2);
        $tenantId = (int) ($config['tenant_id'] ?? 1);
        $nav = $config['nav'];
        $metricLabels = $config['metric_labels'];
        $navHtml = '';
        foreach (\is_array($nav) ? $nav : [] as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $navHtml .= '<a href="' . \esc_attr((string) ($item['href'] ?? '#')) . '">' . $esc($item['label'] ?? '') . '</a>';
        }

        $positioningHtml = '';
        foreach (\is_array($config['positioning_cards'] ?? null) ? $config['positioning_cards'] : [] as $card) {
            if (!\is_array($card)) {
                continue;
            }
            $positioningHtml .= '<div class="positioning-card"><b>' . $esc($card['eyebrow'] ?? '') . '</b><strong>' . $esc($card['title'] ?? '') . '</strong><p>' . $esc($card['copy'] ?? '') . '</p></div>';
        }

        $cards = '';
        foreach ($products as $index => $product) {
            $tone = ['market-blue', 'market-green', 'market-red', 'market-gold'][$index % 4];
            $cards .= '<article class="product-card">
                <div class="product-media ' . $tone . '"><span>' . $esc(\mb_substr((string) $product['title'], 0, 1)) . '</span><small>Verified local service</small></div>
                <div class="product-body">
                    <p class="vendor-name">' . $esc($product['business_name']) . '</p>
                    <h3>' . $esc($product['title']) . '</h3>
                    <p>' . $esc($product['description'] ?: $config['item_fallback_copy']) . '</p>
                    <div class="service-tags"><span>Insured</span><span>Fast response</span><span>Local</span></div>
                    <div class="product-meta"><strong>' . $money($product['price_minor']) . '</strong><span>' . $esc($product['stock_quantity']) . ' ' . $esc($config['item_quantity_label']) . '</span></div>
                </div>
            </article>';
        }

        if ($cards === '') {
            $cards = '<article class="empty-state"><h3>' . $esc($config['item_empty_title']) . '</h3><p>' . $esc($config['item_empty_copy']) . '</p></article>';
        }

        global $wpdb;
        $vendorsTable = $wpdb->prefix . 'mercato_vendors';
        $subordersTable = $wpdb->prefix . 'mercato_suborders';
        $payoutsTable = $wpdb->prefix . 'mercato_payout_batches';
        $notificationsTable = $wpdb->prefix . 'mercato_notification_deliveries';
        $categoriesTable = $wpdb->prefix . 'mercato_categories';
        $jobsTable = $wpdb->prefix . 'mercato_jobs';
        $bookingTable = $wpdb->prefix . 'mercato_booking_requests';
        $estimatesTable = $wpdb->prefix . 'mercato_estimates';
        $referralsTable = $wpdb->prefix . 'mercato_referrals';
        $vendors = $wpdb->get_results($wpdb->prepare("SELECT vendor_id, business_name, store_slug, status, stripe_account_id FROM `{$vendorsTable}` WHERE tenant_id = %d AND status = 'approved' ORDER BY vendor_id DESC LIMIT 6", $tenantId), ARRAY_A) ?: [];
        $orders = $wpdb->get_results($wpdb->prepare("SELECT suborder_id, vendor_id, wc_order_id, status, payment_status, total_minor, refunded_minor, tracking_carrier, tracking_number FROM `{$subordersTable}` WHERE tenant_id = %d ORDER BY suborder_id DESC LIMIT 5", $tenantId), ARRAY_A) ?: [];
        $latestPayout = $wpdb->get_row($wpdb->prepare("SELECT batch_id, status, total_minor, created_at FROM `{$payoutsTable}` WHERE tenant_id = %d ORDER BY batch_id DESC LIMIT 1", $tenantId), ARRAY_A) ?: [];
        $latestNotification = $wpdb->get_row($wpdb->prepare("SELECT delivery_id, recipient, subject, status FROM `{$notificationsTable}` WHERE tenant_id = %d ORDER BY delivery_id DESC LIMIT 1", $tenantId), ARRAY_A) ?: [];
        $categoryRows = $wpdb->get_results($wpdb->prepare("SELECT p.category_id, p.name, COUNT(c.category_id) AS child_count
            FROM `{$categoriesTable}` p
            LEFT JOIN `{$categoriesTable}` c ON c.tenant_id = p.tenant_id AND c.parent_id = p.category_id
            WHERE p.tenant_id = %d AND p.parent_id IS NULL
            GROUP BY p.category_id, p.name, p.sort_order
            ORDER BY p.sort_order ASC, p.name ASC
            LIMIT 16", $tenantId), ARRAY_A) ?: [];
        $subcategoryRows = $wpdb->get_results($wpdb->prepare("SELECT c.name, p.name AS parent_name
            FROM `{$categoriesTable}` c
            INNER JOIN `{$categoriesTable}` p ON p.tenant_id = c.tenant_id AND p.category_id = c.parent_id
            WHERE c.tenant_id = %d
            ORDER BY p.sort_order ASC, c.sort_order ASC, c.name ASC
            LIMIT 42", $tenantId), ARRAY_A) ?: [];
        $jobRows = $wpdb->get_results($wpdb->prepare("SELECT j.job_id, j.status, j.assigned_user_id, j.updated_at, v.business_name
            FROM `{$jobsTable}` j
            LEFT JOIN `{$vendorsTable}` v ON v.tenant_id = j.tenant_id AND v.vendor_id = j.vendor_id
            WHERE j.tenant_id = %d
            ORDER BY j.job_id DESC
            LIMIT 5", $tenantId), ARRAY_A) ?: [];
        $bookingCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$bookingTable}` WHERE tenant_id = %d", $tenantId));
        $jobCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$jobsTable}` WHERE tenant_id = %d", $tenantId));
        $estimateCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$estimatesTable}` WHERE tenant_id = %d", $tenantId));
        $referralCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$referralsTable}` WHERE tenant_id = %d", $tenantId));

        $vendorCards = '';
        foreach ($vendors as $vendor) {
            $vendorCards .= '<article class="vendor-card"><div class="vendor-avatar">' . $esc(\mb_substr((string) $vendor['business_name'], 0, 1)) . '</div><div><h3>' . $esc($vendor['business_name']) . '</h3><p>@' . $esc($vendor['store_slug']) . '</p><span>' . $esc($vendor['status']) . ' / ' . $esc($config['vendor_status_label']) . '</span></div></article>';
        }

        $categoryCards = '';
        foreach ($categoryRows as $category) {
            $categoryCards .= '<article class="category-card"><strong>' . $esc($category['name']) . '</strong><span>' . $esc($category['child_count']) . ' subcategories</span></article>';
        }
        if ($categoryCards === '') {
            $categoryCards = '<article class="empty-state"><h3>No categories yet</h3><p>Seed tenant categories to show marketplace browse structure.</p></article>';
        }

        $subcategoryPills = '';
        foreach ($subcategoryRows as $subcategory) {
            $subcategoryPills .= '<span title="' . \esc_attr((string) $subcategory['parent_name']) . '">' . $esc($subcategory['name']) . '</span>';
        }
        if ($subcategoryPills === '') {
            $subcategoryPills = '<span>No subcategories yet</span>';
        }

        $orderRows = '';
        foreach ($orders as $order) {
            $orderRows .= '<tr><td>#' . $esc($order['wc_order_id']) . '</td><td>' . $esc($order['status']) . '</td><td>' . $esc($order['payment_status']) . '</td><td>' . $money($order['total_minor']) . '</td><td>' . $money($order['refunded_minor']) . '</td><td>' . $esc($order['tracking_carrier']) . ' ' . $esc($order['tracking_number']) . '</td></tr>';
        }
        if ($orderRows === '') {
            $orderRows = '<tr><td colspan="6">No order records yet.</td></tr>';
        }

        $jobRowsHtml = '';
        foreach ($jobRows as $job) {
            $assignee = (int) ($job['assigned_user_id'] ?? 0);
            $jobRowsHtml .= '<tr><td>#' . $esc($job['job_id']) . '</td><td>' . $esc($job['business_name'] ?: 'Provider') . '</td><td>' . $esc($job['status']) . '</td><td>' . ($assignee > 0 ? $esc($assignee) : 'Unassigned') . '</td><td>' . $esc($job['updated_at']) . '</td></tr>';
        }
        if ($jobRowsHtml === '') {
            $jobRowsHtml = '<tr><td colspan="5">No service jobs yet.</td></tr>';
        }

        $payoutSummary = $latestPayout === []
            ? 'No payout batch yet'
            : 'Batch #' . $esc($latestPayout['batch_id']) . ' is ' . $esc($latestPayout['status']) . ' for ' . $money($latestPayout['total_minor']);
        $notificationSummary = $latestNotification === []
            ? 'No notification yet'
            : 'Delivery #' . $esc($latestNotification['delivery_id']) . ' sent to ' . $esc($latestNotification['recipient']);

        $sellerSteps = '';
        foreach (\is_array($config['seller_steps'] ?? null) ? $config['seller_steps'] : [] as $step) {
            if (!\is_array($step)) {
                continue;
            }
            $title = (string) ($step['title'] ?? '');
            if ($title === '__PAYOUT_SUMMARY__') {
                $title = $payoutSummary;
            } elseif ($title === '__NOTIFICATION_SUMMARY__') {
                $title = $notificationSummary;
            }
            $sellerSteps .= '<div class="step"><b>' . $esc($step['eyebrow'] ?? '') . '</b><strong>' . $esc($title) . '</strong><p>' . $esc($step['copy'] ?? '') . '</p></div>';
        }

        $workflowSteps = '';
        foreach (\is_array($config['workflow_steps'] ?? null) ? $config['workflow_steps'] : [] as $step) {
            if (!\is_array($step)) {
                continue;
            }
            $workflowSteps .= '<div class="step"><b>' . $esc($step['eyebrow'] ?? '') . '</b><strong>' . $esc($step['title'] ?? '') . '</strong><p>' . $esc($step['copy'] ?? '') . '</p></div>';
        }

        return '<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>' . $esc($config['title']) . '</title>
  <style>
    :root{--ink:#10232f;--muted:#5b6b73;--line:#dbe7e5;--panel:#fff;--wash:#f3f7f6;--teal:#0f766e;--mint:#d9f99d;--sky:#e0f2fe;--amber:#f7c948;--coral:#f9735b;--forest:#164e45}
    *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--ink);background:var(--wash)}a{color:inherit}
    .topbar{height:72px;display:flex;align-items:center;justify-content:space-between;padding:0 44px;background:rgba(255,255,255,.92);border-bottom:1px solid rgba(16,35,47,.08);position:sticky;top:0;z-index:3;backdrop-filter:blur(14px)}
    .brand{display:flex;align-items:center;gap:12px;font-weight:900;font-size:20px}.mark{width:38px;height:38px;border-radius:8px;background:var(--forest);color:#fff;display:grid;place-items:center;box-shadow:0 10px 22px rgba(15,118,110,.24)}.nav{display:flex;gap:22px;color:#40525b;font-size:14px}.nav a{text-decoration:none;font-weight:650}.nav a:hover{color:var(--teal)}
    .hero-wrap{background:linear-gradient(180deg,#eefaf5 0%,#f8fbfa 68%,var(--wash) 100%);border-bottom:1px solid rgba(16,35,47,.06)}
    .hero{display:grid;grid-template-columns:minmax(0,1.02fr) minmax(380px,.98fr);gap:42px;align-items:center;padding:76px 44px 34px;max-width:1280px;margin:0 auto}
    .eyebrow{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(15,118,110,.22);background:#fff;border-radius:999px;padding:7px 11px;color:#165c55;font-size:13px;font-weight:750;margin-bottom:18px}.eyebrow:before{content:"";width:8px;height:8px;border-radius:999px;background:var(--teal)}
    .hero h1{font-size:64px;line-height:1;margin:0 0 18px;letter-spacing:0;max-width:760px}.hero p{font-size:19px;line-height:1.65;color:#4b6069;max-width:720px;margin:0}
    .hero-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:28px}.button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;border-radius:8px;padding:0 18px;text-decoration:none;font-weight:800;border:1px solid var(--forest);background:var(--forest);color:#fff;box-shadow:0 16px 28px rgba(22,78,69,.18)}.button.secondary{background:#fff;color:var(--forest);box-shadow:none}.button:hover{transform:translateY(-1px)}
    .hero-media{display:grid;gap:14px}.booking-panel{background:#fff;border:1px solid rgba(16,35,47,.08);border-radius:8px;padding:18px;box-shadow:0 28px 70px rgba(16,35,47,.12)}.booking-panel h3{margin:0 0 14px;font-size:18px}.search-row{display:grid;grid-template-columns:1fr 1fr auto;gap:10px}.field{border:1px solid var(--line);border-radius:8px;padding:12px;background:#f8fbfa}.field span{display:block;color:var(--muted);font-size:11px;font-weight:750;text-transform:uppercase}.field strong{display:block;margin-top:4px;font-size:14px}.search-btn{border:0;border-radius:8px;background:var(--teal);color:#fff;font-weight:900;padding:0 18px}
    .photo-board{display:grid;grid-template-columns:1fr 1fr;gap:14px}.photo-card{min-height:190px;border-radius:8px;padding:18px;display:flex;flex-direction:column;justify-content:space-between;color:#fff;background-size:cover;background-position:center;box-shadow:0 22px 52px rgba(16,35,47,.12)}.photo-card strong{font-size:21px;max-width:220px}.photo-card span{font-size:13px;opacity:.9}.photo-a{background:linear-gradient(135deg,rgba(15,118,110,.92),rgba(15,118,110,.3)),url("https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=900&q=80")}.photo-b{background:linear-gradient(135deg,rgba(16,35,47,.88),rgba(249,115,91,.28)),url("https://images.unsplash.com/photo-1621905252507-b35492cc74b4?auto=format&fit=crop&w=900&q=80")}
    .demo-board{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;max-width:1280px;margin:0 auto;padding:0 44px 44px}.board-row{background:#fff;border:1px solid rgba(16,35,47,.08);border-radius:8px;padding:16px}.board-row span{color:var(--muted);font-size:13px}.board-row strong{display:block;font-size:28px;margin-top:4px}
    .section{max-width:1280px;margin:0 auto;padding:46px 44px}.section-head{display:flex;align-items:end;justify-content:space-between;gap:22px;margin-bottom:22px}.section h2{font-size:34px;line-height:1.1;margin:0}.section p{color:var(--muted);margin:8px 0 0;line-height:1.55}
    .product-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.product-card{background:#fff;border:1px solid rgba(16,35,47,.08);border-radius:8px;overflow:hidden;box-shadow:0 16px 36px rgba(16,35,47,.06)}.product-media{height:156px;display:flex;align-items:flex-end;justify-content:space-between;padding:16px;color:#fff}.product-media span{font-size:46px;font-weight:900}.product-media small{font-weight:750;background:rgba(255,255,255,.22);border:1px solid rgba(255,255,255,.32);border-radius:999px;padding:5px 9px}.market-blue{background:linear-gradient(135deg,#0e7490,#164e63)}.market-green{background:linear-gradient(135deg,#15803d,#14532d)}.market-red{background:linear-gradient(135deg,#dc2626,#7f1d1d)}.market-gold{background:linear-gradient(135deg,#b45309,#78350f)}
    .category-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.category-card{background:#fff;border:1px solid rgba(16,35,47,.08);border-radius:8px;padding:16px;min-height:92px;display:flex;flex-direction:column;justify-content:space-between}.category-card strong{font-size:17px}.category-card span{color:var(--muted);font-size:13px}.subcategory-cloud{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}.subcategory-cloud span{border:1px solid #cfe2df;background:#fff;border-radius:999px;padding:7px 10px;font-size:12px;color:#315b54;font-weight:750}.ops-grid{display:grid;grid-template-columns:.7fr 1.3fr;gap:18px}.ops-score{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.ops-score div{background:#fff;border:1px solid rgba(16,35,47,.08);border-radius:8px;padding:16px}.ops-score span{display:block;color:var(--muted);font-size:12px}.ops-score strong{display:block;font-size:28px;margin-top:4px}
    .product-body{padding:18px}.vendor-name{text-transform:uppercase;font-size:11px;letter-spacing:.04em;color:var(--teal);font-weight:850;margin:0 0 8px}.product-body h3{font-size:19px;margin:0 0 8px}.product-body p{font-size:14px;line-height:1.5}.service-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:14px}.service-tags span{font-size:11px;border:1px solid #cfe2df;background:#f2faf8;border-radius:999px;padding:4px 8px;color:#315b54}.product-meta{display:flex;align-items:center;justify-content:space-between;margin-top:16px}.product-meta strong{font-size:19px}.product-meta span{font-size:12px;color:var(--muted)}
    .workflow,.positioning{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.step,.positioning-card{background:#fff;border:1px solid rgba(16,35,47,.08);border-radius:8px;padding:18px;min-height:150px}.step b,.positioning-card b{display:block;font-size:12px;color:var(--teal);margin-bottom:10px;text-transform:uppercase}.step strong,.positioning-card strong{display:block;font-size:18px;margin-bottom:8px}.step p,.positioning-card p{font-size:14px;line-height:1.5;margin:0}
    .user-grid{display:grid;grid-template-columns:.9fr 1.1fr;gap:18px}.seller-grid,.vendor-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.vendor-card{background:#fff;border:1px solid rgba(16,35,47,.08);border-radius:8px;padding:16px;display:flex;gap:12px;align-items:center}.vendor-avatar{width:50px;height:50px;border-radius:8px;background:var(--forest);color:#fff;display:grid;place-items:center;font-weight:900}.vendor-card h3{margin:0 0 3px;font-size:16px}.vendor-card p,.vendor-card span{display:block;margin:0;color:var(--muted);font-size:12px}.cart-panel,.account-panel{background:#fff;border:1px solid rgba(16,35,47,.08);border-radius:8px;padding:18px}.cart-panel h3,.account-panel h3{margin-top:0}.cart-line{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #edf4f2;padding:11px 0}.cart-line:last-child{border-bottom:0}.table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line)}.table th,.table td{padding:10px;border-bottom:1px solid #edf2f7;text-align:left;font-size:13px}.table th{background:#f5faf8}.pill{display:inline-flex;border:1px solid #b8d8d2;border-radius:999px;padding:6px 10px;background:#fff;font-size:12px;color:#315b54;font-weight:800}
    .empty-state{grid-column:1/-1;background:#fff;border:1px solid var(--line);border-radius:8px;padding:24px}.footer{padding:34px 44px;color:var(--muted);font-size:13px;text-align:center}
    @media(max-width:980px){.hero,.user-grid,.ops-grid{grid-template-columns:1fr}.product-grid,.workflow,.seller-grid,.vendor-grid,.positioning,.demo-board,.category-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.search-row{grid-template-columns:1fr}.nav{display:none}}@media(max-width:640px){.topbar,.hero,.section{padding-left:20px;padding-right:20px}.hero h1{font-size:40px}.product-grid,.workflow,.seller-grid,.vendor-grid,.positioning,.demo-board,.photo-board,.category-grid,.ops-score{grid-template-columns:1fr}.demo-board{padding-left:20px;padding-right:20px}.search-btn{min-height:44px}}
  </style>
</head>
<body>
  <header class="topbar"><div class="brand"><div class="mark">' . $esc($config['mark']) . '</div>' . $esc($config['brand']) . '</div><nav class="nav">' . $navHtml . '</nav></header>
  <main>
  <div class="hero-wrap">
    <section class="hero">
      <div><div class="eyebrow">Trusted service marketplace</div><h1>' . $esc($config['hero_headline']) . '</h1><p>' . $esc($config['hero_copy']) . '</p><div class="hero-actions"><a class="button" href="#shop">Explore services</a><a class="button secondary" href="#vendors">View providers</a></div></div>
      <aside class="hero-media"><div class="booking-panel"><h3>Find help near you</h3><div class="search-row"><div class="field"><span>Service</span><strong>Cleaning, repairs, installs</strong></div><div class="field"><span>Location</span><strong>Toronto area</strong></div><button class="search-btn">Search</button></div></div><div class="photo-board"><div class="photo-card photo-a"><span>Home services</span><strong>Verified crews, clear pricing, tracked jobs.</strong></div><div class="photo-card photo-b"><span>Field operations</span><strong>Dispatch, estimates, and provider workflows.</strong></div></div></aside>
    </section>
    <aside class="demo-board"><div class="board-row"><span>' . $esc($metricLabels['vendors'] ?? '') . '</span><strong>' . $vendorCount . '</strong></div><div class="board-row"><span>' . $esc($metricLabels['products'] ?? '') . '</span><strong>' . $productCount . '</strong></div><div class="board-row"><span>' . $esc($metricLabels['orders'] ?? '') . '</span><strong>' . $suborderCount . '</strong></div><div class="board-row"><span>' . $esc($metricLabels['take'] ?? '') . '</span><strong>' . $money($takeRateMinor) . '</strong></div></aside>
  </div>
    <section class="section"><div class="section-head"><div><h2>' . $esc($config['positioning_headline']) . '</h2><p>' . $esc($config['positioning_copy']) . '</p></div></div><div class="positioning">' . $positioningHtml . '</div></section>
    <section class="section" id="categories"><div class="section-head"><div><h2>Browse every service category</h2><p>Tenant-scoped categories and subcategories are loaded from the Gigsii marketplace taxonomy.</p></div><span class="pill">Task-style hierarchy</span></div><div class="category-grid">' . $categoryCards . '</div><div class="subcategory-cloud">' . $subcategoryPills . '</div></section>
    <section class="section" id="shop"><div class="section-head"><div><h2>' . $esc($config['catalog_headline']) . '</h2><p>' . $esc($config['catalog_copy']) . '</p></div><span class="pill">' . $esc($config['catalog_badge']) . '</span></div><div class="product-grid">' . $cards . '</div></section>
    <section class="section" id="vendors"><div class="section-head"><div><h2>' . $esc($config['vendor_headline']) . '</h2><p>' . $esc($config['vendor_copy']) . '</p></div><span class="pill">' . $esc($config['vendor_badge']) . '</span></div><div class="vendor-grid">' . $vendorCards . '</div></section>
    <section class="section" id="buyer"><div class="section-head"><div><h2>' . $esc($config['buyer_headline']) . '</h2><p>' . $esc($config['buyer_copy']) . '</p></div></div><div class="user-grid"><div class="cart-panel"><h3>Checkout preview</h3><div class="cart-line"><span>Cart contains products from multiple vendors</span><strong>Split after payment</strong></div><div class="cart-line"><span>Tax, shipping, discounts</span><strong>Allocated by suborder</strong></div><div class="cart-line"><span>Payment</span><strong>Stripe test intent</strong></div><div class="cart-line"><span>Refund support</span><strong>Commission reversal</strong></div></div><div class="account-panel"><h3>Buyer order history</h3><table class="table"><thead><tr><th>Order</th><th>Status</th><th>Payment</th><th>Total</th><th>Refunded</th><th>Tracking</th></tr></thead><tbody>' . $orderRows . '</tbody></table></div></div></section>
    <section class="section" id="operations"><div class="section-head"><div><h2>Service operations cockpit</h2><p>Booking, dispatch, estimate, and referral records come from the shared Mercato service-ops module.</p></div><span class="pill">Soft-launch ops</span></div><div class="ops-grid"><div class="ops-score"><div><span>Bookings</span><strong>' . $bookingCount . '</strong></div><div><span>Jobs</span><strong>' . $jobCount . '</strong></div><div><span>Estimates</span><strong>' . $estimateCount . '</strong></div><div><span>Referrals</span><strong>' . $referralCount . '</strong></div></div><div class="account-panel"><h3>Recent jobs</h3><table class="table"><thead><tr><th>Job</th><th>Provider</th><th>Status</th><th>Assignee</th><th>Updated</th></tr></thead><tbody>' . $jobRowsHtml . '</tbody></table></div></div></section>
    <section class="section" id="seller"><div class="section-head"><div><h2>' . $esc($config['seller_headline']) . '</h2><p>' . $esc($config['seller_copy']) . '</p></div><a class="button secondary" href="/wp-admin/admin.php?page=mercato-vendor">' . $esc($config['secondary_cta']) . '</a></div><div class="seller-grid">' . $sellerSteps . '</div></section>
    <section class="section"><div class="section-head"><div><h2>' . $esc($config['workflow_headline']) . '</h2><p>' . $esc($config['workflow_copy']) . '</p></div></div><div class="workflow">' . $workflowSteps . '</div></section>
  </main>
  <footer class="footer">' . $esc($config['footer']) . '</footer>
</body>
</html>';
    }
}
