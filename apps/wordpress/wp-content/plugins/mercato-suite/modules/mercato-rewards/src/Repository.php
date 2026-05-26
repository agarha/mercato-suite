<?php

declare(strict_types=1);

namespace Mercato\Rewards;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

/**
 * Per-tenant rewards configuration + balance/ledger read APIs.
 *
 * The config table holds the entire economy (currency names, earn/spend
 * amounts, premium-bid threshold). Tenants edit through the admin UI;
 * the storefront/REST surface reads via config().
 *
 * The read APIs (balance, ledger, leaderboard) are tenant-scoped and used
 * by the provider dashboard, the admin ledger view, and the customer
 * credit display at checkout.
 */
final class Repository
{
    /** Hard-coded defaults; mirror the migration DEFAULT values. */
    public const DEFAULT_CONFIG = [
        'pro_currency_name' => 'Sparks',
        'customer_currency_name' => 'Credits',
        'signup_bonus_sparks' => 10,
        'profile_complete_sparks' => 5,
        'insurance_verified_sparks' => 5,
        'completed_job_sparks' => 2,
        'five_star_sparks' => 2,
        'referral_sparks' => 10,
        'bid_cost_sparks' => 1,
        'premium_bid_cost_sparks' => 3,
        'premium_bid_threshold_minor' => 50000,
        'featured_listing_sparks' => 10,
        'extra_area_sparks_per_month' => 5,
        'customer_signup_credit_minor' => 1000,
        'customer_referral_credit_minor' => 1000,
        'customer_review_credit_minor' => 500,
        'sparks_per_usd' => 2,
        'enabled' => 1,
    ];

    public function __construct(
        private readonly Resolver $tenants,
        private readonly Outbox $outbox,
    ) {
    }

    /** @return array<string,mixed> */
    public function config(): array
    {
        global $wpdb;
        $tenantId = $this->tenants->currentTenantId();
        $table = $wpdb->prefix . 'mercato_reward_config';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE tenant_id = %d", $tenantId), ARRAY_A);
        if (!\is_array($row)) {
            return [
                'tenant_id' => $tenantId,
            ] + self::DEFAULT_CONFIG;
        }
        return $row;
    }

    /** @param array<string,mixed> $config @return array<string,mixed> */
    public function setConfig(array $config): array
    {
        global $wpdb;
        $tenantId = $this->tenants->currentTenantId();
        $table = $wpdb->prefix . 'mercato_reward_config';

        $clean = ['tenant_id' => $tenantId];
        foreach (self::DEFAULT_CONFIG as $key => $default) {
            if (!\array_key_exists($key, $config)) {
                continue;
            }
            if (\is_string($default)) {
                $clean[$key] = \function_exists('sanitize_text_field')
                    ? \sanitize_text_field((string) $config[$key])
                    : (string) $config[$key];
            } else {
                $clean[$key] = (int) $config[$key];
            }
        }

        $wpdb->replace($table, $clean);
        $this->outbox->publish('mercato.rewards.config.updated.v1', $clean, (string) $tenantId, $tenantId);
        return $this->config();
    }

    /** @return array<string,mixed> */
    public function balance(int $userId): array
    {
        global $wpdb;
        $tenantId = $this->tenants->currentTenantId();
        $table = $wpdb->prefix . 'mercato_user_balances';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, sparks, credits_minor, lifetime_sparks_earned, lifetime_credits_earned_minor, referrals_completed, updated_at FROM `{$table}` WHERE tenant_id = %d AND user_id = %d",
            $tenantId,
            $userId
        ), ARRAY_A);
        if (!\is_array($row)) {
            return [
                'user_id' => $userId,
                'sparks' => 0,
                'credits_minor' => 0,
                'lifetime_sparks_earned' => 0,
                'lifetime_credits_earned_minor' => 0,
                'referrals_completed' => 0,
                'updated_at' => null,
            ];
        }
        return $row;
    }

    /** @return list<array<string,mixed>> */
    public function ledger(?int $userId = null, int $limit = 100, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenants->currentTenantId();
        $table = $wpdb->prefix . 'mercato_reward_ledger';
        $limit = \min(500, \max(1, $limit));

        if ($userId !== null) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE tenant_id = %d AND user_id = %d ORDER BY entry_id DESC LIMIT %d OFFSET %d",
                $tenantId,
                $userId,
                $limit,
                $offset
            ), ARRAY_A) ?: [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE tenant_id = %d ORDER BY entry_id DESC LIMIT %d OFFSET %d",
            $tenantId,
            $limit,
            $offset
        ), ARRAY_A) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function topEarners(int $limit = 20): array
    {
        global $wpdb;
        $tenantId = $this->tenants->currentTenantId();
        $table = $wpdb->prefix . 'mercato_user_balances';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, sparks, lifetime_sparks_earned, referrals_completed FROM `{$table}` WHERE tenant_id = %d ORDER BY lifetime_sparks_earned DESC LIMIT %d",
            $tenantId,
            $limit
        ), ARRAY_A) ?: [];
    }
}
