<?php

declare(strict_types=1);

namespace Mercato\Core;

/**
 * ServiceProvider — every Mercato module extends this.
 *
 * Implementation contract:
 * - register(): bind services into the DI container; MUST NOT call WP functions.
 * - boot():     called after all modules have registered; WP API available.
 * - migrate():  called when migrations need to run for this module.
 *
 * See docs_v2/04_fsd/FSD.md §4.2.
 */
abstract class ServiceProvider
{
    public function __construct(
        public readonly ModuleManifest $manifest,
        protected readonly Container $container,
    ) {
    }

    /**
     * Bind services into the DI container.
     * MUST be deterministic and side-effect-free.
     */
    abstract public function register(): void;

    /**
     * Boot the module after all providers have registered.
     * WordPress APIs are available here.
     */
    public function boot(): void
    {
        // No-op by default; modules override.
    }

    /**
     * Migration files this module owns. Each entry is an absolute path to a .sql or .php file.
     * Migrator collects from all modules and runs them in dependency order.
     *
     * @return list<string>
     */
    public function migrations(): array
    {
        $dir = dirname((new \ReflectionClass($this))->getFileName(), 2) . '/migrations';

        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.sql') ?: [];
        sort($files);

        return $files;
    }
}
