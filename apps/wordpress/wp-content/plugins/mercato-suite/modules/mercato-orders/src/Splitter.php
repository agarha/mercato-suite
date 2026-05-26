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
            $offeringId = $this->offeringIdForItem($item);
            $vendorId = $offeringId > 0 ? $this->vendorIdForOffering($offeringId, $productId) : 0;
            if ($vendorId < 1) {
                $vendorId = \function_exists('get_post_meta') ? (int) \get_post_meta($productId, '_mercato_vendor_id', true) : 0;
            }
            if ($vendorId < 1) {
                continue;
            }

            $groups[$vendorId][] = [
                'wc_order_item_id' => (int) $itemId,
                'wc_product_id' => $productId,
                'offering_id' => $offeringId > 0 ? $offeringId : null,
                'title' => $item->get_name(),
                'quantity' => (int) $item->get_quantity(),
                'line_subtotal_minor' => $this->moneyToMinor((float) $item->get_subtotal()),
                'line_total_minor' => $this->moneyToMinor((float) $item->get_total()),
                'discount_minor' => \max(0, $this->moneyToMinor((float) $item->get_subtotal()) - $this->moneyToMinor((float) $item->get_total())),
                'tax_minor' => $this->moneyToMinor((float) $item->get_total_tax()),
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
        $lineSubtotal = \array_sum(\array_column($items, 'line_subtotal_minor'));
        $discount = \array_sum(\array_column($items, 'discount_minor'));
        $subtotal = \array_sum(\array_column($items, 'line_total_minor'));
        $tax = \array_sum(\array_column($items, 'tax_minor'));
        $shipping = $this->shippingForVendor($order, $vendorId, $subtotal);
        $total = $subtotal + $tax + $shipping;
        $suborders = $wpdb->prefix . 'mercato_suborders';
        $inserted = $wpdb->replace($suborders, [
            'tenant_id' => $tenantId,
            'wc_order_id' => $order->get_id(),
            'vendor_id' => $vendorId,
            'currency' => $order->get_currency(),
            'subtotal_minor' => $lineSubtotal,
            'discount_minor' => $discount,
            'shipping_minor' => $shipping,
            'tax_minor' => $tax,
            'total_minor' => $total,
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
                'offering_id' => $item['offering_id'],
                'title' => $item['title'],
                'quantity' => $item['quantity'],
                'line_subtotal_minor' => $item['line_subtotal_minor'],
                'discount_minor' => $item['discount_minor'],
                'line_total_minor' => $item['line_total_minor'],
                'tax_minor' => $item['tax_minor'],
            ]);
        }

        $this->outbox->publish('mercato.order.suborder.created.v1', [
            'suborder_id' => $suborderId,
            'wc_order_id' => $order->get_id(),
            'vendor_id' => $vendorId,
            'item_count' => \count($items),
            'subtotal_minor' => $lineSubtotal,
            'discount_minor' => $discount,
            'shipping_minor' => $shipping,
            'tax_minor' => $tax,
            'total_minor' => $total,
        ], (string) $suborderId, $tenantId);

        if (\function_exists('do_action')) {
            \do_action('mercato_suborder_created', [
                'suborder_id' => $suborderId,
                'tenant_id' => $tenantId,
                'wc_order_id' => $order->get_id(),
                'vendor_id' => $vendorId,
                'total_minor' => $total,
                'currency' => $order->get_currency(),
            ]);
        }

        return $suborderId;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function updateSuborder(int $suborderId, array $data): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $suborders = $wpdb->prefix . 'mercato_suborders';
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$suborders}` WHERE `tenant_id` = %d AND `suborder_id` = %d",
            $tenantId,
            $suborderId
        ), ARRAY_A);

        if (!\is_array($existing)) {
            throw new RuntimeException('Suborder not found.');
        }

        $allowedStatuses = ['created', 'acknowledged', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded'];
        $update = [];
        if (isset($data['status'])) {
            $status = (string) $data['status'];
            if (!\in_array($status, $allowedStatuses, true)) {
                throw new RuntimeException('Unsupported suborder status.');
            }
            $update['status'] = $status;
        }
        if (isset($data['tracking_carrier'])) {
            $update['tracking_carrier'] = \sanitize_text_field((string) $data['tracking_carrier']);
        }
        if (isset($data['tracking_number'])) {
            $update['tracking_number'] = \sanitize_text_field((string) $data['tracking_number']);
        }

        if ($update === []) {
            return $existing;
        }

        $wpdb->update($suborders, $update, [
            'tenant_id' => $tenantId,
            'suborder_id' => $suborderId,
        ]);

        if (($update['tracking_carrier'] ?? '') !== '' && ($update['tracking_number'] ?? '') !== '') {
            $wpdb->insert($wpdb->prefix . 'mercato_order_shipments', [
                'suborder_id' => $suborderId,
                'carrier' => (string) $update['tracking_carrier'],
                'tracking_number' => (string) $update['tracking_number'],
            ]);
        }

        $updated = $this->findSuborder($tenantId, $suborderId);
        $this->outbox->publish('mercato.order.suborder.updated.v1', $updated, (string) $suborderId, $tenantId);
        if (($updated['status'] ?? '') === 'shipped') {
            $this->outbox->publish('mercato.order.suborder.shipped.v1', $updated, (string) $suborderId, $tenantId);
        }

        return $updated;
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

    /**
     * @return array<string,mixed>
     */
    private function findSuborder(int $tenantId, int $suborderId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_suborders';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `suborder_id` = %d",
            $tenantId,
            $suborderId
        ), ARRAY_A);

        if (!\is_array($row)) {
            throw new RuntimeException('Suborder not found.');
        }

        return $row;
    }

    private function shippingForVendor(WC_Order $order, int $vendorId, int $vendorSubtotal): int
    {
        $shippingTotal = $this->moneyToMinor((float) $order->get_shipping_total());
        if ($shippingTotal < 1) {
            return 0;
        }

        $orderSubtotal = $this->moneyToMinor((float) $order->get_total() - (float) $order->get_shipping_total() - (float) $order->get_total_tax());
        if ($orderSubtotal < 1) {
            return 0;
        }

        $allocated = (int) \round($shippingTotal * ($vendorSubtotal / $orderSubtotal));
        return \max(0, $allocated);
    }

    private function moneyToMinor(float $amount): int
    {
        return (int) \round($amount * 100);
    }

    private function offeringIdForItem(object $item): int
    {
        foreach (['_mercato_offering_id', 'mercato_offering_id', 'offering_id'] as $key) {
            if (\method_exists($item, 'get_meta')) {
                $value = $item->get_meta($key, true);
                if ((int) $value > 0) {
                    return (int) $value;
                }
            }
        }

        return 0;
    }

    private function vendorIdForOffering(int $offeringId, int $wcProductId): int
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $offerings = $wpdb->prefix . 'mercato_vendor_service_offerings';
        $products = $wpdb->prefix . 'mercato_products';
        $vendorId = $wpdb->get_var($wpdb->prepare(
            "SELECT o.`vendor_id`
             FROM `{$offerings}` o
             INNER JOIN `{$products}` p ON p.`tenant_id` = o.`tenant_id` AND p.`product_id` = o.`product_id`
             WHERE o.`tenant_id` = %d AND o.`offering_id` = %d AND o.`status` = 'active' AND p.`wc_product_id` = %d
             LIMIT 1",
            $tenantId,
            $offeringId,
            $wcProductId
        ));

        return $vendorId === null ? 0 : (int) $vendorId;
    }
}
