<?php

declare(strict_types=1);

namespace Mercato\Vendors;

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
        $this->container->bind(Signup::class, fn ($c): Signup => new Signup(
            $c->get(Resolver::class),
            $c->get(Repository::class),
            $c,
        ));
    }

    public function boot(): void
    {
        if (!\function_exists('register_rest_route')) {
            return;
        }

        \add_action('rest_api_init', function (): void {
            \register_rest_route('mercato/v1', '/vendors', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'list'],
                    'permission_callback' => [Permissions::class, 'canManage'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'registerVendor'],
                    'permission_callback' => [Permissions::class, 'canPublicRegister'],
                ],
            ]);

            \register_rest_route('mercato/v1', '/vendors/(?P<id>\d+)/status', [
                'methods' => 'POST',
                'callback' => [$this, 'setStatus'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);

            \register_rest_route('mercato/v1', '/vendors/(?P<id>\d+)/onboarding', [
                'methods' => 'GET',
                'callback' => [$this, 'onboarding'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);

            \register_rest_route('mercato/v1', '/vendors/(?P<id>\d+)/profile', [
                'methods' => 'PATCH',
                'callback' => [$this, 'updateProfile'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);

            \register_rest_route('mercato/v1', '/vendors/(?P<id>\d+)/locations', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'listLocations'],
                    'permission_callback' => [Permissions::class, 'canRead'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'createLocation'],
                    'permission_callback' => [Permissions::class, 'canRead'],
                ],
            ]);

            \register_rest_route('mercato/v1', '/vendors/(?P<id>\d+)/service-areas', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'listServiceAreas'],
                    'permission_callback' => [Permissions::class, 'canRead'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'createServiceArea'],
                    'permission_callback' => [Permissions::class, 'canRead'],
                ],
            ]);

            \register_rest_route('mercato/v1', '/storefront/signup/verify', [
                'methods' => 'POST',
                'callback' => [$this, 'verifyEmail'],
                'permission_callback' => [Permissions::class, 'canPublicRegister'],
            ]);

            // Public storefront self-signup. One call drops a draft vendor
            // with the full profile, location, areas and starter services
            // attached. Admin still has to approve before products go active.
            \register_rest_route('mercato/v1', '/storefront/signup', [
                'methods' => 'POST',
                'callback' => [$this, 'storefrontSignup'],
                'permission_callback' => [Permissions::class, 'canPublicRegister'],
            ]);
        });
    }

    public function onboarding(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->repo()->onboardingChecklist((int) $request->get_param('id')), 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_vendor_onboarding_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function list(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->repo()->list((string) $request->get_param('status')), 200);
    }

    public function registerVendor(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $ownerUserId = \function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
                $vendor = $this->repo()->register((array) $request->get_json_params(), $ownerUserId);
                $this->audit('vendor.registered', 'vendor', (int) $vendor['vendor_id'], null, $vendor);
                return new WP_REST_Response($vendor, 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_vendor_registration_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function setStatus(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $vendor = $this->repo()->setStatus(
                    (int) $request->get_param('id'),
                    (string) $request->get_param('status'),
                    $request->get_param('reason') === null ? null : (string) $request->get_param('reason')
                );
                $this->audit('vendor.status.updated', 'vendor', (int) $vendor['vendor_id'], null, $vendor);
                return new WP_REST_Response($vendor, 200);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_vendor_status_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function verifyEmail(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $token = (string) ($request->get_param('token') ?: ($request->get_json_params()['token'] ?? ''));
            $vendor = $this->repo()->verifyEmailToken($token);
            $this->audit('vendor.email.verified', 'vendor', (int) $vendor['vendor_id'], null, $vendor);
            return new WP_REST_Response([
                'ok' => true,
                'vendor_id' => (int) $vendor['vendor_id'],
                'business_name' => (string) $vendor['business_name'],
                'verified_at' => (string) ($vendor['email_verified_at'] ?? ''),
            ], 200);
        } catch (\Throwable $e) {
            $code = $e->getMessage() === 'TOKEN_INVALID' ? 404 : 400;
            return new WP_Error('mercato_verify_failed', $e->getMessage(), ['status' => $code]);
        }
    }

    public function updateProfile(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $vendor = $this->repo()->updateProfile((int) $request->get_param('id'), (array) $request->get_json_params());
            $this->audit('vendor.profile.updated', 'vendor', (int) $vendor['vendor_id'], null, $vendor);
            return new WP_REST_Response($vendor, 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_vendor_profile_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function listLocations(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->repo()->locations((int) $request->get_param('id')), 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_vendor_locations_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function createLocation(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $location = $this->repo()->createLocation((int) $request->get_param('id'), (array) $request->get_json_params());
                $this->audit('vendor.location.created', 'vendor_location', (int) ($location['location_id'] ?? 0), null, $location);
                return new WP_REST_Response($location, 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_vendor_location_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function listServiceAreas(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($this->repo()->serviceAreas((int) $request->get_param('id')), 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_vendor_areas_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function createServiceArea(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $area = $this->repo()->createServiceArea((int) $request->get_param('id'), (array) $request->get_json_params());
                $this->audit('vendor.service_area.created', 'vendor_service_area', (int) ($area['area_id'] ?? 0), null, $area);
                return new WP_REST_Response($area, 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_vendor_service_area_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function storefrontSignup(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $loggedIn = \function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
                $signup = $this->container->get(Signup::class);
                $result = $signup->run((array) $request->get_json_params(), $loggedIn);
                $this->audit('vendor.storefront_signup', 'vendor', (int) ($result['vendor']['vendor_id'] ?? 0), null, $result);
                return new WP_REST_Response($result, 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_vendor_signup_failed', $e->getMessage(), ['status' => 400]);
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
