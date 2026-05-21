<?php

declare(strict_types=1);

namespace Mercato\Core\WooCommerce;

use Mercato\Core\Events\Outbox;

final class HookAdapter
{
    public function __construct(private readonly Outbox $outbox)
    {
    }

    public function register(): void
    {
        if (!\function_exists('add_action')) {
            return;
        }

        \add_action('woocommerce_checkout_order_processed', [$this, 'checkoutOrderProcessed'], 10, 3);
        \add_action('woocommerce_order_status_changed', [$this, 'orderStatusChanged'], 10, 4);
        \add_action('woocommerce_product_set_stock', [$this, 'productStockChanged'], 10, 1);
        \add_action('woocommerce_refund_created', [$this, 'refundCreated'], 10, 2);
    }

    public function checkoutOrderProcessed(int $orderId, array $postedData = [], mixed $order = null): void
    {
        $this->outbox->publish('mercato.order.checkout.processed.v1', [
            'order_id' => $orderId,
            'posted_keys' => \array_keys($postedData),
        ], (string) $orderId);
    }

    public function orderStatusChanged(int $orderId, string $oldStatus, string $newStatus, mixed $order = null): void
    {
        $this->outbox->publish('mercato.order.status.changed.v1', [
            'order_id' => $orderId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ], (string) $orderId);
    }

    public function productStockChanged(mixed $product): void
    {
        $productId = \is_object($product) && \method_exists($product, 'get_id') ? (int) $product->get_id() : 0;
        $this->outbox->publish('mercato.product.stock.changed.v1', [
            'product_id' => $productId,
        ], (string) $productId);
    }

    public function refundCreated(int $refundId, array $args = []): void
    {
        $this->outbox->publish('mercato.order.refund.created.v1', [
            'refund_id' => $refundId,
            'order_id' => isset($args['order_id']) ? (int) $args['order_id'] : null,
        ], (string) ($args['order_id'] ?? $refundId));
    }
}
