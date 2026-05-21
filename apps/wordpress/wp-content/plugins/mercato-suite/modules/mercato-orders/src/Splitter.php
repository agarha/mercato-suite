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

    private function moneyToMinor(float $amount): int
    {
        return (int) \round($amount * 100);
    }
}
