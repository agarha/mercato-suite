<?php

declare(strict_types=1);

namespace Mercato\Notifications;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

final class Mailer
{
    public function __construct(private readonly Resolver $tenantResolver, private readonly Outbox $outbox)
    {
    }

    public function send(string $recipient, string $subject, string $body, ?int $tenantId = null): int
    {
        global $wpdb;

        $tenantId ??= $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_notification_deliveries';
        $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'channel' => 'email',
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'status' => 'queued',
        ]);
        $deliveryId = (int) $wpdb->insert_id;

        $sent = \function_exists('wp_mail') ? \wp_mail($recipient, $subject, $body) : true;
        $wpdb->update($table, [
            'status' => $sent ? 'sent' : 'failed',
            'last_error' => $sent ? null : 'wp_mail returned false',
            'sent_at' => $sent ? \gmdate('Y-m-d H:i:s.v') : null,
        ], ['delivery_id' => $deliveryId]);

        if (!$sent) {
            throw new RuntimeException('Email delivery failed.');
        }

        $this->outbox->publish('mercato.notification.email.sent.v1', [
            'delivery_id' => $deliveryId,
            'recipient' => $recipient,
            'subject' => $subject,
        ], (string) $deliveryId, $tenantId);

        return $deliveryId;
    }
}
