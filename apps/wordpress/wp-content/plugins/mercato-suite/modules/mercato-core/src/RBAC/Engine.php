<?php

declare(strict_types=1);

namespace Mercato\Core\RBAC;

use Mercato\Core\Tenant\Resolver;

final class Engine
{
    /** @var array<string,bool> */
    private array $cache = [];

    public function __construct(private readonly Resolver $tenantResolver)
    {
    }

    public function userCan(string $capability, ?int $tenantId = null, ?int $resourceOwnerId = null, ?int $userId = null): bool
    {
        global $wpdb;

        $tenantId ??= $this->tenantResolver->currentTenantId();
        $userId ??= \function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;

        if ($userId < 1) {
            return false;
        }

        if (\function_exists('current_user_can') && \current_user_can('manage_options')) {
            return true;
        }

        $key = "{$tenantId}:{$userId}:{$capability}:{$resourceOwnerId}";
        if (\array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $roles = $wpdb->prefix . 'mercato_rbac_user_roles';
        $roleCaps = $wpdb->prefix . 'mercato_rbac_role_caps';
        $caps = $wpdb->prefix . 'mercato_rbac_capabilities';

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$roles}` ur
                 INNER JOIN `{$roleCaps}` rc ON rc.`role_id` = ur.`role_id`
                 INNER JOIN `{$caps}` c ON c.`capability_id` = rc.`capability_id`
                 WHERE ur.`tenant_id` = %d AND ur.`user_id` = %d AND c.`capability_slug` = %s",
                $tenantId,
                $userId,
                $capability
            )
        );

        return $this->cache[$key] = $count > 0;
    }
}
