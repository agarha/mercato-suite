<?php

declare(strict_types=1);

namespace Mercato\Core\Admin;

use Mercato\Core\Container;
use Mercato\Core\Tenant\IntegrationSettings;
use Mercato\Core\Tenant\Resolver;

/**
 * Admin operations UI — server-rendered PHP forms (no React) so it works
 * the moment the plugin is active, with no build step.
 *
 * Pages:
 *   - mercato-connectors  -> renderConnectors(): list every integration
 *                            (Stripe / Postmark / SendGrid / Twilio / S3 /
 *                             Avalara / TaxJar / Shippo / PayPal), show
 *                            current status and editable credentials, post
 *                            back to admin-post.php?action=mercato_save_connector.
 *   - mercato-approvals   -> renderApprovals(): list pending vendors with
 *                            profile preview and Approve / Reject buttons.
 *
 * All writes flow through IntegrationSettings / Vendors\Repository so the
 * audit log and outbox still receive proper events.
 */
final class Pages
{
    /** @var list<array{key:string,label:string,public_fields:list<array{name:string,label:string,placeholder?:string}>,secret_fields:list<array{name:string,label:string}>}> */
    private const CONNECTORS = [
        [
            'key' => 'stripe_connect',
            'label' => 'Stripe Connect (payments + payouts)',
            'public_fields' => [
                ['name' => 'publishable_key', 'label' => 'Publishable key', 'placeholder' => 'pk_live_...'],
                ['name' => 'account_country', 'label' => 'Default account country (ISO-2)', 'placeholder' => 'CA'],
                ['name' => 'webhook_url', 'label' => 'Webhook URL (read-only)', 'placeholder' => '/wp-json/mercato/v1/webhooks/stripe'],
            ],
            'secret_fields' => [
                ['name' => 'secret_key', 'label' => 'Secret key (sk_live_...)'],
                ['name' => 'webhook_signing_secret', 'label' => 'Webhook signing secret (whsec_...)'],
            ],
        ],
        [
            'key' => 'postmark',
            'label' => 'Postmark (transactional email)',
            'public_fields' => [
                ['name' => 'from_email', 'label' => 'From email', 'placeholder' => 'no-reply@gigsii.com'],
                ['name' => 'from_name', 'label' => 'From name', 'placeholder' => 'Gigsii'],
                ['name' => 'message_stream', 'label' => 'Message stream', 'placeholder' => 'outbound'],
            ],
            'secret_fields' => [
                ['name' => 'server_token', 'label' => 'Server API token'],
            ],
        ],
        [
            'key' => 'sendgrid',
            'label' => 'SendGrid (transactional email - alternate)',
            'public_fields' => [
                ['name' => 'from_email', 'label' => 'From email'],
                ['name' => 'from_name', 'label' => 'From name'],
            ],
            'secret_fields' => [
                ['name' => 'api_key', 'label' => 'API key (SG.xxxx)'],
            ],
        ],
        [
            'key' => 'twilio',
            'label' => 'Twilio (SMS notifications)',
            'public_fields' => [
                ['name' => 'from_number', 'label' => 'From phone number', 'placeholder' => '+14165550100'],
                ['name' => 'messaging_service_sid', 'label' => 'Messaging service SID (optional)'],
            ],
            'secret_fields' => [
                ['name' => 'account_sid', 'label' => 'Account SID'],
                ['name' => 'auth_token', 'label' => 'Auth token'],
            ],
        ],
        [
            'key' => 'aws_s3',
            'label' => 'AWS S3 (media + portfolio uploads)',
            'public_fields' => [
                ['name' => 'region', 'label' => 'Region', 'placeholder' => 'us-east-1'],
                ['name' => 'bucket', 'label' => 'Bucket name'],
                ['name' => 'public_endpoint', 'label' => 'Public CDN endpoint (optional)'],
            ],
            'secret_fields' => [
                ['name' => 'access_key_id', 'label' => 'Access key ID'],
                ['name' => 'secret_access_key', 'label' => 'Secret access key'],
            ],
        ],
        [
            'key' => 'avalara',
            'label' => 'Avalara (tax calculation - enterprise)',
            'public_fields' => [
                ['name' => 'account_id', 'label' => 'Account ID'],
                ['name' => 'company_code', 'label' => 'Company code'],
                ['name' => 'environment', 'label' => 'Environment (sandbox / production)'],
            ],
            'secret_fields' => [
                ['name' => 'license_key', 'label' => 'License key'],
            ],
        ],
        [
            'key' => 'taxjar',
            'label' => 'TaxJar (tax calculation - SMB)',
            'public_fields' => [
                ['name' => 'environment', 'label' => 'Environment (sandbox / production)'],
            ],
            'secret_fields' => [
                ['name' => 'api_token', 'label' => 'API token'],
            ],
        ],
        [
            'key' => 'shippo',
            'label' => 'Shippo (shipping rates + labels)',
            'public_fields' => [
                ['name' => 'default_carrier', 'label' => 'Default carrier (optional)'],
            ],
            'secret_fields' => [
                ['name' => 'api_token', 'label' => 'API token'],
            ],
        ],
        [
            'key' => 'paypal',
            'label' => 'PayPal Marketplace (alt payments)',
            'public_fields' => [
                ['name' => 'partner_id', 'label' => 'Partner ID'],
                ['name' => 'environment', 'label' => 'Environment (sandbox / live)'],
            ],
            'secret_fields' => [
                ['name' => 'client_id', 'label' => 'Client ID'],
                ['name' => 'client_secret', 'label' => 'Client secret'],
            ],
        ],
    ];

    public function __construct(
        private readonly Container $container,
        private readonly Resolver $tenants,
    ) {
    }

    public function register(): void
    {
        if (!\function_exists('add_submenu_page')) {
            return;
        }
        \add_submenu_page('mercato-admin', 'Connectors', 'Connectors', 'manage_options', 'mercato-connectors', [$this, 'renderConnectors']);
        \add_submenu_page('mercato-admin', 'Provider Approvals', 'Approvals', 'manage_options', 'mercato-approvals', [$this, 'renderApprovals']);

        \add_action('admin_post_mercato_save_connector', [$this, 'handleSaveConnector']);
        \add_action('admin_post_mercato_vendor_decision', [$this, 'handleVendorDecision']);
    }

    public function renderConnectors(): void
    {
        $tenantId = $this->tenants->currentTenantId();
        $settings = $this->container->get(IntegrationSettings::class);
        $current = [];
        foreach ($settings->list() as $row) {
            $public = \json_decode((string) ($row['public_config'] ?? '{}'), true);
            $secret = \json_decode((string) ($row['secret_refs'] ?? '{}'), true);
            $current[(string) $row['provider_key']] = [
                'status' => (string) $row['status'],
                'public' => \is_array($public) ? $public : [],
                'secret' => \is_array($secret) ? $secret : [],
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }

        $notice = isset($_GET['saved']) && $_GET['saved'] === '1' ? '<div class="notice notice-success is-dismissible"><p>Connector saved.</p></div>' : '';
        echo '<div class="wrap">';
        echo '<h1>Connectors</h1>';
        echo '<p>Per-tenant credentials for external integrations. Status <code>disabled</code> = module wired but not called. <code>test</code> = sandbox keys. <code>live</code> = production keys.</p>';
        echo '<p><strong>Current tenant ID:</strong> ' . (int) $tenantId . '</p>';
        echo $notice;

        foreach (self::CONNECTORS as $conn) {
            $cur = $current[$conn['key']] ?? ['status' => 'disabled', 'public' => [], 'secret' => [], 'updated_at' => ''];
            echo '<div class="postbox" style="margin-top:18px;padding:16px;">';
            echo '<h2 style="margin-top:0;">' . \esc_html($conn['label']) . '</h2>';
            echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">';
            echo '<input type="hidden" name="action" value="mercato_save_connector">';
            echo '<input type="hidden" name="provider" value="' . \esc_attr($conn['key']) . '">';
            \wp_nonce_field('mercato_save_connector_' . $conn['key']);

            echo '<label style="grid-column:1/-1;"><strong>Status</strong><br>';
            echo '<select name="status">';
            foreach (['disabled', 'test', 'live'] as $s) {
                $sel = $cur['status'] === $s ? ' selected' : '';
                echo '<option value="' . $s . '"' . $sel . '>' . $s . '</option>';
            }
            echo '</select>';
            if ($cur['updated_at'] !== '') {
                echo ' <small style="margin-left:12px;color:#666;">Updated ' . \esc_html($cur['updated_at']) . '</small>';
            }
            echo '</label>';

            foreach ($conn['public_fields'] as $f) {
                $val = isset($cur['public'][$f['name']]) ? (string) $cur['public'][$f['name']] : '';
                $ph = isset($f['placeholder']) ? ' placeholder="' . \esc_attr($f['placeholder']) . '"' : '';
                echo '<label><strong>' . \esc_html($f['label']) . '</strong><br>';
                echo '<input type="text" name="public[' . \esc_attr($f['name']) . ']" value="' . \esc_attr($val) . '"' . $ph . ' style="width:100%;"></label>';
            }
            foreach ($conn['secret_fields'] as $f) {
                $has = !empty($cur['secret'][$f['name']]);
                echo '<label><strong>' . \esc_html($f['label']) . '</strong>';
                if ($has) {
                    echo ' <small style="color:#0a4f47;">(set - leave blank to keep)</small>';
                }
                echo '<br>';
                echo '<input type="password" name="secret[' . \esc_attr($f['name']) . ']" placeholder="' . ($has ? '************' : '') . '" style="width:100%;" autocomplete="new-password"></label>';
            }
            echo '<p style="grid-column:1/-1;"><button type="submit" class="button button-primary">Save ' . \esc_html(\ucfirst(\str_replace('_', ' ', $conn['key']))) . '</button></p>';
            echo '</form>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function handleSaveConnector(): void
    {
        if (!\current_user_can('manage_options')) {
            \wp_die('Insufficient permissions.');
        }
        $provider = isset($_POST['provider']) ? \sanitize_key((string) $_POST['provider']) : '';
        \check_admin_referer('mercato_save_connector_' . $provider);
        if ($provider === '') {
            \wp_die('Missing provider.');
        }

        $status = isset($_POST['status']) ? \sanitize_key((string) $_POST['status']) : 'disabled';
        $public = isset($_POST['public']) && \is_array($_POST['public']) ? \wp_unslash($_POST['public']) : [];
        $secretPosted = isset($_POST['secret']) && \is_array($_POST['secret']) ? \wp_unslash($_POST['secret']) : [];

        // Merge: blank secret field = keep existing.
        $settings = $this->container->get(IntegrationSettings::class);
        $existing = $settings->get($provider);
        $existingSecret = [];
        if ($existing && isset($existing['secret_refs'])) {
            $decoded = \json_decode((string) $existing['secret_refs'], true);
            $existingSecret = \is_array($decoded) ? $decoded : [];
        }
        $finalSecret = [];
        foreach ($secretPosted as $k => $v) {
            $k = (string) $k;
            if ($v === '' && isset($existingSecret[$k])) {
                $finalSecret[$k] = $existingSecret[$k];
            } elseif ($v !== '') {
                $finalSecret[$k] = (string) $v;
            }
        }

        $settings->set($provider, [
            'status' => $status,
            'public_config' => \array_map('strval', $public),
            'secret_refs' => $finalSecret,
        ]);

        \wp_safe_redirect(\admin_url('admin.php?page=mercato-connectors&saved=1'));
        exit;
    }

    public function renderApprovals(): void
    {
        $tenantId = $this->tenants->currentTenantId();
        global $wpdb;
        $vendors = $wpdb->prefix . 'mercato_vendors';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT vendor_id, business_name, store_slug, headline, bio, contact_email, phone, license_number, insurance_carrier, insurance_amount_minor, years_experience, hourly_rate_minor, owner_user_id, status, created_at FROM `{$vendors}` WHERE tenant_id = %d AND status IN ('pending','kyc_required') ORDER BY created_at DESC LIMIT 50",
            $tenantId
        ), ARRAY_A) ?: [];

        $notice = isset($_GET['decided']) ? '<div class="notice notice-success is-dismissible"><p>Decision recorded.</p></div>' : '';
        echo '<div class="wrap">';
        echo '<h1>Provider applications</h1>';
        echo '<p>Pending and KYC-required applicants waiting for tenant review.</p>';
        echo $notice;

        if ($rows === []) {
            echo '<p><em>No pending applications.</em></p></div>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Business</th><th>Headline</th><th>Contact</th><th>License / Insurance</th><th>Submitted</th><th style="width:220px;">Decision</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $insurance = !empty($r['insurance_amount_minor']) ? '$' . \number_format($r['insurance_amount_minor'] / 100, 0) : '-';
            echo '<tr>';
            echo '<td><strong>' . \esc_html($r['business_name']) . '</strong><br><small>@' . \esc_html($r['store_slug']) . ' - status: ' . \esc_html($r['status']) . '</small></td>';
            echo '<td>' . \esc_html($r['headline'] ?: '-') . '<br><small>' . \esc_html($r['years_experience'] ? $r['years_experience'] . '+ yrs' : '') . '</small></td>';
            echo '<td>' . \esc_html($r['contact_email'] ?: ('user #' . (int) $r['owner_user_id'])) . '<br><small>' . \esc_html($r['phone'] ?: '-') . '</small></td>';
            echo '<td>' . \esc_html($r['license_number'] ?: '-') . '<br><small>' . \esc_html($r['insurance_carrier']) . ' ' . \esc_html($insurance) . '</small></td>';
            echo '<td><small>' . \esc_html($r['created_at']) . '</small></td>';
            echo '<td>';
            $this->renderDecisionForm((int) $r['vendor_id'], 'approved', 'Approve');
            echo ' ';
            $this->renderDecisionForm((int) $r['vendor_id'], 'rejected', 'Reject');
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    private function renderDecisionForm(int $vendorId, string $status, string $label): void
    {
        echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" style="display:inline;">';
        echo '<input type="hidden" name="action" value="mercato_vendor_decision">';
        echo '<input type="hidden" name="vendor_id" value="' . (int) $vendorId . '">';
        echo '<input type="hidden" name="status" value="' . \esc_attr($status) . '">';
        \wp_nonce_field('mercato_vendor_decision_' . $vendorId);
        $cls = $status === 'approved' ? 'button-primary' : 'button';
        echo '<button type="submit" class="button ' . $cls . '">' . \esc_html($label) . '</button>';
        echo '</form>';
    }

    public function handleVendorDecision(): void
    {
        if (!\current_user_can('manage_options')) {
            \wp_die('Insufficient permissions.');
        }
        $vendorId = isset($_POST['vendor_id']) ? (int) $_POST['vendor_id'] : 0;
        \check_admin_referer('mercato_vendor_decision_' . $vendorId);
        $status = isset($_POST['status']) ? \sanitize_key((string) $_POST['status']) : '';
        if ($vendorId < 1 || !\in_array($status, ['approved', 'rejected', 'suspended'], true)) {
            \wp_die('Invalid input.');
        }
        try {
            $repo = $this->container->get(\Mercato\Vendors\Repository::class);
            $repo->setStatus($vendorId, $status);
        } catch (\Throwable $e) {
            \wp_die('Failed: ' . \esc_html($e->getMessage()));
        }
        \wp_safe_redirect(\admin_url('admin.php?page=mercato-approvals&decided=1'));
        exit;
    }
}
