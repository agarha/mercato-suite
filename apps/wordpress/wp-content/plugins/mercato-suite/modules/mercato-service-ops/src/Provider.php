<?php

declare(strict_types=1);

namespace Mercato\ServiceOps;

use Mercato\Core\Audit\Writer;
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
            \register_rest_route('mercato/v1', '/service-ops/bookings', [
                'methods' => 'POST',
                'callback' => [$this, 'createBooking'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
            \register_rest_route('mercato/v1', '/service-ops/jobs', [
                'methods' => 'GET',
                'callback' => [$this, 'jobs'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
            \register_rest_route('mercato/v1', '/service-ops/jobs/(?P<id>\d+)/assign', [
                'methods' => 'POST',
                'callback' => [$this, 'assignJob'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);
            \register_rest_route('mercato/v1', '/service-ops/jobs/(?P<id>\d+)/status', [
                'methods' => 'POST',
                'callback' => [$this, 'setJobStatus'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
            \register_rest_route('mercato/v1', '/service-ops/leads', [
                'methods' => 'POST',
                'callback' => [$this, 'createLead'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
            \register_rest_route('mercato/v1', '/service-ops/estimates', [
                'methods' => 'POST',
                'callback' => [$this, 'sendEstimate'],
                'permission_callback' => [Permissions::class, 'canManage'],
            ]);
            \register_rest_route('mercato/v1', '/service-ops/estimates/(?P<id>\d+)/accept', [
                'methods' => 'POST',
                'callback' => [$this, 'acceptEstimate'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
            \register_rest_route('mercato/v1', '/service-ops/referrals', [
                'methods' => 'POST',
                'callback' => [$this, 'createReferral'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
            \register_rest_route('mercato/v1', '/service-ops/referrals/(?P<id>\d+)/redeem', [
                'methods' => 'POST',
                'callback' => [$this, 'redeemReferral'],
                'permission_callback' => [Permissions::class, 'canRead'],
            ]);
        });
    }

    public function createBooking(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return $this->idempotent($request, function () use ($request): WP_REST_Response {
                $booking = $this->repo()->createBooking((array) $request->get_json_params());
                $this->audit('booking.created', 'booking', (int) $booking['booking_id'], null, $booking);
                return new WP_REST_Response($booking, 201);
            });
        } catch (\Throwable $e) {
            return new WP_Error('mercato_booking_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function jobs(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->repo()->jobs($request->get_param('status') === null ? null : (string) $request->get_param('status')), 200);
    }

    public function assignJob(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $job = $this->repo()->assignJob((int) $request->get_param('id'), (array) $request->get_json_params());
            $this->audit('job.assigned', 'job', (int) $job['job_id'], null, $job);
            return new WP_REST_Response($job, 200);
        } catch (\Throwable $e) {
            $status = $e->getMessage() === 'ASSIGNMENT_CONFLICT' ? 409 : 400;
            return new WP_Error('mercato_job_assign_failed', $e->getMessage(), ['status' => $status]);
        }
    }

    public function setJobStatus(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $data = (array) $request->get_json_params();
            $job = $this->repo()->setJobStatus((int) $request->get_param('id'), (string) ($data['status'] ?? ''), $data['reason'] ?? null);
            $this->audit('job.status.updated', 'job', (int) $job['job_id'], null, $job);
            return new WP_REST_Response($job, 200);
        } catch (\Throwable $e) {
            $status = $e->getMessage() === 'INVALID_STATUS_TRANSITION' ? 409 : 400;
            return new WP_Error('mercato_job_status_failed', $e->getMessage(), ['status' => $status]);
        }
    }

    public function createLead(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $lead = $this->repo()->createLead((array) $request->get_json_params());
            $this->audit('lead.created', 'lead', (int) $lead['lead_id'], null, $lead);
            return new WP_REST_Response($lead, 201);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_lead_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function sendEstimate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $estimate = $this->repo()->sendEstimate((array) $request->get_json_params());
            $this->audit('estimate.sent', 'estimate', (int) $estimate['estimate_id'], null, $estimate);
            return new WP_REST_Response($estimate, 201);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_estimate_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function acceptEstimate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $estimate = $this->repo()->acceptEstimate((int) $request->get_param('id'));
            $this->audit('estimate.accepted', 'estimate', (int) $estimate['estimate_id'], null, $estimate);
            return new WP_REST_Response($estimate, 200);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_estimate_accept_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function createReferral(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $referral = $this->repo()->createReferral((array) $request->get_json_params());
            $this->audit('referral.accrued', 'referral', (int) $referral['referral_id'], null, $referral);
            return new WP_REST_Response($referral, 201);
        } catch (\Throwable $e) {
            return new WP_Error('mercato_referral_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function redeemReferral(): WP_Error
    {
        return new WP_Error('FEATURE_DISABLED', 'Referral redemption is disabled for this tenant.', ['status' => 403]);
    }

    private function repo(): Repository
    {
        return $this->container->get(Repository::class);
    }

    /** @param array<string,mixed>|null $before @param array<string,mixed>|null $after */
    private function audit(string $action, string $entityType, int $entityId, ?array $before, ?array $after): void
    {
        $this->container->get(Writer::class)->log($action, $entityType, $entityId, $before, $after);
    }
}
