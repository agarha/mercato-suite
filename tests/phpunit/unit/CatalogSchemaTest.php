<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CatalogSchemaTest extends TestCase
{
    private string $migration;
    private string $repository;
    private string $provider;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $this->migration = file_get_contents($root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-products/migrations/0002_catalog_relationships.sql') ?: '';
        $this->repository = file_get_contents($root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-products/src/Repository.php') ?: '';
        $this->provider = file_get_contents($root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-products/src/Provider.php') ?: '';
    }

    public function testCatalogRelationshipTablesAreTenantScoped(): void
    {
        foreach ([
            'mercato_categories',
            'mercato_product_categories',
            'mercato_vendor_service_offerings',
            'mercato_vendor_locations',
            'mercato_service_areas',
        ] as $table) {
            self::assertStringContainsString("`{prefix}{$table}`", $this->migration);
        }

        self::assertStringContainsString('`tenant_id` BIGINT UNSIGNED NOT NULL', $this->migration);
        self::assertStringContainsString('UNIQUE KEY `uk_vendor_product` (`tenant_id`, `vendor_id`, `product_id`)', $this->migration);
        self::assertStringContainsString('KEY `idx_geo` (`tenant_id`, `latitude`, `longitude`)', $this->migration);
    }

    public function testCatalogRepositoryExposesManyProviderCategoryAndGeoOperations(): void
    {
        foreach ([
            'createCategory',
            'assignCategories',
            'upsertOffering',
            'addVendorLocation',
            'radiusKm',
            'distance_km',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->repository);
        }
    }

    public function testRestApiExposesCatalogRelationshipEndpoints(): void
    {
        foreach ([
            '/categories',
            '/products/(?P<id>\d+)/categories',
            '/products/(?P<id>\d+)/offerings',
            '/vendors/(?P<vendor_id>\d+)/locations',
            'category_id',
            'radius_km',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->provider);
        }
    }
}
