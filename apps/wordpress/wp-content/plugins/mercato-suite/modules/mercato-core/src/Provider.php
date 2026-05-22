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
}
