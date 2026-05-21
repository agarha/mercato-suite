<?php

declare(strict_types=1);

namespace Mercato\Sendgrid;

use Mercato\Core\Events\Outbox;
use Mercato\Core\ServiceProvider;
use Mercato\Core\Tenant\Resolver;
use Mercato\Notifications\Mailer;
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
                'permission_callback' => fn (): bool => \function_exists('current_user_can') && \current_user_can('manage_options'),
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
            if (!$this->container->has(Mailer::class)) {
                return new WP_Error('mercato_sendgrid_mailer_missing', 'Notifications mailer is unavailable.', ['status' => 500]);
            }

            $deliveryId = $this->container->get(Mailer::class)->send(
                (string) $request->get_param('recipient'),
                (string) $request->get_param('subject'),
                (string) $request->get_param('body')
            );

            return new WP_REST_Response(['delivery_id' => $deliveryId, 'provider' => 'sendgrid'], 201);
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
