<?php

declare(strict_types=1);

namespace Mercato\Rewards;

use Mercato\Core\Tenant\Resolver;

/**
 * Server-rendered admin UI for the rewards subsystem.
 *
 * Two pages, both under the Mercato menu:
 *   - mercato-rewards-config   : edit currency names + earn/spend amounts
 *   - mercato-rewards-ledger   : view ledger entries, balances, manual
 *                                 adjustments
 *
 * No JS framework. Forms POST to admin-post.php and route through the
 * Repository / Ledger so audit + outbox events fire normally.
 */
final class AdminPages
{
    public function __construct(
        private readonly Repository $repo,
        private readonly Ledger $ledger,
        private readonly Resolver $tenants,
    ) {
    }

    public function register(): void
    {
        if (!\function_exists('add_submenu_page')) {
            return;
        }
        \add_submenu_page('mercato-admin', 'Rewards Config', 'Rewards', 'manage_options', 'mercato-rewards-config', [$this, 'renderConfig']);
        \add_submenu_page('mercato-admin', 'Rewards Ledger', 'Rewards Ledger', 'manage_options', 'mercato-rewards-ledger', [$this, 'renderLedger']);
    }

    public function renderConfig(): void
    {
        $config = $this->repo->config();
        $notice = isset($_GET['saved']) ? '<div class="notice notice-success is-dismissible"><p>Rewards config saved.</p></div>' : '';
        $pro = \esc_html((string) $config['pro_currency_name']);
        $cust = \esc_html((string) $config['customer_currency_name']);

        echo '<div class="wrap">';
        echo '<h1>Rewards configuration</h1>';
        echo '<p>Tenant ' . (int) $this->tenants->currentTenantId() . '. Edit the names and earn/spend amounts. Setting <code>enabled</code> to 0 turns the entire economy off without losing balances.</p>';
        echo $notice;

        echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="mercato_rewards_save_config">';
        \wp_nonce_field('mercato_rewards_save_config');

        echo '<table class="form-table" role="presentation"><tbody>';
        $this->row('enabled', 'Economy enabled (1/0)', (int) $config['enabled'], 'number');
        $this->row('pro_currency_name', 'Pro currency name', (string) $config['pro_currency_name'], 'text');
        $this->row('customer_currency_name', 'Customer currency name', (string) $config['customer_currency_name'], 'text');

        echo '<tr><th colspan="2"><h2>Pro earning (' . $pro . ')</h2></th></tr>';
        $this->row('signup_bonus_sparks', 'Signup bonus', (int) $config['signup_bonus_sparks'], 'number');
        $this->row('profile_complete_sparks', 'Profile completion bonus', (int) $config['profile_complete_sparks'], 'number');
        $this->row('insurance_verified_sparks', 'Insurance verified bonus', (int) $config['insurance_verified_sparks'], 'number');
        $this->row('completed_job_sparks', 'Per completed job', (int) $config['completed_job_sparks'], 'number');
        $this->row('five_star_sparks', 'Per 5-star review', (int) $config['five_star_sparks'], 'number');
        $this->row('referral_sparks', 'Per activated referral (single-tier)', (int) $config['referral_sparks'], 'number');

        echo '<tr><th colspan="2"><h2>Pro spending</h2></th></tr>';
        $this->row('bid_cost_sparks', 'Cost per bid', (int) $config['bid_cost_sparks'], 'number');
        $this->row('premium_bid_cost_sparks', 'Cost per premium bid', (int) $config['premium_bid_cost_sparks'], 'number');
        $this->row('premium_bid_threshold_minor', 'Premium bid budget threshold (cents)', (int) $config['premium_bid_threshold_minor'], 'number');
        $this->row('featured_listing_sparks', 'Featured listing 24h', (int) $config['featured_listing_sparks'], 'number');
        $this->row('extra_area_sparks_per_month', 'Extra service area per month', (int) $config['extra_area_sparks_per_month'], 'number');

        echo '<tr><th colspan="2"><h2>Customer ' . $cust . ' (cents)</h2></th></tr>';
        $this->row('customer_signup_credit_minor', 'Customer signup credit', (int) $config['customer_signup_credit_minor'], 'number');
        $this->row('customer_referral_credit_minor', 'Customer referral credit', (int) $config['customer_referral_credit_minor'], 'number');
        $this->row('customer_review_credit_minor', 'Customer review credit', (int) $config['customer_review_credit_minor'], 'number');

        echo '<tr><th colspan="2"><h2>Top-up</h2></th></tr>';
        $this->row('sparks_per_usd', $pro . ' per USD when buying', (int) $config['sparks_per_usd'], 'number');

        echo '</tbody></table>';
        echo '<p><button type="submit" class="button button-primary">Save configuration</button></p>';
        echo '</form>';
        echo '</div>';
    }

    private function row(string $name, string $label, mixed $value, string $type): void
    {
        echo '<tr>';
        echo '<th><label for="r_' . \esc_attr($name) . '">' . \esc_html($label) . '</label></th>';
        echo '<td><input id="r_' . \esc_attr($name) . '" type="' . $type . '" name="config[' . \esc_attr($name) . ']" value="' . \esc_attr((string) $value) . '" class="regular-text"></td>';
        echo '</tr>';
    }

    public function handleSaveConfig(): void
    {
        if (!\current_user_can('manage_options')) { \wp_die('Insufficient permissions.'); }
        \check_admin_referer('mercato_rewards_save_config');
        $config = isset($_POST['config']) && \is_array($_POST['config']) ? \wp_unslash($_POST['config']) : [];
        try {
            $this->repo->setConfig($config);
        } catch (\Throwable $e) {
            \wp_die('Save failed: ' . \esc_html($e->getMessage()));
        }
        \wp_safe_redirect(\admin_url('admin.php?page=mercato-rewards-config&saved=1'));
        exit;
    }

    public function renderLedger(): void
    {
        $userFilter = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
        $rows = $this->repo->ledger($userFilter > 0 ? $userFilter : null, 200, 0);
        $config = $this->repo->config();
        $proName = \esc_html((string) $config['pro_currency_name']);
        $custName = \esc_html((string) $config['customer_currency_name']);

        $notice = isset($_GET['adjusted']) ? '<div class="notice notice-success is-dismissible"><p>Adjustment recorded.</p></div>' : '';
        echo '<div class="wrap">';
        echo '<h1>Rewards ledger</h1>';
        echo $notice;

        echo '<form method="get" action="" style="margin-bottom:14px;">';
        echo '<input type="hidden" name="page" value="mercato-rewards-ledger">';
        echo '<label>Filter by user_id: <input type="number" name="user_id" value="' . ($userFilter ?: '') . '" style="width:120px;"></label> ';
        echo '<button type="submit" class="button">Filter</button> ';
        if ($userFilter > 0) {
            echo '<a href="' . \esc_url(\admin_url('admin.php?page=mercato-rewards-ledger')) . '" class="button">Clear</a>';
        }
        echo '</form>';

        if ($userFilter > 0) {
            $bal = $this->repo->balance($userFilter);
            echo '<div class="postbox" style="padding:14px;margin-bottom:14px;">';
            echo '<h2 style="margin-top:0;">User #' . (int) $userFilter . ' - balance</h2>';
            echo '<p><strong>' . (int) $bal['sparks'] . '</strong> ' . $proName . ' &nbsp; | &nbsp; <strong>$' . \number_format($bal['credits_minor'] / 100, 2) . '</strong> ' . $custName . '</p>';
            echo '<p><small>Lifetime ' . $proName . ' earned: ' . (int) $bal['lifetime_sparks_earned'] . ' &middot; Referrals completed: ' . (int) $bal['referrals_completed'] . '</small></p>';

            echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" style="margin-top:14px;border-top:1px solid #ccd0d4;padding-top:14px;">';
            echo '<input type="hidden" name="action" value="mercato_rewards_adjust">';
            echo '<input type="hidden" name="user_id" value="' . (int) $userFilter . '">';
            \wp_nonce_field('mercato_rewards_adjust_' . $userFilter);
            echo '<h3>Manual adjustment</h3>';
            echo '<label>Currency: <select name="currency"><option value="sparks">' . $proName . '</option><option value="credits">' . $custName . ' (cents)</option></select></label> ';
            echo '<label>Delta (+/-): <input type="number" name="delta" required style="width:120px;"></label> ';
            echo '<label>Reason: <input type="text" name="reason" required style="width:200px;"></label> ';
            echo '<button type="submit" class="button button-primary">Apply</button>';
            echo '</form>';
            echo '</div>';
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>When</th><th>User</th><th>Currency</th><th>Kind</th><th>Amount</th><th>Balance after</th><th>Reason</th><th>Reference</th><th>Actor</th></tr></thead><tbody>';
        if ($rows === []) {
            echo '<tr><td colspan="9"><em>No ledger entries yet.</em></td></tr>';
        }
        foreach ($rows as $r) {
            $sign = $r['kind'] === 'spent' ? '-' : '+';
            $amountFmt = (string) $r['amount'];
            if ($r['currency'] === 'credits') {
                $amountFmt = '$' . \number_format((int) $r['amount'] / 100, 2);
            }
            $ref = $r['reference_type'] ? \esc_html($r['reference_type']) . ' #' . (int) ($r['reference_id'] ?? 0) : '-';
            echo '<tr>';
            echo '<td><small>' . \esc_html($r['created_at']) . '</small></td>';
            echo '<td><a href="' . \esc_url(\admin_url('admin.php?page=mercato-rewards-ledger&user_id=' . (int) $r['user_id'])) . '">#' . (int) $r['user_id'] . '</a></td>';
            echo '<td>' . \esc_html($r['currency']) . '</td>';
            echo '<td>' . \esc_html($r['kind']) . '</td>';
            echo '<td><strong>' . $sign . $amountFmt . '</strong></td>';
            echo '<td>' . (int) $r['balance_after'] . '</td>';
            echo '<td><code>' . \esc_html($r['reason']) . '</code></td>';
            echo '<td>' . $ref . '</td>';
            echo '<td>' . ($r['actor_user_id'] ? '#' . (int) $r['actor_user_id'] : '-') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    public function handleAdjust(): void
    {
        if (!\current_user_can('manage_options')) { \wp_die('Insufficient permissions.'); }
        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        \check_admin_referer('mercato_rewards_adjust_' . $userId);
        $currency = isset($_POST['currency']) ? \sanitize_key((string) $_POST['currency']) : 'sparks';
        $delta = isset($_POST['delta']) ? (int) $_POST['delta'] : 0;
        $reason = isset($_POST['reason']) ? \sanitize_text_field((string) $_POST['reason']) : 'admin_adjustment';
        $actor = \function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        try {
            $this->ledger->adjust($userId, $currency, $delta, $reason, $actor);
        } catch (\Throwable $e) {
            \wp_die('Adjustment failed: ' . \esc_html($e->getMessage()));
        }
        \wp_safe_redirect(\admin_url('admin.php?page=mercato-rewards-ledger&user_id=' . $userId . '&adjusted=1'));
        exit;
    }
}
