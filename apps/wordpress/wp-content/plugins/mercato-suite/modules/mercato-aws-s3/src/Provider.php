<?php

declare(strict_types=1);

namespace Mercato\AwsS3;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Audit\Writer;
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
        $this->container->bind(Repository::class, fn ($c): Repository => new Repository(
            $c->get(Resolver::class),
            $c->get(Outbox::class),
        ));
    }

    public function boot(): void
    {
        if (!\function_exists('register_rest_route')) {
            return;
        }

        \add_action('rest_api_init', function (): void {
            \register_rest_route('mercato/v1', '/media/presign', [
                'methods' => 'POST',
                'callback' => [$this, 'presign'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);

            \register_rest_route('mercato/v1', '/media/(?P<id>\d+)/complete', [
                'methods' => 'POST',
                'callback' => [$this, 'complete'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);
        });
    }

    public function presign(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $media = $this->repo()->createUpload((array) $request->get_json_params());
                $this->audit('media.upload.presigned', 'media', (int) $media['media_id'], null, $media);
                return new WP_REST_Response($media, 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_media_presign_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function complete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $media = $this->repo()->complete((int) $request->get_param('id'), (string) $request->get_param('scan_status'));
                $this->audit('media.upload.completed', 'media', (int) $media['media_id'], null, $media);
                return new WP_REST_Response($media, 200);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_media_complete_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    private function repo(): Repository
    {
        return $this->container->get(Repository::class);
    }

    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    private function audit(string $action, string $entityType, int $entityId, ?array $before, ?array $after): void
    {
        $this->container->get(Writer::class)->log($action, $entityType, $entityId, $before, $after);
    }
}
