<?php

declare(strict_types=1);

namespace Mercato\Rentals;

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
        $this->container->bind(Availability::class, fn ($c): Availability => new Availability(
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
            \register_rest_route('mercato/v1', '/rentals/listings/(?P<id>\d+)/availability', [
                'methods' => 'GET',
                'callback' => [$this, 'availability'],
                'permission_callback' => [Permissions::class, 'canPublicHealth'],
            ]);
            \register_rest_route('mercato/v1', '/rentals/listings/(?P<id>\d+)/free-windows', [
                'methods' => 'GET',
                'callback' => [$this, 'freeWindows'],
                'permission_callback' => [Permissions::class, 'canPublicHealth'],
            ]);
            \register_rest_route('mercato/v1', '/rentals/listings/(?P<id>\d+)/holds', [
                'methods' => 'POST',
                'callback' => [$this, 'placeHold'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
            \register_rest_route('mercato/v1', '/rentals/bookings/confirm', [
                'methods' => 'POST',
                'callback' => [$this, 'confirmBooking'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
            \register_rest_route('mercato/v1', '/rentals/bookings/(?P<id>\d+)/pickup', [
                'methods' => 'POST',
                'callback' => [$this, 'markPickedUp'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
            \register_rest_route('mercato/v1', '/rentals/bookings/(?P<id>\d+)/return', [
                'methods' => 'POST',
                'callback' => [$this, 'markReturned'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
            \register_rest_route('mercato/v1', '/rentals/bookings/(?P<id>\d+)/cancel', [
                'methods' => 'POST',
                'callback' => [$this, 'cancel'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
        });
    }

    public function availability(WP_REST_Request $req): WP_REST_Response
    {
        $productId = (int) $req->get_param('id');
        $startsAt = (string) $req->get_param('starts_at');
        $endsAt = (string) $req->get_param('ends_at');
        $available = $this->container->get(Availability::class)->isAvailable($productId, $startsAt, $endsAt);
        return new WP_REST_Response(['available' => $available, 'product_id' => $productId, 'starts_at' => $startsAt, 'ends_at' => $endsAt], 200);
    }

    public function freeWindows(WP_REST_Request $req): WP_REST_Response
    {
        $productId = (int) $req->get_param('id');
        $from = (string) ($req->get_param('from') ?: \gmdate('Y-m-d H:i:s.v'));
        $to = (string) ($req->get_param('to') ?: \gmdate('Y-m-d H:i:s.v', \time() + 90 * 86400));
        $windows = $this->container->get(Availability::class)->freeWindows($productId, $from, $to);
        return new WP_REST_Response(['product_id' => $productId, 'windows' => $windows], 200);
    }

    public function placeHold(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        try {
            $body = (array) $req->get_json_params();
            $uid = \function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
            $result = $this->container->get(Availability::class)->placeHold(
                (int) $req->get_param('id'),
                $uid,
                (string) ($body['starts_at'] ?? ''),
                (string) ($body['ends_at'] ?? ''),
                $body['session_token'] ?? null
            );
            return new WP_REST_Response($result, 201);
        } catch (\Throwable $e) {
            $code = $e->getMessage() === 'WINDOW_UNAVAILABLE' ? 409 : 400;
            return new WP_Error('mercato_rental_hold_failed', $e->getMessage(), ['status' => $code]);
        }
    }

    public function confirmBooking(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        try {
            $body = (array) $req->get_json_params();
            $holdId = (int) ($body['hold_id'] ?? 0);
            $booking = $this->container->get(Availability::class)->confirmBooking($holdId, $body);
            return new WP_REST_Response($booking, 201);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_rental_confirm_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function markPickedUp(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        try {
            $body = (array) $req->get_json_params();
            $b = $this->container->get(Availability::class)->markPickedUp(
                (int) $req->get_param('id'),
                isset($body['condition_out_url']) ? (string) $body['condition_out_url'] : null
            );
            return new WP_REST_Response($b, 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_rental_pickup_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function markReturned(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        try {
            $body = (array) $req->get_json_params();
            $b = $this->container->get(Availability::class)->markReturned(
                (int) $req->get_param('id'),
                isset($body['condition_in_url']) ? (string) $body['condition_in_url'] : null,
                isset($body['deposit_claim_minor']) ? (int) $body['deposit_claim_minor'] : 0,
                isset($body['return_notes']) ? (string) $body['return_notes'] : null
            );
            return new WP_REST_Response($b, 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_rental_return_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function cancel(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        try {
            $body = (array) $req->get_json_params();
            $b = $this->container->get(Availability::class)->cancel(
                (int) $req->get_param('id'),
                (string) ($body['reason'] ?? 'renter_cancelled')
            );
            return new WP_REST_Response($b, 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_rental_cancel_failed', $e->getMessage(), ['status' => 400]);
        }
    }
}
