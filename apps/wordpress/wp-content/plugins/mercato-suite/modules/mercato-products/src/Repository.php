<?php

declare(strict_types=1);

namespace Mercato\Products;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;
use WC_Product_Simple;

final class Repository
{
    public function __construct(
        private readonly Resolver $tenantResolver,
        private readonly Outbox $outbox,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function create(array $data): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $vendorId = (int) ($data['vendor_id'] ?? 0);
        $title = $this->cleanText((string) ($data['title'] ?? ''));
        $priceMinor = (int) ($data['price_minor'] ?? 0);

        if ($vendorId < 1 || $title === '' || $priceMinor < 0) {
            throw new RuntimeException('vendor_id, title, and non-negative price_minor are required.');
        }

        $status = (string) ($data['status'] ?? 'draft');
        $this->assertVendorCanPublish($vendorId, $status);

        $wcProductId = $this->createWooProduct($data);
        $table = $wpdb->prefix . 'mercato_products';
        $inserted = $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'wc_product_id' => $wcProductId,
            'status' => $status,
            'title' => $title,
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'sku' => isset($data['sku']) ? $this->cleanText((string) $data['sku']) : null,
            'price_minor' => $priceMinor,
            'currency' => 'USD',
            'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
        ]);

        if ($inserted === false) {
            throw new RuntimeException('Unable to create product: ' . (string) $wpdb->last_error);
        }

        $product = $this->find((int) $wpdb->insert_id);
        $this->outbox->publish('mercato.product.created.v1', $product, (string) $product['product_id'], $tenantId);

        return $product;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function list(?int $vendorId = null): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_products';
        $tenantId = $this->tenantResolver->currentTenantId();

        if ($vendorId !== null && $vendorId > 0) {
            return $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `vendor_id` = %d ORDER BY `created_at` DESC", $tenantId, $vendorId),
                ARRAY_A
            ) ?: [];
        }

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `{$table}` WHERE `tenant_id` = %d ORDER BY `created_at` DESC", $tenantId),
            ARRAY_A
        ) ?: [];
    }

    /**
     * @return array<string,mixed>
     */
    public function archive(int $productId): array
    {
        global $wpdb;

        $product = $this->find($productId);
        $table = $wpdb->prefix . 'mercato_products';
        $updated = $wpdb->update($table, [
            'status' => 'archived',
            'archived_at' => \gmdate('Y-m-d H:i:s.v'),
        ], [
            'tenant_id' => $this->tenantResolver->currentTenantId(),
            'product_id' => $productId,
        ]);

        if ($updated === false) {
            throw new RuntimeException('Unable to archive product: ' . (string) $wpdb->last_error);
        }

        if (!empty($product['wc_product_id']) && \function_exists('wp_update_post')) {
            \wp_update_post(['ID' => (int) $product['wc_product_id'], 'post_status' => 'draft']);
        }

        $archived = $this->find($productId);
        $this->outbox->publish('mercato.product.archived.v1', $archived, (string) $productId);

        return $archived;
    }

    /**
     * @return array<string,mixed>
     */
    public function find(int $productId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_products';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `product_id` = %d", $this->tenantResolver->currentTenantId(), $productId),
            ARRAY_A
        );

        if (!$row) {
            throw new RuntimeException('Product not found.');
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function createWooProduct(array $data): ?int
    {
        if (!\class_exists(WC_Product_Simple::class)) {
            return null;
        }

        $product = new WC_Product_Simple();
        $product->set_name($this->cleanText((string) $data['title']));
        $product->set_status(((string) ($data['status'] ?? 'draft')) === 'active' ? 'publish' : 'draft');
        $product->set_description((string) ($data['description'] ?? ''));
        $product->set_regular_price(\number_format(((int) ($data['price_minor'] ?? 0)) / 100, 2, '.', ''));
        $product->set_manage_stock(true);
        $product->set_stock_quantity((int) ($data['stock_quantity'] ?? 0));
        if (!empty($data['sku'])) {
            $product->set_sku($this->cleanText((string) $data['sku']));
        }

        $productId = (int) $product->save();
        if ($productId > 0 && \function_exists('update_post_meta') && isset($data['vendor_id'])) {
            \update_post_meta($productId, '_mercato_vendor_id', (int) $data['vendor_id']);
        }

        return $productId;
    }

    private function assertVendorCanPublish(int $vendorId, string $productStatus): void
    {
        if ($productStatus !== 'active') {
            return;
        }

        global $wpdb;

        $vendors = $wpdb->prefix . 'mercato_vendors';
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM `{$vendors}` WHERE tenant_id = %d AND vendor_id = %d",
            $this->tenantResolver->currentTenantId(),
            $vendorId
        ));

        if ($status !== 'approved') {
            throw new RuntimeException('Vendor must be approved before publishing active products.');
        }
    }

    private function cleanText(string $value): string
    {
        return \function_exists('sanitize_text_field') ? \sanitize_text_field($value) : \trim($value);
    }
}
