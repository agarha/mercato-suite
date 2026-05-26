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
        self::assertContains('mercato.referral.redeemed.v1', $manifest['provides_events']);
        self::assertContains('mercato.service_request.created.v1', $manifest['provides_events']);
        self::assertContains('mercato.service_bid.accepted.v1', $manifest['provides_events']);
        self::assertContains('wp_mercato_booking_requests', $manifest['tables']);
        self::assertContains('wp_mercato_jobs', $manifest['tables']);
        self::assertContains('wp_mercato_referrals', $manifest['tables']);
        self::assertContains('wp_mercato_service_requests', $manifest['tables']);
        self::assertContains('wp_mercato_service_bids', $manifest['tables']);
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

        $biddingMigration = (string) file_get_contents($this->root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-service-ops/migrations/0002_request_bidding.sql');
        foreach ([
            'mercato_service_requests',
            'mercato_service_bids',
            "ENUM('sealed_bid','open_auction')",
            "ENUM('open','awarded','cancelled','expired')",
            'UNIQUE KEY `uk_request_vendor`',
        ] as $needle) {
            self::assertStringContainsString($needle, $biddingMigration);
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
        self::assertStringContainsString('redeemReferral', $repository);
        self::assertStringContainsString('mercato.referral.redeemed.v1', $repository);
        self::assertStringContainsString('createServiceRequest', $repository);
        self::assertStringContainsString('createBid', $repository);
        self::assertStringContainsString('acceptBid', $repository);
        self::assertStringContainsString('mercato.service_bid.accepted.v1', $repository);
        self::assertStringContainsString("hash('sha256'", $repository);
    }

    public function testGigsiiSeedKeepsGuardedLaunchFlags(): void
    {
        $seed = (string) file_get_contents($this->root . '/tools/seed-gigsii-tenant.ps1');

        foreach ([
            '"gigsii.otp" = $true',
            '"gigsii.monetization" = $true',
            '"gigsii.task_posting" = $true',
            '"gigsii.referral_redemption" = $true',
            '"mercato.ai" = $true',
            '"mercato.integration.stripe" = $true',
            '"mercato.integration.paypal" = $true',
            '"mercato.subscriptions" = $true',
        ] as $needle) {
            self::assertStringContainsString($needle, $seed);
        }
    }
}
