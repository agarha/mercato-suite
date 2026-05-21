<?php

declare(strict_types=1);

namespace Mercato\Core\Audit;

use Mercato\Core\Tenant\Resolver;
use RuntimeException;

final class Writer
{
    public function __construct(private readonly Resolver $tenantResolver)
    {
    }

    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    public function log(string $action, string $entityType, int $entityId, ?array $before = null, ?array $after = null, ?int $tenantId = null): int
    {
        global $wpdb;

        $tenantId ??= $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_audit_log';
        $result = $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'actor_id' => \function_exists('get_current_user_id') ? \get_current_user_id() : null,
            'actor_role' => null,
            'actor_ip' => null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_state' => $before === null ? null : \wp_json_encode($before, JSON_THROW_ON_ERROR),
            'after_state' => $after === null ? null : \wp_json_encode($after, JSON_THROW_ON_ERROR),
            'correlation_id' => null,
        ]);

        if ($result === false) {
            throw new RuntimeException('Unable to write audit log: ' . (string) $wpdb->last_error);
        }

        return (int) $wpdb->insert_id;
    }
}
