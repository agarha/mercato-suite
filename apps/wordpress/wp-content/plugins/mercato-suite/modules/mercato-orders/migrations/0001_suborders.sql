CREATE TABLE IF NOT EXISTS `{prefix}mercato_suborders` (
  `suborder_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `wc_order_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('created','acknowledged','shipped','delivered','completed','cancelled','refunded') NOT NULL DEFAULT 'created',
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `subtotal_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `shipping_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `tax_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `total_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `tracking_carrier` VARCHAR(64) DEFAULT NULL,
  `tracking_number` VARCHAR(128) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`suborder_id`),
  UNIQUE KEY `uk_order_vendor` (`tenant_id`, `wc_order_id`, `vendor_id`),
  KEY `idx_vendor_status` (`tenant_id`, `vendor_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_suborder_items` (
  `suborder_item_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `suborder_id` BIGINT UNSIGNED NOT NULL,
  `wc_order_item_id` BIGINT UNSIGNED NOT NULL,
  `wc_product_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `quantity` INT NOT NULL,
  `line_total_minor` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`suborder_item_id`),
  KEY `idx_suborder` (`suborder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_order_shipments` (
  `shipment_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `suborder_id` BIGINT UNSIGNED NOT NULL,
  `carrier` VARCHAR(64) NOT NULL,
  `tracking_number` VARCHAR(128) NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`shipment_id`),
  KEY `idx_suborder` (`suborder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
