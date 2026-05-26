<?php

declare(strict_types=1);

namespace Mercato\Rewards;

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
        $this->container->bind(Ledger::class, fn ($c): Ledger => new Ledger(
            $c->get(Resolver::class),
            $c->get(Outbox::class),
        ));
        $this->container->bind(Repository::class, fn ($c): Repository => new Repository(
            $c->get(Resolver::class),
            $c->get(Outbox::class),
        ));
        $this->container->bind(AdminPages::class, fn ($c): AdminPages => new AdminPages(
            $c->get(Repository::class),
            $c->get(Ledger::class),
            $c->get(Resolver::class),
        ));
    }

    public function boot(): void
    {
        if (\function_exists('add_action')) {
            \add_action('admin_menu', [$this->container->get(AdminPages::class), 'register']);
            \add_action('admin_post_mercato_rewards_save_config', [$this->container->get(AdminPages::class), 'handleSaveConfig']);
            \add_action('admin_post_mercato_rewards_adjust', [$this->container->get(AdminPages::class), 'handleAdjust']);
            \add_action('rest_api_init', [$this, 'registerRoutes']);
        }
    }

    public function registerRoutes(): void
    {
        \register_rest_route('mercato/v1', '/rewards/config', [
            [
                'methods' => 'GET',
                'callback' => fn (): WP_REST_Response => new WP_REST_Response($this->container->get(Repository::class)->config(), 200),
                'permission_callback' => [Permissions::class, 'canRead'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'updateConfig'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ],
        ]);
        \register_rest_route('mercato/v1', '/rewards/balance', [
            'methods' => 'GET',
            'callback' => [$this, 'currentBalance'],
            'permission_callback' => [Permissions::class, 'canRead'],
        ]);
        \register_rest_route('mercato/v1', '/rewards/ledger', [
            'methods' => 'GET',
            'callback' => [$this, 'ledger'],
            'permission_callback' => [Permissions::class, 'canRead'],
        ]);
        \register_rest_route('mercato/v1', '/rewards/adjust', [
            'methods' => 'POST',
            'callback' => [$this, 'adjust'],
            'permission_callback' => [Permissions::class, 'canManage'],
        ]);
    }

    public function updateConfig(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        try {
            $body = (array) $req->get_json_params();
            return new WP_REST_Response($this->container->get(Repository::class)->setConfig($body), 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_rewards_config_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function currentBalance(WP_REST_Request $req): WP_REST_Response
    {
        $uid = (int) ($req->get_param('user_id') ?: (\function_exists('get_current_user_id') ? \get_current_user_id() : 0));
        return new WP_REST_Response($this->container->get(Repository::class)->balance($uid), 200);
    }

    public function ledger(WP_REST_Request $req): WP_REST_Response
    {
        $uid = $req->get_param('user_id') !== null ? (int) $req->get_param('user_id') : null;
        $limit = $req->get_param('limit') !== null ? (int) $req->get_param('limit') : 100;
        $offset = $req->get_param('offset') !== null ? (int) $req->get_param('offset') : 0;
        return new WP_REST_Response($this->container->get(Repository::class)->ledger($uid, $limit, $offset), 200);
    }

    public function adjust(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        try {
            $body = (array) $req->get_json_params();
            $userId = (int) ($body['user_id'] ?? 0);
            $currency = (string) ($body['currency'] ?? 'sparks');
            $delta = (int) ($body['delta'] ?? 0);
            $reason = (string) ($body['reason'] ?? 'admin_adjustment');
            $actor = \function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
            $balance = $this->container->get(Ledger::class)->adjust($userId, $currency, $delta, $reason, $actor);
            return new WP_REST_Response(['user_id' => $userId, 'currency' => $currency, 'balance' => $balance], 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_rewards_adjust_failed', $e->getMessage(), ['status' => 400]);
        }
    }
}
