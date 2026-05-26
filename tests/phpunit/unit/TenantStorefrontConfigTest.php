<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Storefront strings can live in mercato-core's Provider, the new
 * Storefront/{Config,Repository,Renderer} classes, or the template
 * partials under templates/storefront/. Test searches the aggregated
 * corpus so a future refactor can split modules differently without
 * breaking the contract.
 */
final class TenantStorefrontConfigTest extends TestCase
{
    private string $coreCorpus;
    private string $enterpriseProvider;
    private string $enterpriseRepository;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $core = $root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core';
        $this->coreCorpus = $this->concatPhp($core . '/src') . "\n" . $this->concatPhp($core . '/templates');
        $this->enterpriseProvider = file_get_contents($root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-enterprise/src/Provider.php') ?: '';
        $this->enterpriseRepository = file_get_contents($root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-enterprise/src/Repository.php') ?: '';
    }

    public function testStorefrontUsesTenantSettingsInsteadOfHardcodedOnly(): void
    {
        self::assertStringContainsString('Storefront\\Config', $this->coreCorpus);
        self::assertStringContainsString('mercato_tenant_settings', $this->coreCorpus);
        self::assertStringContainsString('$config[', $this->coreCorpus);
        self::assertStringContainsString('WHERE tenant_id = %d', $this->coreCorpus);
    }

    public function testEnterpriseApiCanUpdateTenantStorefrontConfig(): void
    {
        self::assertStringContainsString('/enterprise/storefront', $this->enterpriseProvider);
        self::assertStringContainsString('setStorefront', $this->enterpriseProvider);
        self::assertStringContainsString('setStorefront', $this->enterpriseRepository);
        self::assertStringContainsString('setStorefrontForTenant', $this->enterpriseRepository);
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
            self::assertStringContainsString($key, $this->coreCorpus, "Storefront corpus missing '{$key}'");
            self::assertStringContainsString($key, $this->enterpriseRepository, "Enterprise repository missing '{$key}'");
        }
    }

    public function testStorefrontRendersTenantTaxonomyAndServiceOpsEvidence(): void
    {
        foreach ([
            'Browse every service category',
            'mercato_categories',
            'mercato_jobs',
            'mercato_booking_requests',
            'mercato_estimates',
            'mercato_referrals',
            'Service operations cockpit',
            'Recent jobs',
            'Post a request and let providers bid',
            'mercato_service_requests',
            'mercato_service_bids',
            'All Gigsii capabilities enabled',
            'mercato_tenant_feature_flags',
            'mercato_tenant_integrations',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->coreCorpus, "Storefront corpus missing '{$needle}'");
        }
    }

    private function concatPhp(string $dir): string
    {
        if (!is_dir($dir)) {
            return '';
        }
        $out = '';
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && in_array($f->getExtension(), ['php', 'phtml'], true)) {
                $out .= (file_get_contents($f->getPathname()) ?: '') . "\n";
            }
        }
        return $out;
    }
}
