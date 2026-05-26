<?php

declare(strict_types=1);

namespace Mercato\Notifications;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Rest\Permissions;
use Mercato\Core\ServiceProvider;
use Mercato\Core\Tenant\Resolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind(Mailer::class, fn ($c): Mailer => new Mailer($c->get(Resolver::class), $c->get(Outbox::class)));
    }

    public function boot(): void
    {
        if (!\function_exists('register_rest_route')) {
            return;
        }

        \add_action('rest_api_init', function (): void {
            \register_rest_route('mercato/v1', '/notifications/email', [
                'methods' => 'POST',
                'callback' => [$this, 'send'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);
        });
    }

    public function send(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $id = $this->container->get(Mailer::class)->send(
                    (string) $request->get_param('recipient'),
                    (string) $request->get_param('subject'),
                    (string) $request->get_param('body')
                );
                return new WP_REST_Response(['delivery_id' => $id], 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_notification_failed', $e->getMessage(), ['status' => 400]);
        }
    }
}
