<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ServiceOpsModuleTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testModuleDeclaresGigsiiSoftLaunchCapabilities(): void
    {
        $manifest = json_decode((string) file_get_contents($this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-service-ops/module.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('mercato-service-ops', $manifest['slug']);
        self::assertContains('mercato.booking.created.v1', $manifest['provides_events']);
        self::assertContains('mercato.job.status_changed.v1', $manifest['provides_events']);
        self::assertContains('mercato.estimate.accepted.v1', $manifest['provides_events']);
        self::assertContains('wp_mercato_booking_requests', $manifest['tables']);
        self::assertContains('wp_mercato_jobs', $manifest['tables']);
        self::assertContains('wp_mercato_referrals', $manifest['tables']);
    }

    public function testMigrationCoversBookingJobsEstimatesAndReferralTables(): void
    {
        $migration = (string) file_get_contents($this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-service-ops/migrations/0001_service_ops.sql');

        foreach ([
            'mercato_booking_requests',
            'mercato_jobs',
            'mercato_job_status_history',
            'mercato_leads',
            'mercato_estimates',
            'mercato_estimate_line_items',
            'mercato_referrals',
            '`tenant_id` BIGINT UNSIGNED NOT NULL',
            "ENUM('scheduled','assigned','enroute','inprogress','completed','closed','cancelled')",
            'UNIQUE KEY `uk_referral`',
        ] as $needle) {
            self::assertStringContainsString($needle, $migration);
        }
    }

    public function testRepositoryEnforcesCoreStateAndConflictRequirements(): void
    {
        $repository = (string) file_get_contents($this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-service-ops/src/Repository.php');

        self::assertStringContainsString('INVALID_STATUS_TRANSITION', $repository);
        self::assertStringContainsString('ASSIGNMENT_CONFLICT', $repository);
        self::assertStringContainsString('mercato.booking.created.v1', $repository);
        self::assertStringContainsString('mercato.job.created.v1', $repository);
        self::assertStringContainsString('mercato.referral.accrued.v1', $repository);
        self::assertStringContainsString("hash('sha256'", $repository);
    }

    public function testGigsiiSeedKeepsSoftLaunchFlagsOff(): void
    {
        $seed = (string) file_get_contents($this->root . '/tools/seed-gigsii-tenant.ps1');

        foreach ([
            '"gigsii.otp" = $false',
            '"gigsii.monetization" = $false',
            '"gigsii.task_posting" = $false',
            '"gigsii.referral_redemption" = $false',
            '"mercato.ai" = $false',
        ] as $needle) {
            self::assertStringContainsString($needle, $seed);
        }
    }
}
