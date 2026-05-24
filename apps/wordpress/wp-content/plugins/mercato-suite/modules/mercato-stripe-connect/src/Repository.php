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
    public function recordWebhook(array $payload, ?string $signature = null, ?string $rawBody = null): array
    {
        global $wpdb;

        $this->verifyWebhookSignature($rawBody ?? \wp_json_encode($payload, JSON_THROW_ON_ERROR), $signature);

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
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function createRefund(array $data): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $paymentIntentId = (string) ($data['payment_intent_id'] ?? '');
        $amount = (int) ($data['amount_minor'] ?? 0);
        if ($paymentIntentId === '' || $amount < 1) {
            throw new RuntimeException('PaymentIntent ID and positive amount are required.');
        }

        $intent = $this->paymentIntent($paymentIntentId);
        $stripe = $this->isLiveConfigured()
            ? $this->createStripeRefund($paymentIntentId, $amount)
            : [
                'id' => 're_test_' . \bin2hex(\random_bytes(8)),
                'status' => 'succeeded',
            ];

        $table = $wpdb->prefix . 'mercato_stripe_refunds';
        $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'wc_order_id' => (int) $intent['wc_order_id'],
            'stripe_payment_intent_id' => $paymentIntentId,
            'stripe_refund_id' => (string) $stripe['id'],
            'amount_minor' => $amount,
            'currency' => (string) $intent['currency'],
            'status' => (string) ($stripe['status'] ?? 'succeeded'),
        ]);

        if ($wpdb->insert_id < 1) {
            throw new RuntimeException('Unable to record Stripe refund: ' . (string) $wpdb->last_error);
        }

        $refund = [
            'stripe_refund_row_id' => (int) $wpdb->insert_id,
            'tenant_id' => $tenantId,
            'wc_order_id' => (int) $intent['wc_order_id'],
            'payment_intent_id' => $paymentIntentId,
            'stripe_refund_id' => (string) $stripe['id'],
            'amount_minor' => $amount,
            'currency' => (string) $intent['currency'],
            'status' => (string) ($stripe['status'] ?? 'succeeded'),
        ];
        $this->outbox->publish('mercato.stripe.charge.refunded.v1', $refund, (string) $refund['stripe_refund_id'], $tenantId);

        return $refund;
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
                $attempts = (int) $row['attempt_count'] + 1;
                $wpdb->update($items, [
                    'status' => 'failed',
                    'attempt_count' => $attempts,
                    'next_retry_at' => $attempts >= 3 ? null : \gmdate('Y-m-d H:i:s.v', \time() + 86400),
                    'manual_review_required' => $attempts >= 3 ? 1 : 0,
                    'last_error' => $e->getMessage(),
                ], ['payout_item_id' => (int) $row['payout_item_id'], 'tenant_id' => $tenantId]);
                $this->outbox->publish('mercato.stripe.transfer.failed.v1', [
                    'batch_id' => $batchId,
                    'payout_item_id' => (int) $row['payout_item_id'],
                    'error' => $e->getMessage(),
                ], (string) $row['payout_item_id'], $tenantId);
            }
        }

        $batches = $wpdb->prefix . 'mercato_payout_batches';
        if ($failed === 0 && $created > 0) {
            $wpdb->update($batches, ['status' => 'succeeded', 'processed_at' => \gmdate('Y-m-d H:i:s.v')], ['tenant_id' => $tenantId, 'batch_id' => $batchId]);
            $this->outbox->publish('mercato.payout.succeeded.v1', ['batch_id' => $batchId, 'created' => $created], (string) $batchId, $tenantId);
        } elseif ($failed > 0) {
            $wpdb->update($batches, ['status' => 'failed', 'processed_at' => \gmdate('Y-m-d H:i:s.v')], ['tenant_id' => $tenantId, 'batch_id' => $batchId]);
            $this->outbox->publish('mercato.payout.failed.v1', ['batch_id' => $batchId, 'created' => $created, 'failed' => $failed], (string) $batchId, $tenantId);
        }

        return ['batch_id' => $batchId, 'created' => $created, 'failed' => $failed];
    }

    private function verifyWebhookSignature(string $rawBody, ?string $signature): void
    {
        $secret = (string) \getenv('STRIPE_WEBHOOK_SECRET');
        if ($secret === '' || \str_contains($secret, 'replace_me')) {
            return;
        }

        if ($signature === null || $signature === '') {
            throw new RuntimeException('Missing Stripe webhook signature.');
        }

        $parts = [];
        foreach (\explode(',', $signature) as $part) {
            [$key, $value] = \array_pad(\explode('=', $part, 2), 2, '');
            $parts[$key][] = $value;
        }

        $timestamp = (string) ($parts['t'][0] ?? '');
        $signatures = $parts['v1'] ?? [];
        if ($timestamp === '' || $signatures === []) {
            throw new RuntimeException('Malformed Stripe webhook signature.');
        }

        if (\abs(\time() - (int) $timestamp) > 300) {
            throw new RuntimeException('Expired Stripe webhook signature.');
        }

        $expected = \hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        foreach ($signatures as $candidate) {
            if (\hash_equals($expected, $candidate)) {
                return;
            }
        }

        throw new RuntimeException('Invalid Stripe webhook signature.');
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
     * @return array<string,mixed>
     */
    private function paymentIntent(string $paymentIntentId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_stripe_payment_intents';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `stripe_payment_intent_id` = %s",
            $this->tenantResolver->currentTenantId(),
            $paymentIntentId
        ), ARRAY_A);

        if (!\is_array($row)) {
            throw new RuntimeException('Stripe PaymentIntent not found.');
        }

        return $row;
    }

    /**
     * @return array<string,mixed>
     */
    private function createStripeRefund(string $paymentIntentId, int $amountMinor): array
    {
        return $this->stripeRequest('POST', 'https://api.stripe.com/v1/refunds', [
            'payment_intent' => $paymentIntentId,
            'amount' => (string) $amountMinor,
        ]);
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
