<?php

declare(strict_types=1);

namespace Mercato\Enterprise;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

final class Repository
{
    public function __construct(private readonly Resolver $tenantResolver, private readonly Outbox $outbox)
    {
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function provision(array $data): array
    {
        global $wpdb;

        $slug = \sanitize_title((string) ($data['tenant_slug'] ?? 'tenant-' . \time()));
        $displayName = \sanitize_text_field((string) ($data['display_name'] ?? $slug));
        $plan = \sanitize_key((string) ($data['plan_code'] ?? 'starter'));
        $region = \sanitize_text_field((string) ($data['region_code'] ?? 'us-east-1'));
        $table = $wpdb->prefix . 'mercato_tenants';
        $inserted = $wpdb->insert($table, [
            'tenant_slug' => $slug,
            'display_name' => $displayName,
            'plan_code' => $plan,
            'isolation_mode' => 'pooled',
            'region_code' => $region,
            'status' => 'active',
            'blog_id' => isset($data['blog_id']) ? (int) $data['blog_id'] : null,
            'control_plane_id' => (string) ($data['control_plane_id'] ?? 'local-' . $slug),
        ]);

        if ($inserted === false) {
            throw new RuntimeException('Unable to provision tenant: ' . (string) $wpdb->last_error);
        }

        $tenantId = (int) $wpdb->insert_id;
        $this->seedStarterFlags($tenantId);
        $tenant = $this->getTenant($tenantId);
        $this->outbox->publish('mercato.tenant.provisioned.v1', $tenant, (string) $tenantId, $tenantId);

        return $tenant;
    }

    /**
     * @return array<string,mixed>
     */
    public function currentCapabilityToken(): array
    {
        $tenantId = $this->tenantResolver->currentTenantId();
        return [
            'tenant_id' => $tenantId,
            'features' => $this->featureFlags($tenantId),
            'issued_at' => \gmdate('c'),
            'expires_at' => \gmdate('c', \time() + 86400),
            'mode' => 'static-local',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function setFlag(string $featureKey, bool $enabled, ?int $limitValue = null): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_tenant_feature_flags';
        $wpdb->replace($table, [
            'tenant_id' => $tenantId,
            'feature_key' => \sanitize_text_field($featureKey),
            'enabled' => $enabled ? 1 : 0,
            'limit_value' => $limitValue,
        ]);

        $flag = ['tenant_id' => $tenantId, 'feature_key' => $featureKey, 'enabled' => $enabled, 'limit_value' => $limitValue];
        $this->outbox->publish('mercato.tenant.feature.toggled.v1', $flag, $featureKey, $tenantId);

        return $flag;
    }

    /**
     * @param array<string,mixed> $tokens
     * @return array<string,mixed>
     */
    public function setBranding(array $tokens): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_branding_tokens';
        $wpdb->replace($table, [
            'tenant_id' => $tenantId,
            'tokens' => \wp_json_encode($tokens, JSON_THROW_ON_ERROR),
            'updated_by' => \function_exists('get_current_user_id') ? \get_current_user_id() : null,
        ]);

        return ['tenant_id' => $tenantId, 'tokens' => $tokens];
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public function setStorefront(array $config): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_tenant_settings';
        $current = $wpdb->get_row($wpdb->prepare("SELECT `settings`, `version` FROM `{$table}` WHERE `tenant_id` = %d", $tenantId), ARRAY_A);
        $settings = [];
        if (\is_array($current) && !empty($current['settings'])) {
            $decoded = \json_decode((string) $current['settings'], true);
            $settings = \is_array($decoded) ? $decoded : [];
        }

        $settings['storefront'] = $this->sanitizeStorefrontConfig($config);
        $version = \is_array($current) ? ((int) $current['version'] + 1) : 1;

        $wpdb->replace($table, [
            'tenant_id' => $tenantId,
            'version' => $version,
            'settings' => \wp_json_encode($settings, JSON_THROW_ON_ERROR),
            'updated_by' => \function_exists('get_current_user_id') ? \get_current_user_id() : null,
        ]);

        $payload = ['tenant_id' => $tenantId, 'version' => $version, 'storefront' => $settings['storefront']];
        $this->outbox->publish('mercato.tenant.storefront.updated.v1', $payload, (string) $tenantId, $tenantId);

        return $payload;
    }

    /**
     * @return array<string,mixed>
     */
    private function getTenant(int $tenantId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_tenants';
        $tenant = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE tenant_id = %d", $tenantId), ARRAY_A);
        if (!\is_array($tenant)) {
            throw new RuntimeException('Tenant not found after provision.');
        }

        return $tenant;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function featureFlags(int $tenantId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_tenant_feature_flags';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT feature_key, enabled, limit_value, expires_at FROM {$table} WHERE tenant_id = %d", $tenantId), ARRAY_A) ?: [];
        $flags = [];
        foreach ($rows as $row) {
            $flags[(string) $row['feature_key']] = [
                'enabled' => (bool) $row['enabled'],
                'limit_value' => $row['limit_value'] === null ? null : (int) $row['limit_value'],
                'expires_at' => $row['expires_at'],
            ];
        }

        return $flags;
    }

    private function seedStarterFlags(int $tenantId): void
    {
        foreach ([
            'mercato.core',
            'mercato.vendors',
            'mercato.products',
            'mercato.orders',
            'mercato.commissions',
            'mercato.payouts',
            'mercato.messaging',
            'mercato.notifications',
            'mercato.kyc',
            'mercato.enterprise',
            'mercato.integration.stripe_connect',
            'mercato.integration.sendgrid',
            'mercato.integration.aws_s3',
        ] as $feature) {
            $this->setFlagForTenant($tenantId, $feature, true);
        }
    }

    private function setFlagForTenant(int $tenantId, string $featureKey, bool $enabled): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_tenant_feature_flags';
        $wpdb->replace($table, [
            'tenant_id' => $tenantId,
            'feature_key' => $featureKey,
            'enabled' => $enabled ? 1 : 0,
        ]);
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private function sanitizeStorefrontConfig(array $config): array
    {
        $sanitize = static fn (mixed $value): string => \sanitize_text_field((string) $value);
        $clean = [];

        foreach ([
            'brand',
            'mark',
            'title',
            'hero_headline',
            'hero_copy',
            'primary_cta',
            'secondary_cta',
            'positioning_headline',
            'positioning_copy',
            'catalog_headline',
            'catalog_copy',
            'catalog_badge',
            'vendor_headline',
            'vendor_copy',
            'vendor_badge',
            'buyer_headline',
            'buyer_copy',
            'seller_headline',
            'seller_copy',
            'workflow_headline',
            'workflow_copy',
            'footer',
            'item_empty_title',
            'item_empty_copy',
            'item_fallback_copy',
            'item_quantity_label',
            'vendor_status_label',
        ] as $key) {
            if (\array_key_exists($key, $config)) {
                $clean[$key] = $sanitize($config[$key]);
            }
        }

        foreach (['nav', 'metric_labels', 'positioning_cards', 'seller_steps', 'workflow_steps'] as $key) {
            if (!isset($config[$key]) || !\is_array($config[$key])) {
                continue;
            }
            $clean[$key] = \array_map(static function (mixed $item) use ($sanitize): mixed {
                if (!\is_array($item)) {
                    return $sanitize($item);
                }

                $row = [];
                foreach ($item as $itemKey => $itemValue) {
                    $row[$sanitize($itemKey)] = $sanitize($itemValue);
                }
                return $row;
            }, $config[$key]);
        }

        return $clean;
    }
}
