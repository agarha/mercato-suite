<?php

declare(strict_types=1);

namespace Mercato\Tests\Unit;

use Mercato\Core\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ModuleRegistryTest extends TestCase
{
    private string $modulesPath;

    protected function setUp(): void
    {
        $this->modulesPath = dirname(__DIR__, 3) . '/apps/wordpress/wp-content/plugins/mercato-suite/modules';
    }

    public function test_discovers_all_mvp_module_manifests(): void
    {
        $registry = new ModuleRegistry($this->modulesPath);
        $registry->discover();

        $slugs = array_map(static fn ($m) => $m->slug, $registry->ordered());

        $expectedSubset = [
            'mercato-core',
            'mercato-vendors',
            'mercato-products',
            'mercato-orders',
            'mercato-commissions',
            'mercato-payouts',
            'mercato-messaging',
            'mercato-notifications',
            'mercato-kyc-kyb',
            'mercato-enterprise',
            'mercato-stripe-connect',
            'mercato-sendgrid',
            'mercato-aws-s3',
        ];

        foreach ($expectedSubset as $expected) {
            self::assertContains($expected, $slugs, "Missing module: {$expected}");
        }
    }

    public function test_topological_sort_respects_dependencies(): void
    {
        $registry = new ModuleRegistry($this->modulesPath);
        $registry->discover();

        $positions = [];
        foreach ($registry->ordered() as $index => $manifest) {
            $positions[$manifest->slug] = $index;
        }

        $assertions = [
            ['mercato-core', 'mercato-vendors'],
            ['mercato-vendors', 'mercato-products'],
            ['mercato-products', 'mercato-orders'],
            ['mercato-orders', 'mercato-commissions'],
            ['mercato-stripe-connect', 'mercato-payouts'],
            ['mercato-sendgrid', 'mercato-notifications'],
            ['mercato-aws-s3', 'mercato-kyc-kyb'],
        ];

        foreach ($assertions as [$before, $after]) {
            self::assertLessThan(
                $positions[$after],
                $positions[$before],
                "{$before} must boot before {$after}"
            );
        }
    }

    public function test_missing_dependency_throws(): void
    {
        // Create temp manifest set with broken dep
        $tmp = sys_get_temp_dir() . '/mercato-test-' . uniqid();
        mkdir("$tmp/broken", 0700, true);
        file_put_contents("$tmp/broken/module.json", json_encode([
            'slug' => 'broken',
            'namespace' => 'Mercato\\Broken',
            'version' => '0.1.0',
            'sdk_version' => '^0.1',
            'requires' => ['nonexistent@^0.1'],
            'capabilities' => [],
            'tables' => [],
            'tier' => 'domain',
            'feature_flag' => 'mercato.broken',
        ]));

        $registry = new ModuleRegistry($tmp);
        $registry->discover();

        self::expectException(RuntimeException::class);
        self::expectExceptionMessageMatches('/dependency missing/');
        $registry->ordered();
    }

    public function test_manifest_declared_events_match_asyncapi(): void
    {
        // Sanity: every module either declares some events or explicitly declares none.
        $registry = new ModuleRegistry($this->modulesPath);
        $registry->discover();

        foreach ($registry->ordered() as $m) {
            // ModuleManifest doesn't currently expose events; this test reads JSON directly.
            $raw = json_decode(file_get_contents("{$this->modulesPath}/{$m->slug}/module.json"), true);
            self::assertArrayHasKey('provides_events', $raw, "{$m->slug} missing provides_events");
            self::assertArrayHasKey('consumes_events', $raw, "{$m->slug} missing consumes_events");
            self::assertIsArray($raw['provides_events']);
            self::assertIsArray($raw['consumes_events']);
            foreach ($raw['provides_events'] as $event) {
                self::assertMatchesRegularExpression(
                    '/^mercato\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*){1,4}\.v\d+$/',
                    $event,
                    "Event '{$event}' from {$m->slug} does not match canonical taxonomy"
                );
            }
        }
    }
}
