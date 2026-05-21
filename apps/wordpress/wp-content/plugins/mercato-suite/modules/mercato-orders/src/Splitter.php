<?php

declare(strict_types=1);

namespace Mercato\Orders;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;
use WC_Order;

final class Splitter
{
    public function __construct(
        private readonly Resolver $tenantResolver,
        private readonly Outbox $outbox,
    ) {
    }

    /**
     * @return list<int> suborder IDs
     */
    public function split(int $wcOrderId): array
    {
        if (!\function_exists('wc_get_order')) {
            throw new RuntimeException('WooCommerce is required to split orders.');
        }

        $order = \wc_get_order($wcOrderId);
        if (!$order instanceof WC_Order) {
            throw new RuntimeException('WooCommerce order not found.');
        }

        $groups = $this->groupItemsByVendor($order);
        $suborderIds = [];

        foreach ($groups as $vendorId => $items) {
            $suborderIds[] = $this->createSuborder($order, (int) $vendorId, $items);
        }

        return $suborderIds;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function markPaymentComplete(int $wcOrderId): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $suborders = $wpdb->prefix . 'mercato_suborders';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$suborders}` WHERE `tenant_id` = %d AND `wc_order_id` = %d",
            $tenantId,
            $wcOrderId
        ), ARRAY_A) ?: [];

        $paymentIntentId = $this->paymentIntentForOrder($tenantId, $wcOrderId);
        foreach ($rows as $row) {
            $wpdb->update($suborders, [
                'payment_status' => 'paid',
                'payment_intent_id' => $paymentIntentId,
                'paid_at' => \gmdate('Y-m-d H:i:s.v'),
            ], [
                'tenant_id' => $tenantId,
                'suborder_id' => (int) $row['suborder_id'],
            ]);

            $event = [
                'suborder_id' => (int) $row['suborder_id'],
                'wc_order_id' => $wcOrderId,
                'vendor_id' => (int) $row['vendor_id'],
                'payment_intent_id' => $paymentIntentId,
                'total_minor' => (int) $row['total_minor'],
            ];
            $this->outbox->publish('mercato.payment.complete.v1', $event, (string) $row['suborder_id'], $tenantId);
        }

        return $this->subordersForOrder($tenantId, $wcOrderId);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function refundSuborder(int $suborderId, array $data): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $suborders = $wpdb->prefix . 'mercato_suborders';
        $refunds = $wpdb->prefix . 'mercato_refunds';
        $suborder = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$suborders}` WHERE `tenant_id` = %d AND `suborder_id` = %d",
            $tenantId,
            $suborderId
        ), ARRAY_A);

        if (!\is_array($suborder)) {
            throw new RuntimeException('Suborder not found.');
        }

        $remaining = (int) $suborder['total_minor'] - (int) $suborder['refunded_minor'];
        $amount = (int) ($data['amount_minor'] ?? $remaining);
        if ($amount < 1 || $amount > $remaining) {
            throw new RuntimeException('Refund amount exceeds refundable balance.');
        }

        $wpdb->insert($refunds, [
            'tenant_id' => $tenantId,
            'wc_order_id' => (int) $suborder['wc_order_id'],
            'suborder_id' => $suborderId,
            'vendor_id' => (int) $suborder['vendor_id'],
            'stripe_refund_id' => (string) ($data['stripe_refund_id'] ?? ''),
            'amount_minor' => $amount,
            'currency' => (string) $suborder['currency'],
            'reason' => (string) ($data['reason'] ?? 'requested_by_customer'),
            'status' => (string) ($data['status'] ?? 'succeeded'),
        ]);

        if ($wpdb->insert_id < 1) {
            throw new RuntimeException('Unable to record refund: ' . (string) $wpdb->last_error);
        }

        $refundId = (int) $wpdb->insert_id;
        $refunded = (int) $suborder['refunded_minor'] + $amount;
        $paymentStatus = $refunded >= (int) $suborder['total_minor'] ? 'refunded' : 'partially_refunded';
        $status = $paymentStatus === 'refunded' ? 'refunded' : (string) $suborder['status'];
        $wpdb->update($suborders, [
            'refunded_minor' => $refunded,
            'payment_status' => $paymentStatus,
            'status' => $status,
        ], [
            'tenant_id' => $tenantId,
            'suborder_id' => $suborderId,
        ]);

        $refund = $this->findRefund($tenantId, $refundId);
        $this->outbox->publish('mercato.refund.created.v1', $refund, (string) $refundId, $tenantId);
        if (\function_exists('do_action')) {
            \do_action('mercato_refund_created', $refund);
        }

        return $refund;
    }

    /**
     * @return array<int,list<array<string,mixed>>>
     */
    private function groupItemsByVendor(WC_Order $order): array
    {
        $groups = [];

        foreach ($order->get_items() as $itemId => $item) {
            $productId = (int) $item->get_product_id();
            $vendorId = \function_exists('get_post_meta') ? (int) \get_post_meta($productId, '_mercato_vendor_id', true) : 0;
            if ($vendorId < 1) {
                continue;
            }

            $groups[$vendorId][] = [
                'wc_order_item_id' => (int) $itemId,
                'wc_product_id' => $productId,
                'title' => $item->get_name(),
                'quantity' => (int) $item->get_quantity(),
                'line_total_minor' => $this->moneyToMinor((float) $item->get_total()),
            ];
        }

        return $groups;
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    private function createSuborder(WC_Order $order, int $vendorId, array $items): int
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $subtotal = \array_sum(\array_column($items, 'line_total_minor'));
        $suborders = $wpdb->prefix . 'mercato_suborders';
        $inserted = $wpdb->replace($suborders, [
            'tenant_id' => $tenantId,
            'wc_order_id' => $order->get_id(),
            'vendor_id' => $vendorId,
            'currency' => $order->get_currency(),
            'subtotal_minor' => $subtotal,
            'total_minor' => $subtotal,
        ]);

        if ($inserted === false) {
            throw new RuntimeException('Unable to create suborder: ' . (string) $wpdb->last_error);
        }

        $suborderId = (int) $wpdb->insert_id;
        $itemsTable = $wpdb->prefix . 'mercato_suborder_items';

        foreach ($items as $item) {
            $wpdb->insert($itemsTable, [
                'suborder_id' => $suborderId,
                'wc_order_item_id' => $item['wc_order_item_id'],
                'wc_product_id' => $item['wc_product_id'],
                'title' => $item['title'],
                'quantity' => $item['quantity'],
                'line_total_minor' => $item['line_total_minor'],
            ]);
        }

        $this->outbox->publish('mercato.order.suborder.created.v1', [
            'suborder_id' => $suborderId,
            'wc_order_id' => $order->get_id(),
            'vendor_id' => $vendorId,
            'item_count' => \count($items),
            'total_minor' => $subtotal,
        ], (string) $suborderId, $tenantId);

        if (\function_exists('do_action')) {
            \do_action('mercato_suborder_created', [
                'suborder_id' => $suborderId,
                'tenant_id' => $tenantId,
                'wc_order_id' => $order->get_id(),
                'vendor_id' => $vendorId,
                'total_minor' => $subtotal,
                'currency' => $order->get_currency(),
            ]);
        }

        return $suborderId;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function subordersForOrder(int $tenantId, int $wcOrderId): array
    {
        global $wpdb;

        $suborders = $wpdb->prefix . 'mercato_suborders';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$suborders}` WHERE `tenant_id` = %d AND `wc_order_id` = %d ORDER BY `suborder_id` ASC",
            $tenantId,
            $wcOrderId
        ), ARRAY_A) ?: [];
    }

    private function paymentIntentForOrder(int $tenantId, int $wcOrderId): ?string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_stripe_payment_intents';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT `stripe_payment_intent_id` FROM `{$table}` WHERE `tenant_id` = %d AND `wc_order_id` = %d ORDER BY `payment_intent_id` DESC LIMIT 1",
            $tenantId,
            $wcOrderId
        )) ?: null;
    }

    /**
     * @return array<string,mixed>
     */
    private function findRefund(int $tenantId, int $refundId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_refunds';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `refund_id` = %d",
            $tenantId,
            $refundId
        ), ARRAY_A);

        if (!\is_array($row)) {
            throw new RuntimeException('Refund not found.');
        }

        return $row;
    }

    private function moneyToMinor(float $amount): int
    {
        return (int) \round($amount * 100);
    }
}
