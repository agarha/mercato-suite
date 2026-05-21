<?php

declare(strict_types=1);

namespace Mercato\Core\Idempotency;

use Mercato\Core\Tenant\Resolver;
use RuntimeException;

final class Store
{
    public function __construct(private readonly Resolver $tenantResolver)
    {
    }

    /**
     * @return array{status_code:int,response_body:string}|null
     */
    public function find(string $endpoint, string $key, ?int $userId = null, ?int $tenantId = null): ?array
    {
        global $wpdb;

        $tenantId ??= $this->tenantResolver->currentTenantId();
        $userId ??= $this->currentUserId();
        $table = $wpdb->prefix . 'mercato_idempotency';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT `status_code`, `response_body` FROM `{$table}` WHERE `tenant_id` = %d AND `user_id` = %d AND `endpoint` = %s AND `idempotency_key` = %s AND `expires_at` > UTC_TIMESTAMP(3)",
                $tenantId,
                $userId,
                $endpoint,
                $key
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return [
            'status_code' => (int) $row['status_code'],
            'response_body' => (string) $row['response_body'],
        ];
    }

    public function remember(string $endpoint, string $key, string $responseBody, int $statusCode, ?int $userId = null, ?int $tenantId = null, int $ttlSeconds = 86400): void
    {
        global $wpdb;

        $tenantId ??= $this->tenantResolver->currentTenantId();
        $userId ??= $this->currentUserId();
        $table = $wpdb->prefix . 'mercato_idempotency';

        $result = $wpdb->replace($table, [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'endpoint' => $endpoint,
            'idempotency_key' => $key,
            'response_body' => $responseBody,
            'status_code' => $statusCode,
            'expires_at' => \gmdate('Y-m-d H:i:s.v', \time() + $ttlSeconds),
        ]);

        if ($result === false) {
            throw new RuntimeException('Unable to store idempotency response: ' . (string) $wpdb->last_error);
        }
    }

    private function currentUserId(): int
    {
        return \function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
    }
}
