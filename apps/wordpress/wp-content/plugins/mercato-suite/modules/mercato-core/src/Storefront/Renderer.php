<?php

declare(strict_types=1);

namespace Mercato\Core\Storefront;

use Mercato\Core\Tenant\Resolver;

/**
 * Storefront orchestrator. Dispatches request path to the matching
 * page renderer and loads tenant config + tenant-scoped data for it.
 *
 * Route table:
 *   /                                          -> home (default tenant)
 *   /t/<slug>/                                 -> home
 *   /t/<slug>/services[?q=&category=]          -> services index (filterable)
 *   /t/<slug>/providers                        -> provider directory
 *   /t/<slug>/providers/<provider-slug>        -> provider detail
 *   /t/<slug>/requests/new                     -> request-new form
 *   /t/<slug>/provider/dashboard               -> provider self-service dashboard
 *   /t/<slug>/account                          -> buyer account
 *   anything else                              -> null (WP fallthrough)
 */
final class Renderer
{
    private const TENANT_PREFIX = '#^/t/[a-z0-9][a-z0-9-]{0,63}#i';

    private string $templateDir;
    private string $assetUrl;

    public function __construct(
        private readonly Resolver $tenants,
        private readonly Config $config,
        private readonly Repository $repository,
    ) {
        $this->templateDir = \dirname(__DIR__, 2) . '/templates/storefront';
        $this->assetUrl = (\defined('MERCATO_SUITE_FILE') && \function_exists('plugin_dir_url'))
            ? \plugin_dir_url(\MERCATO_SUITE_FILE) . 'modules/mercato-core/assets'
            : '/wp-content/plugins/mercato-suite/modules/mercato-core/assets';
    }

    public function renderForPath(string $path): ?string
    {
        $path = (string) \parse_url($path, PHP_URL_PATH);
        $local = $this->stripTenantPrefix($path);
        if ($local === null) {
            return null;
        }

        $local = \rtrim($local, '/');
        if ($local === '' || $local === '/') {
            return $this->renderHome();
        }
        if ($local === '/services') {
            return $this->renderServices();
        }
        if ($local === '/providers') {
            return $this->renderProviders();
        }
        if (\preg_match('#^/providers/([a-z0-9][a-z0-9-]{0,127})$#i', $local, $m) === 1) {
            return $this->renderProviderDetail($m[1]);
        }
        if ($local === '/requests/new') {
            return $this->renderRequestNew();
        }
        if ($local === '/provider/dashboard') {
            return $this->renderProviderDashboard();
        }
        if ($local === '/account') {
            return $this->renderAccount();
        }
        return null;
    }

    public function renderHome(): string
    {
        $tid    = $this->tenants->currentTenantId();
        $config = $this->config->forTenant($tid);
        $theme  = (string) ($config['theme'] ?? '');

        // Per-tenant theme override. Mercato default = page.php.
        // Gigsii sets theme="taskfirst" in its tenant settings JSON to use
        // the bespoke design pulled from the Gigsii design canvas.
        $template = $theme === 'taskfirst' ? 'page-taskfirst.php' : 'page.php';

        return $this->render($template, [
            'data'         => $this->repository->snapshot($tid),
            'current_page' => 'home',
        ]);
    }

    public function renderServices(): string
    {
        $tid = $this->tenants->currentTenantId();
        $q = isset($_GET['q']) ? \trim((string) $_GET['q']) : '';
        $category = isset($_GET['category']) ? (int) $_GET['category'] : 0;
        if (\function_exists('sanitize_text_field')) {
            $q = \sanitize_text_field($q);
        }
        if (\strlen($q) > 100) {
            $q = \substr($q, 0, 100);
        }

        return $this->render('services-page.php', [
            'data' => $this->repository->servicesPage($tid, $q, $category),
            'current_page' => 'services',
            'search_q' => $q,
            'search_category' => $category,
        ]);
    }

    public function renderProviders(): string
    {
        $tid = $this->tenants->currentTenantId();
        return $this->render('providers-page.php', [
            'data' => $this->repository->providersPage($tid),
            'current_page' => 'providers',
        ]);
    }

    public function renderProviderDetail(string $slug): ?string
    {
        $tid = $this->tenants->currentTenantId();
        $detail = $this->repository->providerDetail($tid, $slug);
        if ($detail === null) {
            return null;
        }
        return $this->render('provider-detail.php', [
            'data' => $detail,
            'current_page' => 'providers',
        ]);
    }

    public function renderRequestNew(): string
    {
        $tid = $this->tenants->currentTenantId();
        return $this->render('request-new.php', [
            'data' => $this->repository->requestNewPage($tid),
            'current_page' => 'requests',
        ]);
    }

    public function renderProviderDashboard(): string
    {
        $tid = $this->tenants->currentTenantId();
        $uid = \function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        return $this->render('provider-dashboard.php', [
            'data' => $this->repository->providerDashboard($tid, $uid),
            'current_page' => 'provider',
        ]);
    }

    public function renderAccount(): string
    {
        $tid = $this->tenants->currentTenantId();
        $uid = \function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        return $this->render('account-page.php', [
            'data' => $this->repository->accountPage($tid, $uid),
            'current_page' => 'account',
        ]);
    }

    public function matchesStorefrontRoute(string $path): bool
    {
        if ($path === '' || $path === '/') {
            return true;
        }
        return $this->stripTenantPrefix($path) !== null;
    }

    private function stripTenantPrefix(string $path): ?string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }
        if (\preg_match(self::TENANT_PREFIX, $path, $m) !== 1) {
            return null;
        }
        $remainder = \substr($path, \strlen($m[0]));
        return $remainder === '' ? '/' : $remainder;
    }

    /**
     * @param array<string,mixed> $extra
     */
    private function render(string $templateFile, array $extra): string
    {
        $tid = $this->tenants->currentTenantId();
        $config = $this->config->forTenant($tid);

        $context = \array_merge([
            'config' => $config,
            'asset_url' => $this->assetUrl,
            'esc' => static fn (mixed $v): string => \esc_html((string) $v),
            'attr' => static fn (mixed $v): string => \esc_attr((string) $v),
            'money' => static fn (mixed $minor): string => '$' . \number_format(((int) $minor) / 100, 2),
            'partials