<?php

declare(strict_types=1);

namespace Mercato\ServiceOps;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

final class Repository
{
    /** @var array<string,list<string>> */
    private const TRANSITIONS = [
        'scheduled' => ['assigned', 'cancelled'],
        'assigned' => ['enroute', 'cancelled'],
        'enroute' => ['inprogress', 'cancelled'],
        'inprogress' => ['completed', 'cancelled'],
        'completed' => ['closed'],
        'closed' => [],
        'cancelled' => [],
    ];

    public function __construct(private readonly Resolver $tenantResolver, private readonly Outbox $outbox)
    {
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function createBooking(array $data): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $vendorId = (int) ($data['vendor_id'] ?? 0);
        $productId = (int) ($data['product_id'] ?? 0);
        if ($vendorId < 1 || $productId < 1) {
            throw new RuntimeException('vendor_id and product_id are required.');
        }

        $bookings = $wpdb->prefix . 'mercato_booking_requests';
        $inserted = $wpdb->insert($bookings, [
            'tenant_id' => $tenantId,
            'client_user_id' => isset($data['client_user_id']) ? (int) $data['client_user_id'] : (\function_exists('get_current_user_id') ? (int) \get_current_user_id() : null),
            'vendor_id' => $vendorId,
            'product_id' => $productId,
            'offering_id' => isset($data['offering_id']) ? (int) $data['offering_id'] : null,
            'scheduled_at' => isset($data['scheduled_at']) ? $this->dateTime((string) $data['scheduled_at']) : null,
            'notes' => isset($data['notes']) ? $this->clean((string) $data['notes']) : null,
        ]);
        if ($inserted === false) {
            throw new RuntimeException('Unable to create booking: ' . (string) $wpdb->last_error);
        }

        $bookingId = (int) $wpdb->insert_id;
        $job = $this->createJob([
            'booking_id' => $bookingId,
            'vendor_id' => $vendorId,
            'product_id' => $productId,
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ]);
        $booking = $this->booking($bookingId);
        $booking['job'] = $job;
        $this->outbox->publish('mercato.booking.created.v1', $booking, (string) $bookingId, $tenantId);

        return $booking;
    }

    /** @return list<array<string,mixed>> */
    public function jobs(?string $status = null): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_jobs';
        if ($status !== null && $status !== '') {
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `status` = %s ORDER BY `created_at` DESC", $tenantId, $status), ARRAY_A) ?: [];
        }

        return $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$table}` WHERE `tenant_id` = %d ORDER BY `created_at` DESC", $tenantId), ARRAY_A) ?: [];
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function assignJob(int $jobId, array $data): array
    {
        global $wpdb;

        $job = $this->job($jobId);
        $expectedVersion = isset($data['expected_version']) ? (int) $data['expected_version'] : (int) $job['version'];
        if ($expectedVersion !== (int) $job['version']) {
            throw new RuntimeException('ASSIGNMENT_CONFLICT');
        }

        $table = $wpdb->prefix . 'mercato_jobs';
        $updated = $wpdb->update($table, [
            'assigned_user_id' => (int) ($data['assigned_user_id'] ?? 0),
            'status' => 'assigned',
            'version' => (int) $job['version'] + 1,
        ], [
            'tenant_id' => $this->tenantResolver->currentTenantId(),
            'job_id' => $jobId,
            'version' => (int) $job['version'],
        ]);
        if ($updated !== 1) {
            throw new RuntimeException('ASSIGNMENT_CONFLICT');
        }

        $this->recordStatus($jobId, (string) $job['status'], 'assigned', $data['reason'] ?? null);
        $assigned = $this->job($jobId);
        $this->outbox->publish('mercato.job.assigned.v1', $assigned, (string) $jobId, (int) $assigned['tenant_id']);

        return $assigned;
    }

    /** @return array<string,mixed> */
    public function setJobStatus(int $jobId, string $status, ?string $reason = null): array
    {
        global $wpdb;

        $job = $this->job($jobId);
        $from = (string) $job['status'];
        if (!\in_array($status, self::TRANSITIONS[$from] ?? [], true)) {
            throw new RuntimeException('INVALID_STATUS_TRANSITION');
        }

        $table = $wpdb->prefix . 'mercato_jobs';
        $updated = $wpdb->update($table, [
            'status' => $status,
            'version' => (int) $job['version'] + 1,
        ], [
            'tenant_id' => $this->tenantResolver->currentTenantId(),
            'job_id' => $jobId,
        ]);
        if ($updated === false) {
            throw new RuntimeException('Unable to update job: ' . (string) $wpdb->last_error);
        }

        $this->recordStatus($jobId, $from, $status, $reason);
        $updatedJob = $this->job($jobId);
        $this->outbox->publish('mercato.job.status_changed.v1', $updatedJob, (string) $jobId, (int) $updatedJob['tenant_id']);

        return $updatedJob;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function createLead(array $data): array
    {
        global $wpdb;

        $title = $this->clean((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('title is required.');
        }

        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_leads';
        $inserted = $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'client_user_id' => isset($data['client_user_id']) ? (int) $data['client_user_id'] : null,
            'vendor_id' => isset($data['vendor_id']) ? (int) $data['vendor_id'] : null,
            'source' => isset($data['source']) ? $this->clean((string) $data['source']) : 'manual',
            'title' => $title,
            'details' => isset($data['details']) ? $this->clean((string) $data['details']) : null,
        ]);
        if ($inserted === false) {
            throw new RuntimeException('Unable to create lead: ' . (string) $wpdb->last_error);
        }

        $lead = $this->row('mercato_leads', 'lead_id', (int) $wpdb->insert_id);
        $this->outbox->publish('mercato.lead.created.v1', $lead, (string) $lead['lead_id'], $tenantId);

        return $lead;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function sendEstimate(array $data): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $vendorId = (int) ($data['vendor_id'] ?? 0);
        $items = (array) ($data['line_items'] ?? []);
        if ($vendorId < 1 || $items === []) {
            throw new RuntimeException('vendor_id and line_items are required.');
        }

        $total = 0;
        foreach ($items as $item) {
            $total += (int) ($item['unit_amount_minor'] ?? 0) * (int) \max(1, (float) ($item['quantity'] ?? 1));
        }

        $estimates = $wpdb->prefix . 'mercato_estimates';
        $inserted = $wpdb->insert($estimates, [
            'tenant_id' => $tenantId,
            'lead_id' => isset($data['lead_id']) ? (int) $data['lead_id'] : null,
            'client_user_id' => isset($data['client_user_id']) ? (int) $data['client_user_id'] : null,
            'vendor_id' => $vendorId,
            'status' => 'sent',
            'currency' => $this->clean((string) ($data['currency'] ?? 'USD')),
            'total_minor' => $total,
            'sent_at' => \gmdate('Y-m-d H:i:s.v'),
        ]);
        if ($inserted === false) {
            throw new RuntimeException('Unable to create estimate: ' . (string) $wpdb->last_error);
        }

        $estimateId = (int) $wpdb->insert_id;
        $lineTable = $wpdb->prefix . 'mercato_estimate_line_items';
        foreach ($items as $item) {
            $wpdb->insert($lineTable, [
                'tenant_id' => $tenantId,
                'estimate_id' => $estimateId,
                'description' => $this->clean((string) ($item['description'] ?? 'Service')),
                'quantity' => (float) ($item['quantity'] ?? 1),
                'unit_amount_minor' => (int) ($item['unit_amount_minor'] ?? 0),
            ]);
        }

        $estimate = $this->estimate($estimateId);
        $this->outbox->publish('mercato.estimate.sent.v1', $estimate, (string) $estimateId, $tenantId);

        return $estimate;
    }

    /** @return array<string,mixed> */
    public function acceptEstimate(int $estimateId): array
    {
        global $wpdb;

        $estimate = $this->estimate($estimateId);
        if ((string) $estimate['status'] !== 'sent') {
            throw new RuntimeException('Estimate is not acceptible.');
        }

        $table = $wpdb->prefix . 'mercato_estimates';
        $wpdb->update($table, [
            'status' => 'accepted',
            'accepted_at' => \gmdate('Y-m-d H:i:s.v'),
        ], [
            'tenant_id' => $this->tenantResolver->currentTenantId(),
            'estimate_id' => $estimateId,
        ]);

        $job = $this->createJob([
            'estimate_id' => $estimateId,
            'lead_id' => $estimate['lead_id'],
            'vendor_id' => $estimate['vendor_id'],
        ]);
        $accepted = $this->estimate($estimateId);
        $accepted['job'] = $job;
        $this->outbox->publish('mercato.estimate.accepted.v1', $accepted, (string) $estimateId, (int) $accepted['tenant_id']);

        return $accepted;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function createReferral(array $data): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $vendorId = (int) ($data['referrer_vendor_id'] ?? 0);
        $email = \strtolower(\trim((string) ($data['referred_email'] ?? '')));
        if ($vendorId < 1 || $email === '') {
            throw new RuntimeException('referrer_vendor_id and referred_email are required.');
        }

        $table = $wpdb->prefix . 'mercato_referrals';
        $row = [
            'tenant_id' => $tenantId,
            'referrer_vendor_id' => $vendorId,
            'referred_email_hash' => \hash('sha256', $email),
            'points' => (int) ($data['points'] ?? 25),
        ];
        $wpdb->replace($table, $row);
        $referral = $this->row('mercato_referrals', 'referral_id', (int) $wpdb->insert_id);
        $this->outbox->publish('mercato.referral.accrued.v1', $referral, (string) $referral['referral_id'], $tenantId);

        return $referral;
    }

    /** @return array<string,mixed> */
    private function createJob(array $data): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_jobs';
        $inserted = $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'booking_id' => isset($data['booking_id']) ? (int) $data['booking_id'] : null,
            'lead_id' => isset($data['lead_id']) ? (int) $data['lead_id'] : null,
            'estimate_id' => isset($data['estimate_id']) ? (int) $data['estimate_id'] : null,
            'vendor_id' => (int) ($data['vendor_id'] ?? 0),
            'product_id' => isset($data['product_id']) ? (int) $data['product_id'] : null,
            'scheduled_at' => isset($data['scheduled_at']) ? $this->dateTime((string) $data['scheduled_at']) : null,
        ]);
        if ($inserted === false) {
            throw new RuntimeException('Unable to create job: ' . (string) $wpdb->last_error);
        }

        $job = $this->job((int) $wpdb->insert_id);
        $this->recordStatus((int) $job['job_id'], null, (string) $job['status'], null);
        $this->outbox->publish('mercato.job.created.v1', $job, (string) $job['job_id'], $tenantId);

        return $job;
    }

    /** @return array<string,mixed> */
    private function booking(int $bookingId): array
    {
        return $this->row('mercato_booking_requests', 'booking_id', $bookingId);
    }

    /** @return array<string,mixed> */
    private function job(int $jobId): array
    {
        return $this->row('mercato_jobs', 'job_id', $jobId);
    }

    /** @return array<string,mixed> */
    private function estimate(int $estimateId): array
    {
        global $wpdb;

        $estimate = $this->row('mercato_estimates', 'estimate_id', $estimateId);
        $lineTable = $wpdb->prefix . 'mercato_estimate_line_items';
        $estimate['line_items'] = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$lineTable}` WHERE `tenant_id` = %d AND `estimate_id` = %d ORDER BY `line_item_id` ASC", $this->tenantResolver->currentTenantId(), $estimateId), ARRAY_A) ?: [];

        return $estimate;
    }

    /** @return array<string,mixed> */
    private function row(string $tableName, string $idColumn, int $id): array
    {
        global $wpdb;

        $table = $wpdb->prefix . $tableName;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `{$idColumn}` = %d", $this->tenantResolver->currentTenantId(), $id), ARRAY_A);
        if (!$row) {
            throw new RuntimeException('Record not found.');
        }

        return $row;
    }

    private function recordStatus(int $jobId, ?string $from, string $to, mixed $reason): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_job_status_history';
        $wpdb->insert($table, [
            'tenant_id' => $this->tenantResolver->currentTenantId(),
            'job_id' => $jobId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => \function_exists('get_current_user_id') ? (int) \get_current_user_id() : null,
            'reason' => $reason === null ? null : $this->clean((string) $reason),
        ]);
    }

    private function dateTime(string $value): string
    {
        $timestamp = \strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException('Invalid datetime.');
        }

        return \gmdate('Y-m-d H:i:s.v', $timestamp);
    }

    private function clean(string $value): string
    {
        return \function_exists('sanitize_text_field') ? \sanitize_text_field($value) : \trim($value);
    }
}
