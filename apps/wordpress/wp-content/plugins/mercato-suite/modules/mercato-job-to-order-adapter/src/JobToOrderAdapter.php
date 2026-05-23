<?php

declare(strict_types=1);

namespace Mercato\Adapters\JobToOrder;

use Mercato\Core\Audit\Writer;
use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

/**
 * Mints a WooCommerce order from an awarded Mercato job/bid.
 *
 * This is the ONLY place in the suite that may call wc_create_order()
 * (see CODEX_DIRECTIVE.md §5.1).
 *
 * Inputs:
 *   - tenant_id     (int)    tenant the job belongs to
 *   - job_id        (int)    Mercato job ID minted by award flow
 *   - buyer_user_id (int)    wp_users.ID of the buyer
 *   - provider_id   (int)    awarded provider
 *   - amount_cents  (int)    bid amount in minor units
 *   - currency      (string) ISO-4217
 *   - description   (string) human label for the line item
 *
 * Output:
 *   ['wc_order_id' => int, 'job_id' => int, 'tenant_id' => int]
 *
 * Failures emit mercato.adapter.job_to_order.failed.v1 to the outbox
 * so the bid award flow can compensate.
 */
final class JobToOrderAdapter
{
    public function __construct(
        private readonly Resolver $tenants,
        private readonly Outbox $outbox,
        private readonly Writer $audit,
    ) {
    }

    /**
     * @param array{tenant_id:int,job_id:int,buyer_user_id:int,provider_id:int,amount_cents:int,currency:string,description?:string} $award
     *
     * @return array{wc_order_id:int,job_id:int,tenant_id:int}
     */
    public function createOrderFromAward(array $award): array
    {
        $this->assertWooCommerceAvailable();

        $tenantId = (int) $award['tenant_id'];
        $jobId = (int) $award['job_id'];

        $order = \wc_create_order([
            'customer_id' => (int) $award['buyer_user_id'],
            'status' => 'pending',
            'created_via' => 'mercato-job-to-order',
        ]);

        if (\is_wp_error($order)) {
            $this->emitFailure($tenantId, $jobId, (string) $order->get_error_message());
            throw new RuntimeException('Unable to create WC order: ' . $order->get_error_message());
        }

        $currency = \strtoupper((string) $award['currency']);
        $amount = \number_format($award['amount_cents'] / 100, 2, '.', '');
        $label = (string) ($award['description'] ?? 'Mercato job #' . $jobId);

        $itemId = $order->add_product(null, 1, [
            'name' => $label,
            'subtotal' => $amount,
            'total' => $amount,
        ]);

        $order->set_currency($currency);

        // Line-item meta — the canonical link between WC order and Mercato job.
        if ($itemId > 0) {
            \wc_add_order_item_meta((int) $itemId, '_mercato_job_id', $jobId, true);
            \wc_add_order_item_meta((int) $itemId, '_mercato_tenant_id', $tenantId, true);
            \wc_add_order_item_meta((int) $itemId, '_mercato_provider_id', (int) $award['provider_id'], true);
        }

        // Order-level meta — duplicated for query convenience; line items remain the source of truth.
        $order->update_meta_data('_mercato_job_id', $jobId);
        $order->update_meta_data('_mercato_tenant_id', $tenantId);
        $order->update_meta_data('_mercato_provider_id', (int) $award['provider_id']);

        $order->calculate_totals(false);
        $orderId = (int) $order->save();

        $payload = [
            'wc_order_id' => $orderId,
            'job_id' => $jobId,
            'tenant_id' => $tenantId,
            'provider_id' => (int) $award['provider_id'],
            'amount_cents' => (int) $award['amount_cents'],
            'currency' => $currency,
        ];

        $this->outbox->publish('mercato.adapter.job_to_order.created.v1', $payload, $tenantId);
        $this->audit->log('adapter.job_to_order.created', 'job', $jobId, null, $payload);

        return [
            'wc_order_id' => $orderId,
            'job_id' => $jobId,
            'tenant_id' => $tenantId,
        ];
    }

    private function assertWooCommerceAvailable(): void
    {
        if (!\function_exists('wc_create_order')) {
            throw new RuntimeException('WooCommerce is not loaded; JobToOrder adapter cannot mint an order.');
        }
    }

    private function emitFailure(int $tenantId, int $jobId, string $reason): void
    {
        $this->outbox->publish(
            'mercato.adapter.job_to_order.failed.v1',
            ['tenant_id' => $tenantId, 'job_id' => $jobId, 'reason' => $reason],
            $tenantId
        );
    }
}
