<?php

declare(strict_types=1);

use Mercato\Core\Tenant\IntegrationSettings;
use PHPUnit\Framework\TestCase;

final class TenantIntegrationsTest extends TestCase
{
    public function testProviderListCoversSaasIntegrationClasses(): void
    {
        foreach (['stripe', 'sendgrid', 's3', 'tax', 'search', 'sms', 'kyc'] as $provider) {
            self::assertContains($provider, IntegrationSettings::PROVIDERS);
        }
    }

    public function testTenantIntegrationSchemaStoresPublicConfigAndSecretReferences(): void
    {
        $root = dirname(__DIR__, 3);
        $migration = file_get_contents($root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/migrations/0007_tenant_integrations.sql') ?: '';

        foreach (['mercato_tenant_integrations', 'provider_key', 'public_config', 'secret_refs', 'tenant_id'] as $needle) {
            self::assertStringContainsString($needle, $migration);
        }
    }

    public function testEnterpriseApiExposesTenantIntegrations(): void
    {
        $root = dirname(__DIR__, 3);
        $provider = file_get_contents($root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-enterprise/src/Provider.php') ?: '';

        self::assertStringContainsString('/enterprise/integrations', $provider);
        self::assertStringContainsString('setIntegration', $provider);
        self::assertStringContainsString(IntegrationSettings::class, $provider);
    }
}
