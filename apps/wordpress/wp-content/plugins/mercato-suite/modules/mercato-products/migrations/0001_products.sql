CREATE TABLE IF NOT EXISTS `{prefix}mercato_products` (
  `product_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `wc_product_id` BIGINT UNSIGNED DEFAULT NULL,
  `product_type` ENUM('simple') NOT NULL DEFAULT 'simple',
  `status` ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  `title` VARCHAR(255) NOT NULL,
  `description` LONGTEXT DEFAULT NULL,
  `sku` VARCHAR(128) DEFAULT NULL,
  `price_minor` BIGINT UNSIGNED NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  `archived_at` DATETIME(3) DEFAULT NULL,
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `uk_tenant_sku` (`tenant_id`, `sku`),
  KEY `idx_vendor_status` (`tenant_id`, `vendor_id`, `status`),
  KEY `idx_wc_product` (`wc_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_product_images` (
  `image_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `s3_key` VARCHAR(512) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`image_id`),
  KEY `idx_product_sort` (`product_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
