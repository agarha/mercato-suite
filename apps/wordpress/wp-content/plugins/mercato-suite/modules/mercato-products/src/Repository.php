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
        $categoryIds = $this->normalizeIds($data['category_ids'] ?? []);
        if ($categoryIds !== []) {
            $this->assignCategories((int) $product['product_id'], $categoryIds);
            $product = $this->find((int) $product['product_id']);
        }

        $this->upsertOffering((int) $product['product_id'], $vendorId, [
            'status' => $status === 'active' ? 'active' : 'draft',
            'price_minor' => $priceMinor,
            'currency' => 'USD',
            'duration_minutes' => isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
            'lead_time_minutes' => isset($data['lead_time_minutes']) ? (int) $data['lead_time_minutes'] : null,
            'capacity' => isset($data['capacity']) ? (int) $data['capacity'] : null,
        ]);

        $this->outbox->publish('mercato.product.created.v1', $product, (string) $product['product_id'], $tenantId);

        return $product;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function list(?int $vendorId = null, ?int $categoryId = null, ?float $latitude = null, ?float $longitude = null, ?float $radiusKm = null): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_products';
        $categories = $wpdb->prefix . 'mercato_product_categories';
        $offerings = $wpdb->prefix . 'mercato_vendor_service_offerings';
        $locations = $wpdb->prefix . 'mercato_vendor_locations';
        $tenantId = $this->tenantResolver->currentTenantId();
        $joins = [];
        $selectParams = [];
        $joinParams = [];
        $where = ['p.`tenant_id` = %d'];
        $whereParams = [$tenantId];
        $havingParams = [];

        if ($vendorId !== null && $vendorId > 0) {
            $joins[] = "INNER JOIN `{$offerings}` o ON o.`tenant_id` = p.`tenant_id` AND o.`product_id` = p.`product_id` AND o.`vendor_id` = %d AND o.`status` = 'active'";
            $joinParams[] = $vendorId;
        }

        if ($categoryId !== null && $categoryId > 0) {
            $joins[] = "INNER JOIN `{$categories}` pc ON pc.`tenant_id` = p.`tenant_id` AND pc.`product_id` = p.`product_id` AND pc.`category_id` = %d";
            $joinParams[] = $categoryId;
        }

        $distanceSelect = '';
        $distanceWhere = '';
        if ($latitude !== null && $longitude !== null && $radiusKm !== null && $radiusKm > 0) {
            if ($vendorId === null || $vendorId < 1) {
                $joins[] = "INNER JOIN `{$offerings}` geo_o ON geo_o.`tenant_id` = p.`tenant_id` AND geo_o.`product_id` = p.`product_id` AND geo_o.`status` = 'active'";
            }
            $geoVendorColumn = ($vendorId !== null && $vendorId > 0) ? 'o.`vendor_id`' : 'geo_o.`vendor_id`';
            $joins[] = "INNER JOIN `{$locations}` vl ON vl.`tenant_id` = p.`tenant_id` AND vl.`vendor_id` = {$geoVendorColumn}";
            $distanceExpression = "(6371 * ACOS(LEAST(1, COS(RADIANS(%f)) * COS(RADIANS(vl.`latitude`)) * COS(RADIANS(vl.`longitude`) - RADIANS(%f)) + SIN(RADIANS(%f)) * SIN(RADIANS(vl.`latitude`)))))";
            $distanceSelect = ", MIN({$distanceExpression}) AS `distance_km`";
            \array_push($selectParams, $latitude, $longitude, $latitude);
            $distanceWhere = " HAVING `distance_km` <= %f OR MIN(vl.`service_radius_km`) >= `distance_km`";
        }

        $sql = "SELECT p.*{$distanceSelect} FROM `{$table}` p " . \implode(' ', $joins) . ' WHERE ' . \implode(' AND ', $where) . ' GROUP BY p.`product_id`';
        if ($distanceWhere !== '') {
            $sql .= $distanceWhere;
            $havingParams[] = $radiusKm;
        }
        $sql .= ' ORDER BY p.`created_at` DESC';

        $params = \array_merge($selectParams, $joinParams, $whereParams, $havingParams);
        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
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

        $row['category_ids'] = $this->categoryIds($productId);
        $row['offerings'] = $this->offerings($productId);

        return $row;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function createCategory(array $data): array
    {
        global $wpdb;

        $name = $this->cleanText((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $tenantId = $this->tenantResolver->currentTenantId();
        $slug = $this->slug((string) ($data['slug'] ?? $name));
        $table = $wpdb->prefix . 'mercato_categories';
        $inserted = $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'parent_id' => isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            'name' => $name,
            'slug' => $slug,
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        if ($inserted === false) {
            throw new RuntimeException('Unable to create category: ' . (string) $wpdb->last_error);
        }

        return $this->category((int) $wpdb->insert_id);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function categories(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_categories';
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `{$table}` WHERE `tenant_id` = %d ORDER BY `sort_order` ASC, `name` ASC", $this->tenantResolver->currentTenantId()),
            ARRAY_A
        ) ?: [];
    }

    /**
     * @param list<int> $categoryIds
     * @return list<int>
     */
    public function assignCategories(int $productId, array $categoryIds): array
    {
        global $wpdb;

        $this->find($productId);
        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_product_categories';
        $wpdb->delete($table, ['tenant_id' => $tenantId, 'product_id' => $productId]);

        foreach ($categoryIds as $categoryId) {
            $wpdb->insert($table, [
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'category_id' => $categoryId,
            ]);
        }

        return $this->categoryIds($productId);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function upsertOffering(int $productId, int $vendorId, array $data): array
    {
        global $wpdb;

        $this->find($productId);
        $this->assertVendorCanPublish($vendorId, (string) ($data['status'] ?? 'active') === 'active' ? 'active' : 'draft');

        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_vendor_service_offerings';
        $row = [
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'product_id' => $productId,
            'status' => (string) ($data['status'] ?? 'active'),
            'price_minor' => isset($data['price_minor']) ? (int) $data['price_minor'] : null,
            'currency' => isset($data['currency']) ? $this->cleanText((string) $data['currency']) : 'USD',
            'duration_minutes' => isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
            'lead_time_minutes' => isset($data['lead_time_minutes']) ? (int) $data['lead_time_minutes'] : null,
            'capacity' => isset($data['capacity']) ? (int) $data['capacity'] : null,
        ];

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT `offering_id` FROM `{$table}` WHERE `tenant_id` = %d AND `vendor_id` = %d AND `product_id` = %d",
            $tenantId,
            $vendorId,
            $productId
        ));

        if ($existing) {
            $updated = $wpdb->update($table, $row, ['tenant_id' => $tenantId, 'offering_id' => (int) $existing]);
            if ($updated === false) {
                throw new RuntimeException('Unable to update offering: ' . (string) $wpdb->last_error);
            }
            return $this->offering((int) $existing);
        }

        $inserted = $wpdb->insert($table, $row);
        if ($inserted === false) {
            throw new RuntimeException('Unable to create offering: ' . (string) $wpdb->last_error);
        }

        return $this->offering((int) $wpdb->insert_id);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function offerings(int $productId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_vendor_service_offerings';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `product_id` = %d ORDER BY `created_at` DESC",
            $this->tenantResolver->currentTenantId(),
            $productId
        ), ARRAY_A) ?: [];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function addVendorLocation(int $vendorId, array $data): array
    {
        global $wpdb;

        $latitude = (float) ($data['latitude'] ?? 999);
        $longitude = (float) ($data['longitude'] ?? 999);
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new RuntimeException('Valid latitude and longitude are required.');
        }

        $tenantId = $this->tenantResolver->currentTenantId();
        $table = $wpdb->prefix . 'mercato_vendor_locations';
        $inserted = $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'label' => isset($data['label']) ? $this->cleanText((string) $data['label']) : null,
            'address_line1' => isset($data['address_line1']) ? $this->cleanText((string) $data['address_line1']) : null,
            'city' => isset($data['city']) ? $this->cleanText((string) $data['city']) : null,
            'region' => isset($data['region']) ? $this->cleanText((string) $data['region']) : null,
            'postal_code' => isset($data['postal_code']) ? $this->cleanText((string) $data['postal_code']) : null,
            'country' => isset($data['country']) ? $this->cleanText((string) $data['country']) : null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'service_radius_km' => isset($data['service_radius_km']) ? (float) $data['service_radius_km'] : null,
            'is_primary' => !empty($data['is_primary']) ? 1 : 0,
        ]);

        if ($inserted === false) {
            throw new RuntimeException('Unable to create vendor location: ' . (string) $wpdb->last_error);
        }

        return $this->vendorLocation((int) $wpdb->insert_id);
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

    /**
     * @return list<int>
     */
    private function normalizeIds(mixed $ids): array
    {
        if (!\is_array($ids)) {
            return [];
        }

        return \array_values(\array_unique(\array_filter(\array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    private function slug(string $value): string
    {
        if (\function_exists('sanitize_title')) {
            return \sanitize_title($value);
        }

        $slug = \strtolower(\preg_replace('/[^a-zA-Z0-9]+/', '-', \trim($value)) ?? '');
        return \trim($slug, '-');
    }

    /**
     * @return array<string,mixed>
     */
    private function category(int $categoryId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_categories';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `category_id` = %d",
            $this->tenantResolver->currentTenantId(),
            $categoryId
        ), ARRAY_A);

        if (!$row) {
            throw new RuntimeException('Category not found.');
        }

        return $row;
    }

    /**
     * @return list<int>
     */
    private function categoryIds(int $productId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_product_categories';
        return \array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT `category_id` FROM `{$table}` WHERE `tenant_id` = %d AND `product_id` = %d ORDER BY `category_id` ASC",
            $this->tenantResolver->currentTenantId(),
            $productId
        )) ?: []);
    }

    /**
     * @return array<string,mixed>
     */
    private function offering(int $offeringId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_vendor_service_offerings';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `offering_id` = %d",
            $this->tenantResolver->currentTenantId(),
            $offeringId
        ), ARRAY_A);

        if (!$row) {
            throw new RuntimeException('Offering not found.');
        }

        return $row;
    }

    /**
     * @return array<string,mixed>
     */
    private function vendorLocation(int $locationId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mercato_vendor_locations';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE `tenant_id` = %d AND `location_id` = %d",
            $this->tenantResolver->currentTenantId(),
            $locationId
        ), ARRAY_A);

        if (!$row) {
            throw new RuntimeException('Vendor location not found.');
        }

        return $row;
    }
}
