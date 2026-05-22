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

    /**
     * @param callable():mixed $callback
     */
    protected function idempotent(\WP_REST_Request $request, callable $callback): mixed
    {
        $key = $this->idempotencyKey($request);
        if ($key === '') {
            return $callback();
        }

        $endpoint = $request->get_method() . ' ' . $request->get_route();
        $store = $this->container->get(Idempotency\Store::class);
        $cached = $store->find($endpoint, $key);
        if ($cached !== null) {
            $body = \json_decode($cached['response_body'], true);
            $response = new \WP_REST_Response($body, $cached['status_code']);
            $response->header('X-Mercato-Idempotent-Replay', '1');
            return $response;
        }

        $response = $callback();
        if ($response instanceof \WP_REST_Response) {
            $store->remember(
                $endpoint,
                $key,
                \wp_json_encode($response->get_data(), JSON_THROW_ON_ERROR),
                $response->get_status()
            );
        }

        return $response;
    }

    private function idempotencyKey(\WP_REST_Request $request): string
    {
        $key = (string) ($request->get_header('idempotency-key') ?: $request->get_header('x-idempotency-key'));
        $key = \function_exists('sanitize_text_field') ? \sanitize_text_field($key) : \trim($key);
        if ($key === '') {
            return '';
        }

        return \substr($key, 0, 96);
    }
}
