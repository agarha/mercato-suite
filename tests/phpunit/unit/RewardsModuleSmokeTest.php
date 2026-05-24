<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Pins the mercato-rewards module wiring:
 *   - independent module manifest + migration present
 *   - Ledger has earn/spend/refund/adjust verbs
 *   - Repository exposes config + balance + ledger + topEarners
 *   - Admin UI registers the two submenu pages
 *   - Signup orchestrator grants the signup bonus
 *   - ServiceOps createBid charges Sparks
 */
final class RewardsModuleSmokeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testModuleManifestExists(): void
    {
        $path = $this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-rewards/module.json';
        $this->assertFileExists($path, 'mercato-rewards module manifest must exist.');
        $data = json_decode(file_get_contents($path), true);
        $this->assertSame('mercato-rewards', $data['slug']);
        $this->assertContains('mercato-vendors@^0.1', $data['requires'], 'Rewards must depend on vendors.');
        $this->assertContains('wp_mercato_user_balances', $data['tables']);
        $this->assertContains('wp_mercato_reward_ledger', $data['tables']);
        $this->assertContains('wp_mercato_reward_config', $data['tables']);
    }

    public function testMigrationDefinesAllThreeTables(): void
    {
        $sql = file_get_contents($this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-rewards/migrations/0001_rewards.sql');
        $this->assertStringContainsString('mercato_reward_config', $sql);
        $this->assertStringContainsString('mercato_user_balances', $sql);
        $this->assertStringContainsString('mercato_reward_ledger', $sql);
        // Currency name is configurable
        $this->assertStringContainsString('pro_currency_name', $sql);
        $this->assertStringContainsString('customer_currency_name', $sql);
        // Referral extension preserves single-tier safety
        $this->assertStringContainsString("ENUM('pro_to_pro','pro_to_customer','customer_to_customer')", $sql, 'Referral kinds must be single-tier only.');
    }

    public function testLedgerHasFourVerbs(): void
    {
        $src = file_get_contents($this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-rewards/src/Ledger.php');
        foreach (['public function earn', 'public function spend', 'public function refund', 'public function adjust'] as $marker) {
            $this->assertStringContainsString($marker, $src, "Ledger must expose $marker.");
        }
        $this->assertStringContainsString('INSUFFICIENT_BALANCE', $src, 'Spend must reject when balance is too low.');
        $this->assertStringContainsString('START TRANSACTION', $src, 'Ledger writes must be transactional.');
    }

    public function testRepositoryExposesReadAndConfigApis(): void
    {
        $src = file_get_contents($this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-rewards/src/Repository.php');
        foreach (['public function config', 'public function setConfig', 'public function balance', 'public function ledger', 'public function topEarners'] as $marker) {
            $this->assertStringContainsString($marker, $src, "Repository must expose $marker.");
        }
        $this->assertStringContainsString('DEFAULT_CONFIG', $src, 'Default config map keeps the schema in sync.');
    }

    public function testAdminPagesRegisterUnderMercatoMenu(): void
    {
        $src = file_get_contents($this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-rewards/src/AdminPages.php');
        $this->assertStringContainsString('mercato-rewards-config', $src);
        $this->assertStringContainsString('mercato-rewards-ledger', $src);
        $this->assertStringContainsString('handleSaveConfig', $src);
        $this->assertStringContainsString('handleAdjust', $src);
    }

    public function testSignupOrchestratorGrantsSignupBonus(): void
    {
        $src = file_get_contents($this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-vendors/src/Signup.php');
        $this->assertStringContainsString("'signup_bonus'", $src, 'Signup must call the rewards ledger with reason=signup_bonus.');
        $this->assertStringContainsString('signup_bonus_sparks', $src, 'Signup reads the configurable bonus amount.');
        $this->assertStringContainsString('Mercato\\\\Rewards\\\\Ledger', $src, 'Signup looks up the rewards Ledger via DI.');
    }

    public function testServiceOpsChargesSparksOnBid(): void
    {
        $src = file_get_contents($this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-service-ops/src/Repository.php');
        $this->assertStringContainsString('chargeBidSparks', $src, 'ServiceOps must charge Sparks before recording a bid.');
        $this->assertStringContainsString('SPARKS_INSUFFICIENT', $src, 'Insufficient balance must surface a stable error code.');
        $this->assertStringContainsString('premium_bid_threshold_minor', $src, 'Premium bids cost more per the configured threshold.');
    }

    public function testRewardsModuleIsIndependent(): void
    {
        // The module's namespace must be its own; nothing in core or vendors should
        // reference Rewards\* unconditionally — all lookups go through Container::has
        // so the module remains optional.
        foreach ([
            '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-vendors/src/Signup.php',
            '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-service-ops/src/Repository.php',
        ] as $rel) {
            $src = file_get_contents($this->root . $rel);
            $this->assertStringContainsString("container->has", $src, "Caller $rel must guard rewards lookup with container->has().");
        }
    }
}
