<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Smoke test for the six named adapter modules at the WC<->Mercato seam.
 *
 * The adapters were added in claude/adapter-extraction. This test pins
 * them against the directive (CODEX_DIRECTIVE.md §3.3, §4, §5) so they
 * cannot silently drift away from their contract.
 *
 * Style mirrors Codex's existing string-presence unit tests
 * (CatalogSchemaTest, TenantResolverTest, etc.) — no DB, no boot, just
 * read source files and assert what they must contain.
 */
final class AdapterSmokeTest extends TestCase
{
    private const ADAPTERS = [
        'mercato-job-to-order-adapter' => [
            'namespace' => 'Mercato\\Adapters\\JobToOrder',
            'main_class' => 'JobToOrderAdapter',
            'wc_hooks' => [],
            'events' => ['mercato.adapter.job_to_order.created.v1'],
        ],
        'mercato-order-to-job-adapter' => [
            'namespace' => 'Mercato\\Adapters\\OrderToJob',
            'main_class' => 'OrderToJobAdapter',
            'wc_hooks' => [
                'woocommerce_new_order',
                'woocommerce_payment_complete',
                'woocommerce_order_status_changed',
            ],
            'events' => [
                'mercato.adapter.order_to_job.linked.v1',
                'mercato.adapter.order_to_job.paid.v1',
                'mercato.adapter.order_to_job.status_synced.v1',
            ],
        ],
        'mercato-refund-to-job-adapter' => [
            'namespace' => 'Mercato\\Adapters\\RefundToJob',
            'main_class' => 'RefundToJobAdapter',
            'wc_hooks' => [
                'woocommerce_refund_created',
                'woocommerce_order_refunded',
            ],
            'events' => [
                'mercato.adapter.refund_to_job.reversed.v1',
                'mercato.adapter.refund_to_job.post_refund.v1',
            ],
        ],
        'mercato-coupon-context-adapter' => [
            'namespace' => 'Mercato\\Adapters\\CouponContext',
            'main_class' => 'CouponContextAdapter',
            'wc_hooks' => ['woocommerce_coupon_is_valid'],
            'events' => ['mercato.adapter.coupon_context.evaluated.v1'],
        ],
        'mercato-tax-context-adapter' => [
            'namespace' => 'Mercato\\Adapters\\TaxContext',
            'main_class' => 'TaxContextAdapter',
            'wc_hooks' => [
                'woocommerce_cart_calculate_fees',
                'woocommerce_checkout_create_order',
            ],
            'events' => ['mercato.adapter.tax_context.applied.v1'],
        ],
        'mercato-subscription-bridge-adapter' => [
            'namespace' => 'Mercato\\Adapters\\SubscriptionBridge',
            'main_class' => 'SubscriptionBridgeAdapter',
            'wc_hooks' => [
                'woocommerce_subscription_status_updated',
                'woocommerce_subscription_renewal_payment_complete',
            ],
            'events' => [
                'mercato.adapter.subscription_bridge.renewed.v1',
                'mercato.adapter.subscription_bridge.cancelled.v1',
                'mercato.adapter.subscription_bridge.status_changed.v1',
            ],
        ],
    ];

    /** §5 forbidden patterns — must not appear in any adapter module. */
    private const FORBIDDEN_GLOBAL = [
        'process_payment',
        'extends WC_Payment_Gateway',
        'Mercato\\Cart\\',
        'Mercato\\Checkout\\',
    ];

    private string $modulesDir;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $this->modulesDir = $root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules';
    }

    public function testAllSixAdapterModulesExistOnDisk(): void
    {
        foreach (array_keys(self::ADAPTERS) as $slug) {
            self::assertDirectoryExists($this->modulesDir . '/' . $slug, "missing module dir: {$slug}");
            self::assertFileExists($this->modulesDir . '/' . $slug . '/module.json', "missing manifest: {$slug}");
            self::assertFileExists($this->modulesDir . '/' . $slug . '/src/Provider.php', "missing Provider.php: {$slug}");
        }
    }

    public function testEachManifestDeclaresAdapterTierAndCorrectNamespace(): void
    {
        foreach (self::ADAPTERS as $slug => $spec) {
            $manifest = json_decode((string) file_get_contents($this->modulesDir . '/' . $slug . '/module.json'), true);
            self::assertIsArray($manifest, "{$slug}: manifest is not valid JSON");
            self::assertSame($slug, $manifest['slug'] ?? null, "{$slug}: slug mismatch");
            self::assertSame('adapter', $manifest['tier'] ?? null, "{$slug}: tier must be 'adapter'");
            self::assertSame($spec['namespace'], $manifest['namespace'] ?? null, "{$slug}: namespace mismatch");
        }
    }

    public function testEachManifestDeclaresItsAdapterEvents(): void
    {
        foreach (self::ADAPTERS as $slug => $spec) {
            $manifest = json_decode((string) file_get_contents($this->modulesDir . '/' . $slug . '/module.json'), true);
            $provides = (array) ($manifest['provides_events'] ?? []);
            foreach ($spec['events'] as $event) {
                self::assertContains($event, $provides, "{$slug}: manifest must declare event '{$event}'");
            }
        }
    }

    public function testCanonicalWooCommerceHooksAreWiredInBoot(): void
    {
        foreach (self::ADAPTERS as $slug => $spec) {
            if (empty($spec['wc_hooks'])) {
                continue;
            }
            $providerSrc = (string) file_get_contents($this->modulesDir . '/' . $slug . '/src/Provider.php');
            foreach ($spec['wc_hooks'] as $hook) {
                self::assertStringContainsString(
                    "'{$hook}'",
                    $providerSrc,
                    "{$slug}: Provider.php must subscribe to canonical WC hook '{$hook}'"
                );
            }
        }
    }

    public function testJobToOrderAdapterIsTheOnlyAllowedCallerOfWcCreateOrder(): void
    {
        $adaptersDirs = [];
        foreach (array_keys(self::ADAPTERS) as $slug) {
            $adaptersDirs[] = $this->modulesDir . '/' . $slug;
        }
        foreach ($adaptersDirs as $dir) {
            $slug = basename($dir);
            $found = false;
            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($iter as $f) {
                if (!$f->isFile() || $f->getExtension() !== 'php') {
                    continue;
                }
                $src = (string) file_get_contents($f->getPathname());
                // Match real call (with backslash escape or without) — not docblock prose.
                if (preg_match('/\\\\?wc_create_order\\s*\\(/', $src) === 1) {
                    $found = true;
                    break;
                }
            }
            if ($slug === 'mercato-job-to-order-adapter') {
                self::assertTrue($found, "{$slug}: must contain a real call to wc_create_order()");
            } else {
                self::assertFalse($found, "{$slug}: must NOT call wc_create_order() (directive §5.1)");
            }
        }
    }

    public function testNoAdapterContainsForbiddenPatterns(): void
    {
        foreach (array_keys(self::ADAPTERS) as $slug) {
            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->modulesDir . '/' . $slug, FilesystemIterator::SKIP_DOTS));
            foreach ($iter as $f) {
                if (!$f->isFile() || $f->getExtension() !== 'php') {
                    continue;
                }
                $src = (string) file_get_contents($f->getPathname());
                foreach (self::FORBIDDEN_GLOBAL as $needle) {
                    // Allow only mentions inside docblock comments (// or /* */) that *describe* the rule.
                    // Strip comments before matching to keep doc-prose ("never extends WC_Payment_Gateway")
                    // from tripping the assertion.
                    $stripped = preg_replace('|//.*|', '', $src);
                    $stripped = preg_replace('|/\\*.*?\\*/|s', '', $stripped);
                    self::assertStringNotContainsString(
                        $needle,
                        (string) $stripped,
                        "{$slug}: {$f->getFilename()} contains forbidden pattern '{$needle}' outside a comment (directive §5)"
                    );
                }
            }
        }
    }

    public function testEachAdapterProviderExtendsTheServiceProviderBaseClass(): void
    {
        foreach (self::ADAPTERS as $slug => $spec) {
            $providerSrc = (string) file_get_contents($this->modulesDir . '/' . $slug . '/src/Provider.php');
            self::assertStringContainsString(
                "namespace {$spec['namespace']};",
                $providerSrc,
                "{$slug}: Provider.php namespace declaration must match manifest"
            );
            self::assertStringContainsString(
                'extends ServiceProvider',
                $providerSrc,
                "{$slug}: Provider.php must extend Mercato\\Core\\ServiceProvider"
            );
            self::assertStringContainsString(
                'public function register(): void',
                $providerSrc,
                "{$slug}: Provider.php must implement register()"
            );
        }
    }

    public function testEachAdapterHasItsNamedMainClass(): void
    {
        foreach (self::ADAPTERS as $slug => $spec) {
            $path = $this->modulesDir . '/' . $slug . '/src/' . $spec['main_class'] . '.php';
            self::assertFileExists($path, "{$slug}: missing main class file {$spec['main_class']}.php");
            $src = (string) file_get_contents($path);
            self::assertStringContainsString(
                "final class {$spec['main_class']}",
                $src,
                "{$slug}: {$spec['main_class']}.php must declare 'final class {$spec['main_class']}'"
            );
        }
    }
}
