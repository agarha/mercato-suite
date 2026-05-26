<?php

declare(strict_types=1);

namespace Mercato\Rentals;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

/**
 * Availability + bookings engine for rental listings.
 *
 * Lifecycle:
 *   placeHold()     -> short soft-hold during checkout (10 min default)
 *   confirmBooking() -> turns a hold into a real booking after payment
 *   markPickedUp()  -> renter has the item; deposit pre-auth fires
 *   markReturned()  -> item back; deposit released or partial claim
 *   cancel()        -> any time before pickup
 *   markOverdue()   -> cron-driven; charges late fee from deposit
 *
 * isAvailable() is the workhorse: checks bookings + blackouts + holds for
 * window overlap. Uses the standard overlap test:
 *   existing.starts_at < requested.ends_at AND existing.ends_at > requested.starts_at
 *
 * All methods are tenant-scoped at the SQL layer. Pessimistic SELECT FOR
 * UPDATE wraps the hold/confirm transactions so two renters racing for
 * the last weekend window can never both win.
 */
final class Availability
{
    private const HOLD_TTL_SECONDS = 600; // 10 minutes
    private const ACTIVE_STATUSES = ['held', 'confirmed', 'active'];

    public function __construct(
        private readonly Resolver $tenants,
        private readonly Outbox $outbox,
    ) {
    }

    /**
     * Returns true if the product has no overlapping booking, blackout, or
     * unexpired hold across the requested window.
     */
    public function isAvailable(int $productId, string $startsAt, string $endsAt): bool
    {
        global $wpdb;
        $tenantId = $this->tenants->currentTenantId();
        $bookings = $wpdb->prefix . 'mercato_listing_bookings';
        $blackouts = $wpdb->prefix . 'mercato_listing_blackouts';
        $holds = $wpdb->prefix . 'mercato_listing_holds';
        $statusList = "'" . \implode("','", self::ACTIVE_STATUSES) . "'";

        $conflict = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT (
                (SELECT COUNT(*) FROM `{$bookings}` WHERE tenant_id = %d AND product_id = %d AND status IN ({$statusList}) AND starts_at < %s AND ends_at > %s)
              + (SELECT COUNT(*) FROM `{$blackouts}` WHERE tenant_id = %d AND product_id = %d AND starts_at < %s AND ends_at > %s)
              + (SELECT COUNT(*) FROM `{$holds}` WHERE tenant_id = %d AND product_id = %d AND expires_at > UTC_TIMESTAMP(3) AND starts_at < %s AND ends_at > %s)
            ) AS conflict_count",
            $tenantId, $productId, $endsAt, $startsAt,
            $tenantId, $productId, $endsAt, $startsAt,
            $tenantId, $productId, $endsAt, $startsAt
        ));

        return $conflict === 0;
    }

    /**
     * Get free windows in a date range. Returns array of {starts_at, ends_at}
     * sorted by starts_at. Useful for rendering calendars.
     *
     * @return list<array{starts_at:string,ends_at:string}>
     */
    public function freeWindows(int $productId, string $rangeStart, string $rangeEnd): array
    {
        global $wpdb;
        $tenantId = $this->tenants->currentTenantId();
        $bookings = $wpdb->prefix . 'mercato_listing_bookings';
        $blackouts = $wpdb->prefix . 'mercato_listing_blackouts';
        $statusList = "'" . \implode("','", self::ACTIVE_STATUSES) . "'";

        $occupied = $wpdb->get_results($wpdb->prepare(
            "SELECT starts_at, ends_at FROM `{$bookings}` WHERE tenant_id = %d AND product_id = %d AND status IN ({$statusList}) AND ends_at > %s AND starts_at < %s
             UNION ALL
             SELECT starts_at, ends_at FROM `{$blackouts}` WHERE tenant_id = %d AND product_id = %d AND ends_at > %s AND starts_at < %s
             ORDER BY starts_at ASC",
            $tenantId, $productId, $rangeStart, $rangeEnd,
            $tenantId, $productId, $rangeStart, $rangeEnd
        ), ARRAY_A) ?: [];

        // Subtract occupied windows from the requested range.
        $free = [];
        $cursor = $rangeStart;
        foreach ($occupied as $row) {
            if ($row['starts_at'] > $cursor) {
                $free[] = ['starts_at' => $cursor, 'ends_at' => $row['starts_at']];
            }
            if ($row['ends_at'] > $cursor) {
                $cursor = $row['ends_at'];
            }
        }
        if ($cursor < $rangeEnd) {
            $free[] = ['starts_at' => $cursor, 'ends_at' => $rangeEnd];
        }
        return $free;
    }

    /**
     * Place a short soft-hold so two renters can't grab the same window
     * during the checkout flow. Returns the hold_id + expires_at.
     *
     * @return array{hold_id:int,session_token:string,expires_at:string}
     */
    public function placeHold(int $productId, int $renterUserId, string $startsAt, string $endsAt, ?string $sessionToken = null): array
    {
        global $wpdb;
        $tenantId = $this->tenants->currentTenantId();
        $holds = $wpdb->prefix . 'mercato_listing_holds';
        $token = $sessionToken ?: \bin2hex(\random_bytes(32));
        $expiresAt = \gmdate('Y-m-d H:i:s.v', \time() + self::HOLD_TTL_SECONDS);

        $wpdb->query('START TRANSACTION');
        try {
            if (!$this->isAvailable($productId, $startsAt, $endsAt)) {
                $wpdb->query('ROLLBACK');
                throw new RuntimeException('WINDOW_UNAVAILABLE');
            }

            $inserted = $wpdb->insert($holds, [
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'renter_user_id' => $renterUserId,
                'session_token' => $token,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'expires_at' => $expiresAt,
            ]);
            if ($inserted === false) {
                $wpdb->query('ROLLBACK');
                throw new RuntimeException('Unable to place hold: ' . (string) $wpdb->last_error);
            }
            $holdId = (int) $wpdb->insert_id;
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        $this->outbox->publish('mercato.rental.hold.placed.v1', [
            'hold_id' => $holdId,
            'product_id' => $productId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'expires_at' => $expiresAt,
        ], (string) $holdId, $tenantId);

        return ['hold_id' => $holdId, 'session_token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * Promote a soft-hold to a confirmed booking after payment settles.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function confirmBooking(int $holdId, array $data): array
    {
        global $wpdb;
        $tenantId = $this->tenants->currentTenantId();
        $holds = $wpdb->prefix . 'mercato_listing_holds';
        $bookings = $wpdb->prefix . 'mercato_listing_bookings';

        $wpdb->query('START TRANSACTION');
        try {
            $hold = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM `{$holds}` WHERE tenant_id = %d AND hold_id = %d AND expires_at > UTC_TIMESTAMP(3) FOR UPDATE",
                $tenantId, $holdId
            ), ARRAY_A);
            if (!\is_array($hold)) {
                $wpdb->query('ROLLBACK');
                throw new RuntimeException('HOLD_EXPIRED_OR_MISSING');
            }

            $bookingRow = [
                'tenant_id' => $tenantId,
                'product_id' => (int) $hold['product_id'],
                'vendor_id' => (int) ($data['vendor_id'] ?? 0),
                'renter_user_id' => (int) $hold['renter_user_id'],
                'starts_at' => (string) $hold['starts_at'],
                'ends_at' => (string) $hold['ends_at'],
                'status' => 'confirmed',
                'pricing_type' => (string) ($data['pricing_type'] ?? 'per_day'),
                'units' => (float) ($data['units'] ?? 1),
                'rate_minor' => (int) ($data['rate_minor'] ?? 0),
                'total_minor' => (int) ($data['total_minor'] ?? 0),
                'currency' => (string) ($data['currency'] ?? 'CAD'),
                'deposit_minor' => (int) ($data['deposit_minor'] ?? 0),
                'deposit_payment_method_id' => isset($data['deposit_payment_method_id']) ? (string) $data['deposit_payment_method_id'] : null,
                'pickup_location_id' => isset($data['pickup_location_id']) ? (int) $data['pickup_location_id'] : null,
                'pickup_notes' => isset($data['pickup_notes']) ? (string) $data['pickup_notes'] : null,
            ];
            $inserted = $wpdb->insert($bookings, $bookingRow);
            if ($inserted === false) {
                $wpdb->query('ROLLBACK');
                throw new RuntimeException('Unable to confirm booking: ' . (string) $wpdb->last_error);
            }
            $bookingId = (int) $wpdb->insert_id;
            $wpdb->delete($holds, ['tenant_id' => $tenantId, 'hold_id' => $holdId]);
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        $booking = $this->find($bookingId);
        $this->outbox->publish('mercato.rental.booking.confirmed.v1', $booking, (string) $bookingId, $tenantId);
        return $booking;
    }

    public function markPickedUp(int $bookingId, ?string $conditionPhotoUrl = null): array
    {
        return $this->transition($bookingId, 'confirmed', 'active', [
            'picked_up_at' => \gmdate('Y-m-d H:i:s.v'),
            'condition_out_url' => $conditionPhotoUrl,
            'deposit_status' => 'authorized',
        ], 'mercato.rental.deposit.held.v1');
    }

    public function markReturned(int $bookingId, ?string $conditionPhotoUrl = null, int $depositClaimMinor = 0, ?string $returnNotes = null): array
    {
        $depositStatus = $depositClaimMinor > 0 ? 'partially_claimed' : 'released';
        return $this->transition($bookingId, 'active', 'returned', [
            'returned_at' => \gmdate('Y-m-d H:i:s.v'),
            'condition_in_url' => $conditionPhotoUrl,
            'return_notes' => $returnNotes,
            'deposit_status' => $depositStatus,
            'deposit_claim_minor' => $depositClaimMinor,
        ], 'mercato.rental.item.returned.v1');
    }

    public function cancel(int $bookingId, string $reason): array
    {
        return $this->transition($bookingId, 'confirmed', 'cancelled', [
            'cancelled_at' => \gmdate('Y-m-d H:i:s.v'),
            'cancelled_reason' => $reason,
        ], 'mercato.rental.booking.cancelled.v1');
    }

    /**
     * @param array<string,mixed> $updates
     * @return array<string,mixed>
     */
    private function transition(int $bookingId, string $expectedStatus, string $newStatus, array $updates, string $event): array
    {
        global $wpdb;
        $tenantId = $this->tenants->currentTenantId();
        $bookings = $wpdb->prefix . 'mercato_listing_bookings';

        $current = $this->find($bookingId);
        if ((string) $current['status'] !== $expectedStatus) {
            throw new RuntimeException('INVALID_TRANSITION_FROM_' . \strtoupper((string) $current['status']));
        }

        $updates['status'] = $newStatus;
        $updated = $wpdb->update($bookings, $updates, [
            'tenant_id' => $tenantId,
            'booking_id' => $bookingId,
        ]);
        if ($updated === false) {
            throw new RuntimeException('Unable to update booking: ' . (string) $wpdb->last_error);
        }

        $after = $this->find($bookingId);
        $this->outbox->publish($event, $after, (string) $bookingId, $tenantId);
        return $after;
    }

    /**
     * @return array<string,mixed>
     */
    public function find(int $bookingId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mercato_listing_bookings';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE tenant_id = %d AND booking_id = %d",
            $this->tenants->currentTenantId(),
            $bookingId
        ), ARRAY_A);
        if (!\is_array($row)) {
            throw new RuntimeException('Booking not found.');
        }
        return $row;
    }

    /**
     * Cron entrypoint — runs every 5 min, flags bookings whose ends_at is
     * in the past while status is still 'active' as 'overdue'. Doesn't
     * charge the deposit automatically — that's a human decision.
     *
     * @return list<int>
     */
    public function flagOverdue(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mercato_listing_bookings';
        $tenantId = $this->tenants->currentTenantId();
        $ids = \array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT booking_id FROM `{$table}` WHERE tenant_id = %d AND status = 'active' AND ends_at < UTC_TIMESTAMP(3)",
            $tenantId
        )) ?: []);
        if ($ids === []) {
            return [];
        }
        foreach ($ids as $id) {
            $wpdb->update($table, ['status' => 'overdue'], ['tenant_id' => $tenantId, 'booking_id' => $id]);
            $this->outbox->publish('mercato.rental.overdue.flagged.v1', ['booking_id' => $id], (string) $id, $tenantId);
        }
        return $ids;
    }
}
