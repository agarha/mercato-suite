<?php

declare(strict_types=1);

use Mercato\Core\Tenant\Resolver;
use PHPUnit\Framework\TestCase;

final class TenantResolverTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'], $_SERVER['HTTP_X_MERCATO_TENANT_ID'], $GLOBALS['mercato_test_tenants']);
        putenv('MERCATO_TRUST_TENANT_HEADER');
    }

    public function testResolvesTenantFromPathPrefix(): void
    {
        $GLOBALS['mercato_test_tenants'] = ['slugs' => ['gigsii' => 42], 'domains' => []];
        $_SERVER['REQUEST_URI'] = '/t/gigsii/services';

        self::assertSame(42, (new Resolver())->currentTenantId());
    }

    public function testTenantPathRootIsAllowedForStorefrontRendering(): void
    {
        $root = dirname(__DIR__, 3);
        $provider = file_get_contents($root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/src/Provider.php') ?: '';

        self::assertStringContainsString('/t/[a-z0-9]', $provider);
        self::assertStringContainsString('renderDemoStorefront', $provider);
    }

    public function testResolvesTenantFromMappedDomain(): void
    {
        $GLOBALS['mercato_test_tenants'] = ['slugs' => [], 'domains' => ['gigsii.test' => 77]];
        $_SERVER['HTTP_HOST'] = 'gigsii.test:8094';
        $_SERVER['REQUEST_URI'] = '/';

        self::assertSame(77, (new Resolver())->currentTenantId());
    }

    public function testResolvesTenantFromSubdomainSlug(): void
    {
        $GLOBALS['mercato_test_tenants'] = ['slugs' => ['gigsii' => 88], 'domains' => []];
        $_SERVER['HTTP_HOST'] = 'gigsii.mercato.local';
        $_SERVER['REQUEST_URI'] = '/';

        self::assertSame(88, (new Resolver())->currentTenantId());
    }

    public function testTrustedHeaderRequiresExplicitEnablement(): void
    {
        $_SERVER['HTTP_X_MERCATO_TENANT_ID'] = '99';

        self::assertSame(1, (new Resolver())->currentTenantId());

        putenv('MERCATO_TRUST_TENANT_HEADER=true');
        self::assertSame(99, (new Resolver())->currentTenantId());
    }
}
