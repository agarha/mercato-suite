<?php

declare(strict_types=1);

namespace Mercato\Sendgrid;

use Mercato\Core\Events\Outbox;
use Mercato\Core\ServiceProvider;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class Provider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        if (!\function_exists('register_rest_route')) {
            return;
        }

        \add_action('rest_api_init', function (): void {
            \register_rest_route('mercato/v1', '/sendgrid/send', [
                'methods' => 'POST',
                'callback' => [$this, 'send'],
                'permission_callback' => '__return_true',
            ]);

            \register_rest_route('mercato/v1', '/sendgrid/events', [
                'methods' => 'POST',
                'callback' => [$this, 'webhook'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public function send(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->sendEmail(
                (string) $request->get_param('recipient'),
                (string) $request->get_param('subject'),
                (string) $request->get_param('body')
            ), 201);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_sendgrid_send_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function webhook(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $events = $request->get_json_params();
            if (!\is_array($events) || !\array_is_list($events)) {
                $events = [$events];
            }

            $count = 0;
            foreach ($events as $event) {
                if (\is_array($event)) {
                    $this->recordEvent($event);
                    ++$count;
                }
            }

            return new WP_REST_Response(['recorded' => $count], 202);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_sendgrid_webhook_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function sendEmail(string $recipient, string $subject, string $body): array
    {
        global $wpdb;

        $tenantId = $this->container->get(Resolver::class)->currentTenantId();
        $deliveries = $wpdb->prefix . 'mercato_notification_deliveries';
        $wpdb->insert($deliveries, [
            'tenant_id' => $tenantId,
            'channel' => 'email',
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'status' => 'queued',
        ]);
        $deliveryId = (int) $wpdb->insert_id;

        try {
            $messageId = $this->sendViaSendgrid($recipient, $subject, $body, $deliveryId);
            $wpdb->update($deliveries, [
                'status' => 'sent',
                'sent_at' => \gmdate('Y-m-d H:i:s.v'),
                'last_error' => null,
            ], ['delivery_id' => $deliveryId]);

            $event = [
                'delivery_id' => $deliveryId,
                'email' => $recipient,
                'event' => 'processed',
                'sg_message_id' => $messageId,
            ];
            $this->recordEvent($event);
            $this->container->get(Outbox::class)->publish('mercato.notification.delivered.v1', $event, (string) $deliveryId, $tenantId);

            return ['delivery_id' => $deliveryId, 'provider' => 'sendgrid', 'message_id' => $messageId, 'status' => 'sent'];
        } catch (\Throwable $e) {
            $wpdb->update($deliveries, [
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ], ['delivery_id' => $deliveryId]);
            $this->container->get(Outbox::class)->publish('mercato.notification.failed.v1', [
                'delivery_id' => $deliveryId,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ], (string) $deliveryId, $tenantId);
            throw $e;
        }
    }

    private function sendViaSendgrid(string $recipient, string $subject, string $body, int $deliveryId): string
    {
        $apiKey = (string) \getenv('SENDGRID_API_KEY');
        if ($apiKey === '' || \str_contains($apiKey, 'replace_me')) {
            return 'sg_test_' . $deliveryId;
        }

        $payload = [
            'personalizations' => [['to' => [['email' => $recipient]]]],
            'from' => ['email' => (string) (\getenv('MERCATO_EMAIL_FROM') ?: 'no-reply@example.invalid')],
            'subject' => $subject,
            'content' => [['type' => 'text/plain', 'value' => $body]],
            'custom_args' => ['delivery_id' => (string) $deliveryId],
        ];
        $response = \wp_remote_post('https://api.sendgrid.com/v3/mail/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => \wp_json_encode($payload, JSON_THROW_ON_ERROR),
            'timeout' => 20,
        ]);

        if (\is_wp_error($response)) {
            throw new RuntimeException($response->get_error_message());
        }

        $status = (int) \wp_remote_retrieve_response_code($response);
        if ($status !== 202) {
            throw new RuntimeException('SendGrid API returned HTTP ' . $status);
        }

        $headers = \wp_remote_retrieve_headers($response);
        $messageId = \is_object($headers) && \method_exists($headers, 'offsetGet') ? (string) $headers->offsetGet('x-message-id') : '';
        return $messageId !== '' ? $messageId : 'sg_api_' . $deliveryId;
    }

    /**
     * @param array<string,mixed> $event
     */
    private function recordEvent(array $event): void
    {
        global $wpdb;

        $tenantId = $this->container->get(Resolver::class)->currentTenantId();
        $type = (string) ($event['event'] ?? 'processed');
        $table = $wpdb->prefix . 'mercato_sendgrid_events';
        $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'delivery_id' => isset($event['delivery_id']) ? (int) $event['delivery_id'] : null,
            'message_id' => isset($event['sg_message_id']) ? (string) $event['sg_message_id'] : null,
            'recipient' => (string) ($event['email'] ?? 'unknown@example.invalid'),
            'event_type' => \in_array($type, ['processed', 'delivered', 'open', 'click', 'bounce', 'dropped', 'spamreport'], true) ? $type : 'processed',
            'reason' => isset($event['reason']) ? (string) $event['reason'] : null,
            'payload' => \wp_json_encode($event, JSON_THROW_ON_ERROR),
        ]);

        if ($type === 'delivered') {
            $this->container->get(Outbox::class)->publish('mercato.sendgrid.delivered.v1', $event, (string) $wpdb->insert_id, $tenantId);
        }

        if (\in_array($type, ['bounce', 'dropped', 'spamreport'], true)) {
            $this->container->get(Outbox::class)->publish('mercato.sendgrid.bounced.v1', $event, (string) $wpdb->insert_id, $tenantId);
        }
    }
}
