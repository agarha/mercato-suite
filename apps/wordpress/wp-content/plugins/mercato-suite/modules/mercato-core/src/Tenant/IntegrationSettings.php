<?php

declare(strict_types=1);

namespace Mercato\Core\Tenant;

final class IntegrationSettings
{
    public const PROVIDERS = [
        'stripe',
        'sendgrid',
        's3',
        'tax',
        'search',
        'sms',
        'kyc',
    ];

    public function __construct(private readonly Resolver $tenantResolver)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function get(string $providerKey): array
    {
        global $wpdb;

        $providerKey = $this->normalizeProvider($providerKey);
        $table = $wpdb->prefix . 'mercato_tenant_integrations';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `provider_key` = %s",
            $this->tenantResolver->currentTenantId(),
            $providerKey
        ), \ARRAY_A);

        if (!\is_array($row)) {
            return [
                'tenant_id' => $this->tenantResolver->currentTenantId(),
                'provider_key' => $providerKey,
                'status' => 'disabled',
                'public_config' => [],
                'secret_refs' => [],
            ];
        }

        $row['public_config'] = $this->decodeJson((string) $row['public_config']);
        $row['secret_refs'] = $this->decodeJson((string) $row['secret_refs']);

        return $row;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function list(): array
    {
        return \array_map(fn (string $provider): array => $this->get($provider), self::PROVIDERS);
    }

    /**
     * @param array<string,mixed> $publicConfig
     * @param array<string,mixed> $secretRefs
     * @return array<string,mixed>
     */
    public function set(string $providerKey, string $status, array $publicConfig, array $secretRefs): array
    {
        global $wpdb;

        $providerKey = $this->normalizeProvider($providerKey);
        $status = \in_array($status, ['disabled', 'test', 'live'], true) ? $status : 'disabled';
        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_tenant_integrations';
        $wpdb->replace($table, [
            'tenant_id' => $tenantId,
            'provider_key' => $providerKey,
            'status' => $status,
            'public_config' => \wp_json_encode($this->sanitizeMap($publicConfig), JSON_THROW_ON_ERROR),
            'secret_refs' => \wp_json_encode($this->sanitizeMap($secretRefs), JSON_THROW_ON_ERROR),
            'updated_by' => \function_exists('get_current_user_id') ? \get_current_user_id() : null,
        ]);

        return $this->get($providerKey);
    }

    private function normalizeProvider(string $providerKey): string
    {
        return \strtolower(\preg_replace('/[^a-zA-Z0-9_.-]+/', '', $providerKey) ?? '');
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJson(string $json): array
    {
        $decoded = \json_decode($json, true);
        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function sanitizeMap(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (\is_array($value) || \is_object($value)) {
                continue;
            }
            $clean[(string) \preg_replace('/[^a-zA-Z0-9_.-]+/', '', (string) $key)] = \sanitize_text_field((string) $value);
        }

        return $clean;
    }
}
