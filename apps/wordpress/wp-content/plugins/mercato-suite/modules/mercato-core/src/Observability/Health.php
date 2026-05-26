<?php

declare(strict_types=1);

namespace Mercato\Core\Observability;

use Mercato\Core\DB\Migrator;
use Mercato\Core\ModuleRegistry;
use Mercato\Core\Tenant\Resolver;

final class Health
{
    public function __construct(
        private readonly Resolver $tenantResolver,
        private readonly Migrator $migrator,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function live(): array
    {
        return [
            'service' => 'mercato-suite',
            'status' => 'ok',
            'version' => \defined('MERCATO_SUITE_VERSION') ? \MERCATO_SUITE_VERSION : 'dev',
            'generated_at' => \gmdate('c'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function readiness(): array
    {
        $checks = [
            'database' => $this->database(),
            'migrations' => $this->migrations(),
            'modules' => $this->modules(),
            'outbox' => $this->outbox(),
            'woocommerce' => $this->woocommerce(),
            'integrations' => $this->integrations(),
        ];

        $failed = \array_filter($checks, static fn (array $check): bool => ($check['status'] ?? 'failed') !== 'ok');

        return [
            'service' => 'mercato-suite',
            'status' => $failed === [] ? 'ok' : 'degraded',
            'tenant_id' => $this->tenantResolver->currentTenantId(),
            'version' => \defined('MERCATO_SUITE_VERSION') ? \MERCATO_SUITE_VERSION : 'dev',
            'checks' => $checks,
            'generated_at' => \gmdate('c'),
        ];
    }

    public function prometheus(): string
    {
        $lines = [
            '# HELP mercato_database_up Whether the Mercato database connection is available.',
            '# TYPE mercato_database_up gauge',
            'mercato_database_up ' . ($this->database()['status'] === 'ok' ? '1' : '0'),
        ];

        $migrations = $this->migrations();
        $modules = $this->modules();
        $outbox = $this->outbox();

        $lines[] = '# HELP mercato_migrations_applied_total Number of applied Mercato migrations.';
        $lines[] = '# TYPE mercato_migrations_applied_total gauge';
        $lines[] = 'mercato_migrations_applied_total ' . (int) $migrations['applied_count'];
        $lines[] = '# HELP mercato_modules_loaded Number of Mercato modules loaded by the registry.';
        $lines[] = '# TYPE mercato_modules_loaded gauge';
        $lines[] = 'mercato_modules_loaded ' . (int) $modules['count'];
        $lines[] = '# HELP mercato_outbox_pending Number of unpublished outbox events.';
        $lines[] = '# TYPE mercato_outbox_pending gauge';
        $lines[] = 'mercato_outbox_pending ' . (int) $outbox['pending_count'];
        $lines[] = '# HELP mercato_outbox_dlq Number of dead-lettered outbox events.';
        $lines[] = '# TYPE mercato_outbox_dlq gauge';
        $lines[] = 'mercato_outbox_dlq ' . (int) $outbox['dlq_count'];
        $lines[] = '# HELP mercato_outbox_published_total Number of published outbox events.';
        $lines[] = '# TYPE mercato_outbox_published_total counter';
        $lines[] = 'mercato_outbox_published_total ' . $this->countWhere($GLOBALS['wpdb']->prefix . 'mercato_event_outbox', "`status` = 'published'");

        foreach ($this->statusCounts($GLOBALS['wpdb']->prefix . 'mercato_vendors', 'status') as $status => $count) {
            $lines[] = 'mercato_vendors_total{status="' . $this->label($status) . '"} ' . $count;
        }
        foreach ($this->statusCounts($GLOBALS['wpdb']->prefix . 'mercato_products', 'status') as $status => $count) {
            $lines[] = 'mercato_products_total{status="' . $this->label($status) . '"} ' . $count;
        }
        foreach ($this->statusCounts($GLOBALS['wpdb']->prefix . 'mercato_suborders', 'payment_status') as $status => $count) {
            $lines[] = 'mercato_suborders_total{payment_status="' . $this->label($status) . '"} ' . $count;
        }
        foreach ($this->statusCounts($GLOBALS['wpdb']->prefix . 'mercato_payout_batches', 'status') as $status => $count) {
            $lines[] = 'mercato_payout_batches_total{status="' . $this->label($status) . '"} ' . $count;
        }
        foreach ($this->statusCounts($GLOBALS['wpdb']->prefix . 'mercato_notification_deliveries', 'status') as $status => $count) {
            $lines[] = 'mercato_notification_deliveries_total{status="' . $this->label($status) . '"} ' . $count;
        }

        return \implode("\n", $lines) . "\n";
    }

    /**
     * @return array<string,mixed>
     */
    private function database(): array
    {
        global $wpdb;

        $ok = isset($wpdb) && (string) $wpdb->get_var('SELECT 1') === '1';
        return ['status' => $ok ? 'ok' : 'failed'];
    }

    /**
     * @return array<string,mixed>
     */
    private function migrations(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_migrations';
        $applied = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");

        return [
            'status' => $this->migrator->verify() && $applied > 0 ? 'ok' : 'failed',
            'applied_count' => $applied,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function modules(): array
    {
        $registry = new ModuleRegistry(\MERCATO_SUITE_DIR . '/modules');
        $registry->discover();
        $ordered = $registry->ordered();

        return [
            'status' => \count($ordered) >= 29 ? 'ok' : 'degraded',
            'count' => \count($ordered),
            'slugs' => \array_map(static fn ($manifest): string => $manifest->slug, $ordered),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function outbox(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_event_outbox';
        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE `status` IN ('pending','publishing')");
        $dlq = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE `status` = 'dlq'");

        return [
            'status' => $dlq === 0 ? 'ok' : 'degraded',
            'pending_count' => $pending,
            'dlq_count' => $dlq,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function woocommerce(): array
    {
        $hpos = false;
        if (\class_exists('\\Automattic\\WooCommerce\\Utilities\\OrderUtil')) {
            $hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }

        return [
            'status' => \defined('WC_VERSION') && $hpos ? 'ok' : 'degraded',
            'version' => \defined('WC_VERSION') ? \WC_VERSION : null,
            'hpos_enabled' => $hpos,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function integrations(): array
    {
        return [
            'status' => 'ok',
            'stripe_mode' => $this->configuredMode('STRIPE_SECRET_KEY', 'sk_test_', 'test-fixture'),
            'sendgrid_mode' => $this->configuredMode('SENDGRID_API_KEY', 'SG.', 'test-fixture'),
            's3_endpoint' => (string) (\getenv('MERCATO_S3_ENDPOINT') ?: ''),
            'mail_host' => (string) (\getenv('MERCATO_MAIL_HOST') ?: ''),
        ];
    }

    private function configuredMode(string $env, string $livePrefix, string $fallback): string
    {
        $value = (string) \getenv($env);
        if ($value === '' || \str_contains($value, 'replace_me')) {
            return $fallback;
        }

        return \str_starts_with($value, $livePrefix) ? 'test-api' : 'configured';
    }

    private function countWhere(string $table, string $where): int
    {
        global $wpdb;

        if (!$this->tableExists($table)) {
            return 0;
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE {$where}");
    }

    /**
     * @return array<string,int>
     */
    private function statusCounts(string $table, string $column): array
    {
        global $wpdb;

        if (!$this->tableExists($table)) {
            return [];
        }

        $rows = $wpdb->get_results("SELECT `{$column}` AS status, COUNT(*) AS count FROM `{$table}` GROUP BY `{$column}`", ARRAY_A) ?: [];
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    private function tableExists(string $table): bool
    {
        global $wpdb;

        return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private function label(string $value): string
    {
        return \str_replace(["\\", "\n", '"'], ["\\\\", "\\n", '\\"'], $value);
    }
}
