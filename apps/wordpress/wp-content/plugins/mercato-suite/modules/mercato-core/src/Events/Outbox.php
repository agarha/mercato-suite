<?php

declare(strict_types=1);

namespace Mercato\Core\Events;

use Mercato\Core\Tenant\Resolver;
use RuntimeException;

final class Outbox
{
    public function __construct(private readonly Resolver $tenantResolver)
    {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function publish(string $eventType, array $payload, ?string $partitionKey = null, ?int $tenantId = null): string
    {
        global $wpdb;

        if (!isset($wpdb)) {
            throw new RuntimeException('Mercato outbox requires WordPress $wpdb.');
        }

        $eventId = $this->newEventId();
        $tenantId ??= $this->tenantResolver->currentTenantId();
        $partitionKey ??= (string) $tenantId;
        $table = $wpdb->prefix . 'mercato_event_outbox';
        $envelope = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'tenant_id' => $tenantId,
            'schema_version' => 1,
            'created_at' => \gmdate('c'),
        ];

        $inserted = $wpdb->insert($table, [
            'event_id' => $eventId,
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'payload' => \wp_json_encode($payload, JSON_THROW_ON_ERROR),
            'envelope' => \wp_json_encode($envelope, JSON_THROW_ON_ERROR),
            'partition_key' => $partitionKey,
        ]);

        if ($inserted === false) {
            throw new RuntimeException('Unable to insert outbox event: ' . (string) $wpdb->last_error);
        }

        return $eventId;
    }

    private function newEventId(): string
    {
        return \substr(\strtoupper(\bin2hex(\random_bytes(13))), 0, 26);
    }
}
