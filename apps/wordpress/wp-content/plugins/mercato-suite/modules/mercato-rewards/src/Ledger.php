<?php

declare(strict_types=1);

namespace Mercato\Rewards;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

/**
 * Append-only reward ledger + balance snapshot updater.
 *
 * Three public verbs:
 *   - earn(user, currency, amount, reason, ref?) - credit balance, log entry.
 *   - spend(user, currency, amount, reason, ref?) - debit balance (must
 *     have >= amount), log entry. Throws INSUFFICIENT_BALANCE otherwise.
 *   - adjust(user, currency, amount, reason, actor) - admin override; can
 *     be positive (credit) or negative (debit). Logged with actor_user_id.
 *
 * All three return the new balance. All writes are tenant-scoped at the
 * SQL layer and emit a balance.updated.v1 event to the outbox so other
 * modules (notifications, analytics) can subscribe.
 *
 * Locking: balance read+update wraps in SELECT...FOR UPDATE inside an
 * explicit transaction so two concurrent earn/spend calls don't race.
 */
final class Ledger
{
    public function __construct(
        private readonly Resolver $tenants,
        private readonly Outbox $outbox,
    ) {
    }

    public function earn(int $userId, string $currency, int $amount, string $reason, ?string $refType = null, ?int $refId = null): int
    {
        $this->assertCurrency($currency);
        if ($amount <= 0) {
            throw new RuntimeException('Reward amount must be positive.');
        }
        return $this->apply($userId, $currency, $amount, 'earned', $reason, $refType, $refId, null);
    }

    public function spend(int $userId, string $currency, int $amount, string $reason, ?string $refType = null, ?int $refId = null): int
    {
        $this->assertCurrency($currency);
        if ($amount <= 0) {
            throw new RuntimeException('Spend amount must be positive.');
        }
        return $this->apply($userId, $currency, -$amount, 'spent', $reason, $refType, $refId, null);
    }

    public function refund(int $userId, string $currency, int $amount, string $reason, ?string $refType = null, ?int $refId = null): int
    {
        $this->assertCurrency($currency);
        if ($amount <= 0) {
            throw new RuntimeException('Refund amount must be positive.');
        }
        return $this->apply($userId, $currency, $amount, 'refunded', $reason, $refType, $refId, null);
    }

    public function adjust(int $userId, string $currency, int $delta, string $reason, int $actorUserId): int
    {
        $this->assertCurrency($currency);
        if ($delta === 0) {
            throw new RuntimeException('Adjustment must be non-zero.');
        }
        return $this->apply($userId, $currency, $delta, 'adjusted', $reason, null, null, $actorUserId);
    }

    private function apply(int $userId, string $currency, int $delta, string $kind, string $reason, ?string $refType, ?int $refId, ?int $actorUserId): int
    {
        global $wpdb;
        $tenantId = $this->tenants->currentTenantId();
        $balances = $wpdb->prefix . 'mercato_user_balances';
        $ledger = $wpdb->prefix . 'mercato_reward_ledger';
        $column = $currency === 'sparks' ? 'sparks' : 'credits_minor';
        $lifetimeCol = $currency === 'sparks' ? 'lifetime_sparks_earned' : 'lifetime_credits_earned_minor';

        $wpdb->query('START TRANSACTION');
        try {
            $current = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT `{$column}` FROM `{$balances}` WHERE tenant_id = %d AND user_id = %d FOR UPDATE",
                $tenantId,
                $userId
            ));
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM `{$balances}` WHERE tenant_id = %d AND user_id = %d",
                $tenantId,
                $userId
            ));
            $newBalance = $current + $delta;
            if ($delta < 0 && $newBalance < 0) {
                $wpdb->query('ROLLBACK');
                throw new RuntimeException('INSUFFICIENT_BALANCE');
            }

            if (!$exists) {
                $wpdb->insert($balances, [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'sparks' => $currency === 'sparks' ? \max(0, $newBalance) : 0,
                    'credits_minor' => $currency === 'credits' ? \max(0, $newBalance) : 0,
                    'lifetime_sparks_earned' => ($currency === 'sparks' && $delta > 0) ? $delta : 0,
                    'lifetime_credits_earned_minor' => ($currency === 'credits' && $delta > 0) ? $delta : 0,
                ]);
            } else {
                $set = "`{$column}` = `{$column}` + %d";
                $args = [$delta];
                if ($delta > 0) {
                    $set .= ", `{$lifetimeCol}` = `{$lifetimeCol}` + %d";
                    $args[] = $delta;
                }
                $sql = "UPDATE `{$balances}` SET {$set} WHERE tenant_id = %d AND user_id = %d";
                $args[] = $tenantId;
                $args[] = $userId;
                $wpdb->query($wpdb->prepare($sql, $args));
            }

            $inserted = $wpdb->insert($ledger, [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'currency' => $currency,
                'kind' => $kind,
                'amount' => \abs($delta),
                'balance_after' => $newBalance,
                'reason' => $reason,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'actor_user_id' => $actorUserId,
            ]);
            if ($inserted === false) {
                $wpdb->query('ROLLBACK');
                throw new RuntimeException('Ledger insert failed: ' . (string) $wpdb->last_error);
            }

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        $this->outbox->publish('mercato.rewards.balance.updated.v1', [
            'user_id' => $userId,
            'currency' => $currency,
            'delta' => $delta,
            'balance_after' => $newBalance,
            'reason' => $reason,
        ], (string) $userId, $tenantId);

        return $newBalance;
    }

    private function assertCurrency(string $currency): void
    {
        if (!\in_array($currency, ['sparks', 'credits'], true)) {
            throw new RuntimeException('Unknown currency: ' . $currency);
        }
    }
}
