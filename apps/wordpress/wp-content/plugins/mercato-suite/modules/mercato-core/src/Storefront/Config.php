<?php

declare(strict_types=1);

namespace Mercato\Core\Storefront;

/**
 * Tenant-aware storefront configuration.
 *
 * Loads the per-tenant config blob stored in
 * `wp_mercato_tenant_settings.settings JSON.storefront` and merges it
 * deeply on top of a baseline default.
 *
 * Any string value containing the placeholder `__TENANT_HOME__` is
 * substituted with `/t/<tenant_slug>` at the end of forTenant().
 */
final class Config
{
    /**
     * @return array<string,mixed>
     */
    public function forTenant(int $tenantId): array
    {
        global $wpdb;

        $config = $this->defaults();
        $config['tenant_id'] = $tenantId;
        $config['tenant_slug'] = $this->slugForTenant($tenantId);

        if (isset($wpdb) && \is_object($wpdb)) {
            $table = $wpdb->prefix . 'mercato_tenant_settings';
            $settingsJson = $wpdb->get_var($wpdb->prepare("SELECT `settings` FROM `{$table}` WHERE `tenant_id` = %d", $tenantId));
            if (\is_string($settingsJson) && $settingsJson !== '') {
                $settings = \json_decode($settingsJson, true);
                if (\is_array($settings) && isset($settings['storefront']) && \is_array($settings['storefront'])) {
                    $config = $this->merge($config, $settings['storefront']);
                    $config['tenant_id'] = $tenantId;
                    $config['tenant_slug'] = $this->slugForTenant($tenantId);
                }
            }
        }

        return $this->resolveTenantPlaceholders($config);
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    public function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (\is_array($value) && isset($base[$key]) && \is_array($base[$key])) {
                $base[$key] = $this->merge($base[$key], $value);
                continue;
            }
            $base[$key] = $value;
        }
        return $base;
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public function resolveTenantPlaceholders(array $config): array
    {
        $home = '/t/' . ($config['tenant_slug'] ?? 'gigsii');
        return $this->walkAndReplace($config, '__TENANT_HOME__', $home);
    }

    /**
     * @param array<string,mixed> $node
     * @return array<string,mixed>
     */
    private function walkAndReplace(array $node, string $needle, string $replacement): array
    {
        foreach ($node as $key => $value) {
            if (\is_array($value)) {
                $node[$key] = $this->walkAndReplace($value, $needle, $replacement);
                continue;
            }
            if (\is_string($value) && \str_contains($value, $needle)) {
                $node[$key] = \str_replace($needle, $replacement, $value);
            }
        }
        return $node;
    }

    private function slugForTenant(int $tenantId): string
    {
        global $wpdb;
        if (!isset($wpdb) || !\is_object($wpdb)) {
            return 'gigsii';
        }
        $table = $wpdb->prefix . 'mercato_tenants';
        $slug = (string) $wpdb->get_var($wpdb->prepare("SELECT `tenant_slug` FROM `{$table}` WHERE `tenant_id` = %d", $tenantId));
        return $slug !== '' ? $slug : 'gigsii';
    }

    /**
     * @return array<string,mixed>
     */
    public function defaults(): array
    {
        return [
            'tenant_id' => 1,
            'tenant_slug' => 'gigsii',
            'brand' => 'Mercato',
            'mark' => 'M',
            'title' => 'Mercato Marketplace Demo',
            'hero_headline' => 'Multi-vendor marketplace operations, packaged for tenants.',
            'hero_copy' => 'Mercato gives each tenant a managed marketplace with vendor onboarding, catalog publishing, multi-vendor order splitting, commissions, payouts, reconciliation, notifications, and media storage.',
            'primary_cta' => 'Open admin console',
            'secondary_cta' => 'Open vendor console',
            'positioning_headline' => 'Why this stands out',
            'positioning_copy' => 'Mercato is positioned as a hosted marketplace operating system, not only a single-site vendor plugin.',
            'catalog_headline' => 'Buyer marketplace',
            'catalog_copy' => 'A real storefront-style catalog loaded from vendor-owned Mercato product records.',
            'catalog_badge' => 'Multi-vendor catalog',
            'vendor_headline' => 'Vendor directory',
            'vendor_copy' => 'Approved sellers with payout onboarding and tenant-scoped store identity.',
            'vendor_badge' => 'KYC + Stripe Connect',
            'buyer_headline' => 'Buyer checkout and account',
            'buyer_copy' => 'Demo of what a buyer sees: cart economics, multi-vendor fulfillment, refund state, and tracking.',
            'seller_headline' => 'Seller portal experience',
            'seller_copy' => 'Public preview of the vendor workflow backed by the same admin/vendor APIs.',
            'workflow_headline' => 'Marketplace workflow',
            'workflow_copy' => 'The local E2E path validates the operational flow behind this storefront.',
            'footer' => 'Local Mercato Docker demo',
            'item_empty_title' => 'No active demo products yet',
            'item_empty_copy' => 'Run tools/seed-demo-data.ps1 to populate realistic vendors and products.',
            'item_fallback_copy' => 'Curated marketplace product ready for vendor fulfillment.',
            'item_quantity_label' => 'in stock',
            'vendor_status_label' => 'Stripe connected',
            'nav' => [
                ['href' => '__TENANT_HOME__', 'label' => 'Home'],
                ['href' => '__TENANT_HOME__/services', 'label' => 'Services'],
                ['href' => '__TENANT_HOME__/providers', 'label' => 'Providers'],
                ['href' => '__TENANT_HOME__/requests/new', 'label' => 'Post request'],
                ['href' => '__TENANT_HOME__/account', 'label' => 'Account'],
                ['href' => '/wp-admin/admin.php?page=mercato-admin', 'label' => 'Admin'],
            ],
            'metric_labels' => [
                'vendors' => 'Approved vendors',
                'products' => 'Active products',
                'orders' => 'Suborders processed',
                'take' => 'Platform fees tracked',
            ],
            'positioning_cards' => [
                ['eyebrow' => '01', 'title' => 'Multi-tenant by design', 'copy' => 'One platform can host many tenant marketplaces with tenant-aware data, audit, metrics, and controls.'],
                ['eyebrow' => '02', 'title' => 'Finance-grade operations', 'copy' => 'Stripe Connect, commissions, payout batches, refund reversals, reconciliation, and trial balance evidence.'],
                ['eyebrow' => '03', 'title' => 'Vendor + buyer workflows', 'copy' => 'Vendor onboarding, catalog, media, suborders, tracking, notifications, and buyer account visibility.'],
                ['eyebrow' => '04', 'title' => 'Portable hosting path', 'copy' => 'Start on Hetzner with Docker, then move to AWS/Kubernetes when sales justify enterprise scale.'],
            ],
            'seller_steps' => [
                ['eyebrow' => 'Apply', 'title' => 'Storefront onboarding', 'copy' => 'Business profile, return policy, KYC, payout account, and tenant approval.'],
                ['eyebrow' => 'Sell', 'title' => 'Catalog workspace', 'copy' => 'Create products, SKU, price, stock, media upload, and WooCommerce projection.'],
                ['eyebrow' => 'Fulfill', 'title' => 'Suborders and tracking', 'copy' => 'Vendors see only their own suborders, update shipment status, and track refunds.'],
                ['eyebrow' => 'Get paid', 'title' => '__PAYOUT_SUMMARY__', 'copy' => 'Commission, ledger, payout batch, and Stripe transfer evidence are linked.'],
                ['eyebrow' => 'Message', 'title' => '__NOTIFICATION_SUMMARY__', 'copy' => 'Notifications are delivered through the local mail/event pipeline.'],
                ['eyebrow' => 'Operate', 'title' => 'Reports and audit', 'copy' => 'Tenant dashboards, reconciliation, audit log, and outbox health are visible in admin.'],
            ],
            'workflow_steps' => [
                ['eyebrow' => '01', 'title' => 'Onboard vendors', 'copy' => 'Register, review, approve, reject, suspend, and track KYC/payout readiness.'],
                ['eyebrow' => '02', 'title' => 'Publish catalog', 'copy' => 'Products are owned by vendors and projected into WooCommerce for checkout.'],
                ['eyebrow' => '03', 'title' => 'Split orders', 'copy' => 'Parent Woo orders become vendor suborders with tax, shipping, discount, and tracking allocation.'],
                ['eyebrow' => '04', 'title' => 'Pay and reconcile', 'copy' => 'Stripe Connect payouts, commission reversals, reports, and trial balance evidence stay linked.'],
            ],
        ];
    }
}
