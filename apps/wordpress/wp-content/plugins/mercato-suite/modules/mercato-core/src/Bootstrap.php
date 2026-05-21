<?php

declare(strict_types=1);

namespace Mercato\Core;

use RuntimeException;

/**
 * Boot sequence:
 *   1. Refuse to boot if environment doesn't satisfy non-negotiables (FR-CORE-001).
 *   2. Discover module manifests.
 *   3. Topologically sort.
 *   4. For each module: load its ServiceProvider (if any) and register() into container.
 *   5. For each module in order: call boot().
 *   6. Fire mercato_suite_booted.
 *
 * See docs_v2/04_fsd/FSD.md §4.
 */
final class Bootstrap
{
    private const REQUIRED_PHP = '8.2.0';
    private const REQUIRED_WP  = '6.4.0';
    private const REQUIRED_WC  = '8.0.0';

    public function __construct(
        private readonly string $modulesPath,
        private readonly Container $container = new Container(),
    ) {
    }

    public function boot(): void
    {
        $this->guardEnvironment();

        $providers = $this->providers();

        // 1. register() — pure container wiring, no WP calls
        foreach ($providers as $provider) {
            $provider->register();
        }

        // 2. boot() — WP API available
        foreach ($providers as $provider) {
            $provider->boot();
            if (\function_exists('do_action')) {
                \do_action('mercato_module_booted', $provider->manifest->slug, $provider->manifest);
            }
        }

        if (\function_exists('do_action')) {
            \do_action('mercato_suite_booted', \MERCATO_SUITE_VERSION);
        }
    }

    public function activate(): void
    {
        $this->guardEnvironment();

        foreach ($this->providers() as $provider) {
            $provider->register();
        }

        if ($this->container->has(DB\Migrator::class)) {
            $this->container->get(DB\Migrator::class)->migrate();
        }
    }

    /**
     * @return list<ServiceProvider>
     */
    private function providers(): array
    {
        $registry = new ModuleRegistry($this->modulesPath);
        $registry->discover();

        /** @var list<ServiceProvider> $providers */
        $providers = [];

        foreach ($registry->ordered() as $manifest) {
            $this->loadModuleSource($manifest);
            $providers[] = $this->createProvider($manifest);
        }

        return $providers;
    }

    private function guardEnvironment(): void
    {
        if (\version_compare(PHP_VERSION, self::REQUIRED_PHP, '<')) {
            $this->fatal('Mercato Suite requires PHP ' . self::REQUIRED_PHP . '+.');
        }

        if (!\defined('ABSPATH')) {
            return; // running in CLI test harness — skip WP/WC checks
        }

        if (!\function_exists('get_bloginfo') || \version_compare((string) \get_bloginfo('version'), self::REQUIRED_WP, '<')) {
            $this->fatal('Mercato Suite requires WordPress ' . self::REQUIRED_WP . '+.');
        }

        if (!\defined('WC_VERSION') || \version_compare(\WC_VERSION, self::REQUIRED_WC, '<')) {
            $this->fatal('Mercato Suite requires WooCommerce ' . self::REQUIRED_WC . '+ with HPOS enabled.');
        }

        if (!$this->isHposEnabled()) {
            $this->fatal('Mercato Suite requires WooCommerce HPOS (High-Performance Order Storage) to be enabled.');
        }
    }

    private function isHposEnabled(): bool
    {
        if (\class_exists('\\Automattic\\WooCommerce\\Utilities\\OrderUtil')) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        return false;
    }

    private function fatal(string $message): void
    {
        if (\function_exists('add_action')) {
            \add_action('admin_notices', static function () use ($message): void {
                echo '<div class="notice notice-error"><p>' . \esc_html($message) . '</p></div>';
            });
            \add_action('admin_init', static function () use ($message): void {
                \deactivate_plugins(\plugin_basename(\MERCATO_SUITE_FILE));
                throw new RuntimeException($message);
            });
            return;
        }
        throw new RuntimeException($message);
    }

    /**
     * Locate and instantiate a module's ServiceProvider, falling back to a generic NullProvider
     * so modules without one boot cleanly.
     */
    private function createProvider(ModuleManifest $manifest): ServiceProvider
    {
        $providerClass = $manifest->namespace . '\\Provider';

        if (\class_exists($providerClass) && \is_subclass_of($providerClass, ServiceProvider::class)) {
            return new $providerClass($manifest, $this->container);
        }

        return new class($manifest, $this->container) extends ServiceProvider {
            public function register(): void
            {
                // No-op default for modules that have not implemented Provider yet.
            }
        };
    }

    private function loadModuleSource(ModuleManifest $manifest): void
    {
        $src = $this->modulesPath . '/' . $manifest->slug . '/src';

        if (!\is_dir($src)) {
            return;
        }

        $files = \glob($src . '/**/*.php') ?: [];
        $files = \array_merge(\glob($src . '/*.php') ?: [], $files);
        \sort($files);

        foreach (\array_unique($files) as $file) {
            require_once $file;
        }
    }
}
