<?php

declare(strict_types=1);

namespace Mercato\Reports;

use Mercato\Core\Tenant\Resolver;
use RuntimeException;

final class Repository
{
    public function __construct(private readonly Resolver $tenantResolver)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $vendors = $wpdb->prefix . 'mercato_vendors';
        $suborders = $wpdb->prefix . 'mercato_suborders';
        $commissions = $wpdb->prefix . 'mercato_commissions';
        $products = $wpdb->prefix . 'mercato_products';
        $payoutItems = $wpdb->prefix . 'mercato_payout_items';
        $reconciliation = $wpdb->prefix . 'mercato_reconciliation_runs';

        $vendorCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$vendors} WHERE tenant_id = %d", $tenantId));
        $productCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$products} WHERE tenant_id = %d", $tenantId));
        $orderCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$suborders} WHERE tenant_id = %d", $tenantId));
        $gmvMinor = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total_minor), 0) FROM {$suborders} WHERE tenant_id = %d", $tenantId));
        $takeMinor = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(commission_minor), 0) FROM {$commissions} WHERE tenant_id = %d", $tenantId));
        $payoutVolumeMinor = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount_minor), 0) FROM {$payoutItems} WHERE tenant_id = %d AND status = 'succeeded'", $tenantId));
        $latestReconciliation = $wpdb->get_row($wpdb->prepare(
            "SELECT status, drift_minor, report_url, created_at FROM {$reconciliation} WHERE tenant_id = %d ORDER BY created_at DESC LIMIT 1",
            $tenantId
        ), ARRAY_A);

        return [
            'tenant_id' => $tenantId,
            'currency' => 'USD',
            'gmv_minor' => $gmvMinor,
            'take_minor' => $takeMinor,
            'aov_minor' => $orderCount > 0 ? (int) \round($gmvMinor / $orderCount) : 0,
            'payout_volume_minor' => $payoutVolumeMinor,
            'vendor_count' => $vendorCount,
            'product_count' => $productCount,
            'suborder_count' => $orderCount,
            'latest_reconciliation' => $latestReconciliation ?: null,
            'generated_at' => \gmdate('c'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function vendorSummary(?int $vendorId = null): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $suborders = $wpdb->prefix . 'mercato_suborders';
        $commissions = $wpdb->prefix . 'mercato_commissions';
        $where = 's.tenant_id = %d';
        $args = [$tenantId];
        if ($vendorId !== null) {
            $where .= ' AND s.vendor_id = %d';
            $args[] = $vendorId;
        }

        $sql = $wpdb->prepare(
            "SELECT s.vendor_id, COUNT(*) suborder_count, COALESCE(SUM(s.total_minor), 0) gmv_minor, COALESCE(SUM(c.commission_minor), 0) take_minor, COALESCE(SUM(c.vendor_net_minor), 0) vendor_net_minor
            FROM {$suborders} s
            LEFT JOIN {$commissions} c ON c.tenant_id = s.tenant_id AND c.suborder_id = s.suborder_id
            WHERE {$where}
            GROUP BY s.vendor_id
            ORDER BY gmv_minor DESC",
            ...$args
        );

        return ['tenant_id' => $tenantId, 'vendors' => $wpdb->get_results($sql, ARRAY_A) ?: []];
    }

    /**
     * @return array<string,mixed>
     */
    public function createCsvExport(string $reportType): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $rows = $reportType === 'vendors' ? $this->vendorSummary()['vendors'] : [$this->dashboard()];
        $fileName = 'mercato-' . $reportType . '-' . \gmdate('Ymd-His') . '.csv';
        $upload = \wp_upload_bits($fileName, null, $this->toCsv($rows));
        if (!empty($upload['error'])) {
            throw new RuntimeException((string) $upload['error']);
        }

        $table = $wpdb->prefix . 'mercato_report_exports';
        $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'report_type' => $reportType,
            'status' => 'ready',
            'file_name' => $fileName,
            'mime_type' => 'text/csv',
            'row_count' => \count($rows),
            'created_by' => \function_exists('get_current_user_id') ? \get_current_user_id() : null,
        ]);

        return [
            'export_id' => (int) $wpdb->insert_id,
            'file_name' => $fileName,
            'url' => (string) $upload['url'],
            'row_count' => \count($rows),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function toCsv(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $handle = \fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Unable to create CSV buffer.');
        }

        \fputcsv($handle, \array_keys($rows[0]));
        foreach ($rows as $row) {
            \fputcsv($handle, $row);
        }

        \rewind($handle);
        $csv = \stream_get_contents($handle);
        \fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
