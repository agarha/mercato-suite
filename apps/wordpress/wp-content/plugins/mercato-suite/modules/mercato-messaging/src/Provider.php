<?php

declare(strict_types=1);

namespace Mercato\Messaging;

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
        $this->container->bind(Repository::class, fn ($c): Repository => new Repository($c->get(Resolver::class), $c->get(Outbox::class)));
    }

    public function boot(): void
    {
        if (!\function_exists('register_rest_route')) {
            return;
        }

        \add_action('rest_api_init', function (): void {
            \register_rest_route('mercato/v1', '/messages/threads', [
                'methods' => 'POST',
                'callback' => [$this, 'createThread'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
            \register_rest_route('mercato/v1', '/messages/threads/(?P<id>\d+)/reply', [
                'methods' => 'POST',
                'callback' => [$this, 'reply'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
        });
    }

    public function createThread(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, fn (): WP_REST_Response => new WP_REST_Response($this->container->get(Repository::class)->createThread((array) $request->get_json_params()), 201));
        } catch (\Throwable $e) {
            return new WP_Error('mercato_message_thread_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function reply(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, fn (): WP_REST_Response => new WP_REST_Response($this->container->get(Repository::class)->reply((int) $request->get_param('id'), (array) $request->get_json_params()), 201));
        } catch (\Throwable $e) {
            return new WP_Error('mercato_message_reply_failed', $e->getMessage(), ['status' => 400]);
        }
    }
}
