<?php

declare(strict_types=1);

namespace Mercato\Core\Tenant;

final class Resolver
{
    private ?int $resolvedTenantId = null;

    public function currentTenantId(): int
    {
        if ($this->resolvedTenantId !== null) {
            return $this->resolvedTenantId;
        }

        if (\defined('MERCATO_TEST_TENANT_ID')) {
            return $this->resolvedTenantId = (int) \MERCATO_TEST_TENANT_ID;
        }

        $tenantId = $this->fromTrustedHeader()
            ?? $this->fromPathPrefix()
            ?? $this->fromHost()
            ?? $this->fromBlog()
            ?? 1;

        return $this->resolvedTenantId = $tenantId;
    }

    public function reset(): void
    {
        $this->resolvedTenantId = null;
    }

    private function fromTrustedHeader(): ?int
    {
        if (!$this->trustedHeaderEnabled()) {
            return null;
        }

        $value = (string) ($_SERVER['HTTP_X_MERCATO_TENANT_ID'] ?? '');
        if ($value === '' || !\ctype_digit($value)) {
            return null;
        }

        $tenantId = (int) $value;
        return $tenantId > 0 ? $tenantId : null;
    }

    private function trustedHeaderEnabled(): bool
    {
        if (\defined('MERCATO_TRUST_TENANT_HEADER')) {
            return (bool) \MERCATO_TRUST_TENANT_HEADER;
        }

        $value = \getenv('MERCATO_TRUST_TENANT_HEADER');
        return \in_array(\strtolower((string) $value), ['1', 'true', 'yes'], true);
    }

    private function fromPathPrefix(): ?int
    {
        $path = (string) \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if ($path === '') {
            return null;
        }

        if (\preg_match('#^/t/([a-z0-9][a-z0-9-]{0,63})(?:/|$)#i', $path, $matches) !== 1) {
            return null;
        }

        return $this->tenantIdForSlug(\strtolower($matches[1]));
    }

    private function fromHost(): ?int
    {
        $host = $this->normalizeHost((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return null;
        }

        $mapped = $this->tenantIdForDomain($host);
        if ($mapped !== null) {
            return $mapped;
        }

        $parts = \explode('.', $host);
        if (\count($parts) < 3 || $parts[0] === 'www') {
            return null;
        }

        return $this->tenantIdForSlug($parts[0]);
    }

    private function fromBlog(): ?int
    {
        if (\function_exists('get_current_blog_id')) {
            $blogId = (int) \get_current_blog_id();
            return $blogId > 0 ? $blogId : null;
        }

        return null;
    }

    private function normalizeHost(string $host): string
    {
        $host = \strtolower(\trim($host));
        if ($host === '') {
            return '';
        }

        return \preg_replace('/:\d+$/', '', $host) ?? $host;
    }

    private function tenantIdForSlug(string $slug): ?int
    {
        $map = $this->testTenantMap();
        if (isset($map['slugs'][$slug])) {
            return (int) $map['slugs'][$slug];
        }

        if (!isset($GLOBALS['wpdb']) || !\is_object($GLOBALS['wpdb'])) {
            return null;
        }

        $wpdb = $GLOBALS['wpdb'];
        $table = $wpdb->prefix . 'mercato_tenants';
        $id = $wpdb->get_var($wpdb->prepare("SELECT `tenant_id` FROM `{$table}` WHERE `tenant_slug` = %s AND `status` = 'active' LIMIT 1", $slug));
        return $id === null ? null : (int) $id;
    }

    private function tenantIdForDomain(string $host): ?int
    {
        $map = $this->testTenantMap();
        if (isset($map['domains'][$host])) {
            return (int) $map['domains'][$host];
        }

        if (!isset($GLOBALS['wpdb']) || !\is_object($GLOBALS['wpdb'])) {
            return null;
        }

        $wpdb = $GLOBALS['wpdb'];
        $table = $wpdb->prefix . 'mercato_tenant_domains';
        $path = (string) \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT `tenant_id`, `path_prefix` FROM `{$table}` WHERE `domain` = %s AND `status` = 'active' ORDER BY `is_primary` DESC, LENGTH(COALESCE(`path_prefix`, '')) DESC",
            $host
        ), \ARRAY_A) ?: [];

        foreach ($rows as $row) {
            $prefix = (string) ($row['path_prefix'] ?? '');
            if ($prefix === '' || \str_starts_with($path, $prefix)) {
                return (int) $row['tenant_id'];
            }
        }

        return null;
    }

    /**
     * @return array{slugs:array<string,int>,domains:array<string,int>}
     */
    private function testTenantMap(): array
    {
        $map = $GLOBALS['mercato_test_tenants'] ?? [];
        return [
            'slugs' => \is_array($map['slugs'] ?? null) ? $map['slugs'] : [],
            'domains' => \is_array($map['domains'] ?? null) ? $map['domains'] : [],
        ];
    }
}
