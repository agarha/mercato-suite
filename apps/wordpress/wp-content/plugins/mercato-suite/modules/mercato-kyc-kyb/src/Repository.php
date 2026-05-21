<?php

declare(strict_types=1);

namespace Mercato\KycKyb;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

final class Repository
{
    public function __construct(private readonly Resolver $tenantResolver, private readonly Outbox $outbox)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function start(int $vendorId): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_kyc_cases';
        $session = $this->createVerificationSession($vendorId);
        $wpdb->replace($table, [
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'provider' => 'stripe_identity',
            'provider_reference' => $session['id'],
            'verification_url' => $session['url'],
            'status' => 'processing',
        ]);
        $case = $this->findByVendor($vendorId);
        $this->outbox->publish('mercato.kyc.started.v1', $case, (string) $case['case_id'], $tenantId);
        return $case;
    }

    /**
     * @return array<string,mixed>
     */
    public function updateStatus(int $vendorId, string $status): array
    {
        global $wpdb;

        $allowed = ['required', 'processing', 'verified', 'rejected'];
        if (!\in_array($status, $allowed, true)) {
            throw new RuntimeException('Invalid KYC status.');
        }

        $table = $wpdb->prefix . 'mercato_kyc_cases';
        $wpdb->update($table, ['status' => $status], [
            'tenant_id' => $this->tenantResolver->currentTenantId(),
            'vendor_id' => $vendorId,
            'provider' => 'stripe_identity',
        ]);
        $case = $this->findByVendor($vendorId);
        $this->syncVendorStatus($vendorId, $status);
        $this->outbox->publish('mercato.kyc.' . $status . '.v1', $case, (string) $case['case_id']);
        if ($status === 'verified') {
            $this->outbox->publish('mercato.vendor.kyc.completed.v1', $case, (string) $case['case_id']);
        }
        return $case;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function handleStripeWebhook(array $payload): array
    {
        global $wpdb;

        $type = (string) ($payload['type'] ?? '');
        $object = (array) ($payload['data']['object'] ?? []);
        $reference = (string) ($object['id'] ?? '');
        if ($reference === '') {
            throw new RuntimeException('Missing Stripe Identity verification session id.');
        }

        $status = match ($type) {
            'identity.verification_session.verified' => 'verified',
            'identity.verification_session.requires_input',
            'identity.verification_session.canceled' => 'rejected',
            default => 'processing',
        };

        $table = $wpdb->prefix . 'mercato_kyc_cases';
        $updated = $wpdb->update($table, [
            'status' => $status,
            'failure_reason' => isset($object['last_error']['reason']) ? (string) $object['last_error']['reason'] : null,
        ], ['provider_reference' => $reference, 'provider' => 'stripe_identity']);

        if ($updated === false || $updated === 0) {
            throw new RuntimeException('KYC case not found for Stripe Identity session.');
        }

        $case = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE `provider_reference` = %s", $reference), ARRAY_A);
        if (!\is_array($case)) {
            throw new RuntimeException('KYC case not found after webhook update.');
        }

        $this->syncVendorStatus((int) $case['vendor_id'], $status);
        $eventType = $status === 'verified' ? 'mercato.vendor.kyc.completed.v1' : 'mercato.kyc.' . $status . '.v1';
        $this->outbox->publish($eventType, $case, (string) $case['case_id'], (int) $case['tenant_id']);

        return $case;
    }

    /**
     * @return array<string,mixed>
     */
    private function findByVendor(int $vendorId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_kyc_cases';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `vendor_id` = %d", $this->tenantResolver->currentTenantId(), $vendorId), ARRAY_A);
        if (!$row) {
            throw new RuntimeException('KYC case not found.');
        }
        return $row;
    }

    /**
     * @return array{id:string,url:string}
     */
    private function createVerificationSession(int $vendorId): array
    {
        $key = (string) \getenv('STRIPE_SECRET_KEY');
        if ($key === '' || \str_contains($key, 'replace_me')) {
            $id = 'vs_test_' . $this->tenantResolver->currentTenantId() . '_' . $vendorId . '_' . \strtolower(\bin2hex(\random_bytes(4)));
            return ['id' => $id, 'url' => \home_url('/?mercato_identity_session=' . \rawurlencode($id))];
        }

        $response = \wp_remote_post('https://api.stripe.com/v1/identity/verification_sessions', [
            'headers' => ['Authorization' => 'Bearer ' . $key],
            'body' => [
                'type' => 'document',
                'metadata[vendor_id]' => (string) $vendorId,
                'return_url' => \home_url('/?mercato_identity=return'),
            ],
            'timeout' => 20,
        ]);

        if (\is_wp_error($response)) {
            throw new RuntimeException($response->get_error_message());
        }

        $status = (int) \wp_remote_retrieve_response_code($response);
        $decoded = \json_decode((string) \wp_remote_retrieve_body($response), true);
        if ($status >= 400 || !\is_array($decoded)) {
            throw new RuntimeException('Stripe Identity session creation failed.');
        }

        return ['id' => (string) $decoded['id'], 'url' => (string) ($decoded['url'] ?? '')];
    }

    private function syncVendorStatus(int $vendorId, string $kycStatus): void
    {
        global $wpdb;

        $vendorStatus = match ($kycStatus) {
            'verified' => 'approved',
            'rejected' => 'rejected',
            default => 'kyc_required',
        };
        $wpdb->update($wpdb->prefix . 'mercato_vendors', ['status' => $vendorStatus], [
            'tenant_id' => $this->tenantResolver->currentTenantId(),
            'vendor_id' => $vendorId,
        ]);
    }
}
