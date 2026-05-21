<?php

declare(strict_types=1);

namespace Mercato\Migration;

use Mercato\Core\Rest\Permissions;
use Mercato\Core\ServiceProvider;
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

        \add_action('rest_api_init', fn () => \register_rest_route('mercato/v1', '/modules/mercato-migration', [
            'methods' => 'GET',
            'callback' => fn (): WP_REST_Response => new WP_REST_Response(['module' => 'mercato-migration', 'status' => 'available'], 200),
            'permission_callback' => [Permissions::class, 'canManage'],
        ]));
    }
}
