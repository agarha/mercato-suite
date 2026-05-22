<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TenantStorefrontConfigTest extends TestCase
{
    private string $coreProvider;
    private string $enterpriseProvider;
    private string $enterpriseRepository;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $this->coreProvider = file_get_contents($root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/src/Provider.php') ?: '';
        $this->enterpriseProvider = file_get_contents($root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-enterprise/src/Provider.php') ?: '';
        $this->enterpriseRepository = file_get_contents($root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-enterprise/src/Repository.php') ?: '';
    }

    public function testStorefrontUsesTenantSettingsInsteadOfHardcodedOnly(): void
    {
        self::assertStringContainsString('storefrontConfig', $this->coreProvider);
        self::assertStringContainsString('mercato_tenant_settings', $this->coreProvider);
        self::assertStringContainsString('$config[', $this->coreProvider);
        self::assertStringContainsString('WHERE tenant_id = %d', $this->coreProvider);
    }

    public function testEnterpriseApiCanUpdateTenantStorefrontConfig(): void
    {
        self::assertStringContainsString('/enterprise/storefront', $this->enterpriseProvider);
        self::assertStringContainsString('setStorefront', $this->enterpriseProvider);
        self::assertStringContainsString('setStorefront', $this->enterpriseRepository);
        self::assertStringContainsString('mercato.tenant.storefront.updated.v1', $this->enterpriseRepository);
    }

    public function testStorefrontConfigSupportsTenantBrandAndScreenCopy(): void
    {
        foreach ([
            'brand',
            'mark',
            'hero_headline',
            'catalog_headline',
            'vendor_headline',
            'metric_labels',
            'positioning_cards',
            'seller_steps',
            'workflow_steps',
        ] as $key) {
            self::assertStringContainsString($key, $this->coreProvider);
            self::assertStringContainsString($key, $this->enterpriseRepository);
        }
    }
}
