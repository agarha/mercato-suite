<?php

declare(strict_types=1);

namespace Mercato\StripeConnect;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

final class Repository
{
    public function __construct(private readonly Resolver $tenantResolver, private readonly Outbox $outbox)
    {
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function createAccount(int $vendorId, array $data = []): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $stripeAccountId = $this->isLiveConfigured()
            ? $this->createStripeAccount($vendorId, $data)
            : 'acct_test_' . $tenantId . '_' . $vendorId;
        $onboardingUrl = $this->isLiveConfigured()
            ? $this->createAccountLink($stripeAccountId)
            : \home_url('/wp-admin/admin.php?page=mercato-stripe-test&account=' . \rawurlencode($stripeAccountId));

        $accounts = $wpdb->prefix . 'mercato_stripe_accounts';
        $wpdb->replace($accounts, [
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'stripe_account_id' => $stripeAccountId,
            'onboarding_status' => 'pending',
            'charges_enabled' => 0,
            'payouts_enabled' => 0,
            'details_submitted' => 0,
            'onboarding_url' => $onboardingUrl,
        ]);

        $vendors = $wpdb->prefix . 'mercato_vendors';
        $wpdb->update($vendors, [
            'stripe_account_id' => $stripeAccountId,
            'status' => 'kyc_required',
        ], ['tenant_id' => $tenantId, 'vendor_id' => $vendorId]);

        $account = $this->account($vendorId);
        $this->outbox->publish('mercato.stripe.account.updated.v1', $account, (string) $vendorId, $tenantId);

        return $account;
    }

    /**
     * @return array<string,mixed>
     */
    public function account(int $vendorId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_stripe_accounts';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE tenant_id = %d AND vendor_id = %d",
            $this->tenantResolver->currentTenantId(),
            $vendorId
        ), ARRAY_A);

        if (!\is_array($row)) {
            throw new RuntimeException('Stripe account not found.');
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function recordWebhook(array $payload, ?string $signature = null): array
    {
        global $wpdb;

        $eventId = (string) ($payload['id'] ?? ('evt_local_' . \bin2hex(\random_bytes(8))));
        $type = (string) ($payload['type'] ?? 'unknown');
        $events = $wpdb->prefix . 'mercato_stripe_webhook_events';
        $wpdb->replace($events, [
            'event_id' => $eventId,
            'type' => $type,
            'payload' => \wp_json_encode(['payload' => $payload, 'signature' => $signature], JSON_THROW_ON_ERROR),
        ]);

        if ($type === 'account.updated') {
            $this->applyAccountUpdated((array) ($payload['data']['object'] ?? []));
        }

        return ['event_id' => $eventId, 'type' => $type, 'recorded' => true];
    }

    /**
     * @return array<string,mixed>
     */
    public function executePayoutBatch(int $batchId): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $items = $wpdb->prefix . 'mercato_payout_items';
        $accounts = $wpdb->prefix . 'mercato_stripe_accounts';
        $transfers = $wpdb->prefix . 'mercato_stripe_transfers';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, a.stripe_account_id
             FROM {$items} i
             INNER JOIN {$accounts} a ON a.tenant_id = i.tenant_id AND a.vendor_id = i.vendor_id
             WHERE i.tenant_id = %d AND i.batch_id = %d AND i.status = 'scheduled'",
            $tenantId,
            $batchId
        ), ARRAY_A) ?: [];

        $created = 0;
        $failed = 0;
        foreach ($rows as $row) {
            try {
                $stripeTransferId = $this->isLiveConfigured()
                    ? $this->createTransfer((string) $row['stripe_account_id'], (int) $row['amount_minor'], (string) $row['currency'])
                    : 'tr_test_' . (int) $row['payout_item_id'];

                $wpdb->replace($transfers, [
                    'tenant_id' => $tenantId,
                    'batch_id' => $batchId,
                    'payout_item_id' => (int) $row['payout_item_id'],
                    'vendor_id' => (int) $row['vendor_id'],
                    'stripe_account_id' => (string) $row['stripe_account_id'],
                    'stripe_transfer_id' => $stripeTransferId,
                    'amount_minor' => (int) $row['amount_minor'],
                    'currency' => (string) $row['currency'],
                    'status' => 'succeeded',
                ]);
                $wpdb->update($items, ['status' => 'succeeded'], ['payout_item_id' => (int) $row['payout_item_id'], 'tenant_id' => $tenantId]);
                $this->outbox->publish('mercato.stripe.transfer.created.v1', [
                    'batch_id' => $batchId,
                    'payout_item_id' => (int) $row['payout_item_id'],
                    'stripe_transfer_id' => $stripeTransferId,
                ], (string) $row['payout_item_id'], $tenantId);
                ++$created;
            } catch (\Throwable $e) {
                ++$failed;
                $wpdb->update($items, ['status' => 'failed'], ['payout_item_id' => (int) $row['payout_item_id'], 'tenant_id' => $tenantId]);
                $this->outbox->publish('mercato.stripe.transfer.failed.v1', [
                    'batch_id' => $batchId,
                    'payout_item_id' => (int) $row['payout_item_id'],
                    'error' => $e->getMessage(),
                ], (string) $row['payout_item_id'], $tenantId);
            }
        }

        return ['batch_id' => $batchId, 'created' => $created, 'failed' => $failed];
    }

    /**
     * @param array<string,mixed> $object
     */
    private function applyAccountUpdated(array $object): void
    {
        global $wpdb;

        $accountId = (string) ($object['id'] ?? '');
        if ($accountId === '') {
            return;
        }

        $status = !empty($object['charges_enabled']) && !empty($object['payouts_enabled']) ? 'complete' : 'restricted';
        $accounts = $wpdb->prefix . 'mercato_stripe_accounts';
        $wpdb->update($accounts, [
            'onboarding_status' => $status,
            'charges_enabled' => empty($object['charges_enabled']) ? 0 : 1,
            'payouts_enabled' => empty($object['payouts_enabled']) ? 0 : 1,
            'details_submitted' => empty($object['details_submitted']) ? 0 : 1,
        ], ['stripe_account_id' => $accountId]);

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$accounts} WHERE stripe_account_id = %s", $accountId), ARRAY_A);
        if (\is_array($row)) {
            $this->outbox->publish('mercato.stripe.account.updated.v1', $row, (string) $row['vendor_id'], (int) $row['tenant_id']);
        }
    }

    private function isLiveConfigured(): bool
    {
        $key = (string) \getenv('STRIPE_SECRET_KEY');
        return \str_starts_with($key, 'sk_test_') && !\str_contains($key, 'replace_me');
    }

    /**
     * @param array<string,mixed> $data
     */
    private function createStripeAccount(int $vendorId, array $data): string
    {
        $response = $this->stripeRequest('POST', 'https://api.stripe.com/v1/accounts', [
            'type' => 'express',
            'country' => (string) ($data['country'] ?? 'US'),
            'email' => (string) ($data['email'] ?? ('vendor-' . $vendorId . '@example.invalid')),
            'capabilities[card_payments][requested]' => 'true',
            'capabilities[transfers][requested]' => 'true',
        ]);

        return (string) $response['id'];
    }

    private function createAccountLink(string $stripeAccountId): string
    {
        $response = $this->stripeRequest('POST', 'https://api.stripe.com/v1/account_links', [
            'account' => $stripeAccountId,
            'refresh_url' => \home_url('/?mercato_stripe=refresh'),
            'return_url' => \home_url('/?mercato_stripe=return'),
            'type' => 'account_onboarding',
        ]);

        return (string) $response['url'];
    }

    private function createTransfer(string $stripeAccountId, int $amountMinor, string $currency): string
    {
        $response = $this->stripeRequest('POST', 'https://api.stripe.com/v1/transfers', [
            'amount' => (string) $amountMinor,
            'currency' => \strtolower($currency),
            'destination' => $stripeAccountId,
        ]);

        return (string) $response['id'];
    }

    /**
     * @param array<string,string> $body
     * @return array<string,mixed>
     */
    private function stripeRequest(string $method, string $url, array $body): array
    {
        $response = \wp_remote_request($url, [
            'method' => $method,
            'headers' => ['Authorization' => 'Bearer ' . (string) \getenv('STRIPE_SECRET_KEY')],
            'body' => $body,
            'timeout' => 20,
        ]);

        if (\is_wp_error($response)) {
            throw new RuntimeException($response->get_error_message());
        }

        $status = (int) \wp_remote_retrieve_response_code($response);
        $decoded = \json_decode((string) \wp_remote_retrieve_body($response), true);
        if ($status >= 400 || !\is_array($decoded)) {
            throw new RuntimeException('Stripe API request failed.');
        }

        return $decoded;
    }
}
