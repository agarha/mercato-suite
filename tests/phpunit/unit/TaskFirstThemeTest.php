<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Pins the Task-First theme contract.
 *
 * The Task-First design is a per-tenant override (Gigsii only). It must:
 *   1) NOT touch Mercato defaults — Storefront\Config::defaults() must
 *      not set `theme`, and page.php must not include taskfirst markup.
 *   2) Ship as separate assets that the renderer dispatches to only
 *      when tenant config sets `storefront.theme = "taskfirst"`.
 *
 * If a future change accidentally promotes Task-First into the default
 * (which would re-skin every Mercato tenant), this test fails loudly.
 */
final class TaskFirstThemeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testTaskFirstTemplateExists(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/templates/storefront/page-taskfirst.php';
        $this->assertFileExists($path, 'page-taskfirst.php template must ship with the bespoke Task-First layout');
        $contents = file_get_contents($path) ?: '';
        $this->assertStringContainsString('dir-taskfirst', $contents, 'page-taskfirst.php must set the body theme scope class');
        $this->assertStringContainsString('storefront-taskfirst.css', $contents, 'page-taskfirst.php must link the Task-First overlay stylesheet');
        $this->assertStringContainsString('tf-hero', $contents);
        $this->assertStringContainsString('tf-how', $contents);
        $this->assertStringContainsString('tf-pros', $contents);
        $this->assertStringContainsString('tf-provider-cta', $contents);
    }

    public function testTaskFirstCssExistsAndIsScopedToBodyClass(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/assets/css/storefront-taskfirst.css';
        $this->assertFileExists($path);
        $contents = file_get_contents($path) ?: '';
        // Every rule must be scoped under body.dir-taskfirst so it cannot
        // bleed into other tenants that load storefront-taskfirst.css
        // through some accidental include.
        $this->assertStringContainsString('body.dir-taskfirst', $contents);
        $this->assertMatchesRegularExpression('/--tf-bg:\s*#fff8ee/', $contents, 'Task-First palette base must be the cream #fff8ee');
        $this->assertMatchesRegularExpression('/--tf-peach-deep:\s*#ff8a5b/', $contents, 'Task-First CTA peach must be #ff8a5b');
    }

    public function testRendererDispatchesOnThemeFlag(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/src/Storefront/Renderer.php';
        $contents = file_get_contents($path) ?: '';
        $this->assertStringContainsString("'page-taskfirst.php'", $contents, 'Renderer must reference the bespoke template by filename');
        $this->assertStringContainsString("\$config['theme']", $contents, 'Renderer must read theme from tenant config');
        $this->assertStringContainsString('page.php', $contents, 'Renderer must still fall back to the default page.php template');
    }

    public function testMercatoDefaultsDoNotSetTaskFirstTheme(): void
    {
        // The whole point of the Gigsii-only override: Mercato defaults
        // are untouched. Config::defaults() must not set a `theme` key,
        // otherwise every fresh tenant would render Task-First.
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/src/Storefront/Config.php';
        $contents = file_get_contents($path) ?: '';
        $this->assertStringNotContainsString("'theme'", $contents, 'Storefront\\Config::defaults() must not set a theme — Task-First is a per-tenant override');
        $this->assertStringNotContainsString('"theme"', $contents);
        $this->assertStringNotContainsString('taskfirst', $contents);
    }

    public function testDefaultPageTemplateDoesNotIncludeTaskFirstMarkup(): void
    {
        // page.php is the Mercato default home page template. It must
        // not contain taskfirst CSS or class names — those belong only
        // in page-taskfirst.php so the design is Gigsii-only.
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/templates/storefront/page.php';
        $contents = file_get_contents($path) ?: '';
        $this->assertStringNotContainsString('dir-taskfirst', $contents);
        $this->assertStringNotContainsString('storefront-taskfirst.css', $contents);
        $this->assertStringNotContainsString('tf-hero', $contents);
    }

    public function testGigsiiSeedSetsTaskFirstTheme(): void
    {
        $path = $this->root . '/tools/seed-gigsii-tenant.ps1';
        $contents = file_get_contents($path) ?: '';
        $this->assertStringContainsString('theme = "taskfirst"', $contents, 'Gigsii seed must opt in to the Task-First theme');
        $this->assertStringContainsString('hero_headline = "What needs doing today?"', $contents, 'Gigsii seed must ship the Task-First hero copy');
        $this->assertStringContainsString('primary_cta = "Find me help"', $contents);
    }
}
