CREATE TABLE IF NOT EXISTS `{prefix}mercato_categories` (
  `category_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `parent_id` BIGINT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(160) NOT NULL,
  `slug` VARCHAR(160) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uk_tenant_slug` (`tenant_id`, `slug`),
  KEY `idx_parent_sort` (`tenant_id`, `parent_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_product_categories` (
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`tenant_id`, `product_id`, `category_id`),
  KEY `idx_category` (`tenant_id`, `category_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_vendor_service_offerings` (
  `offering_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'active',
  `price_minor` BIGINT UNSIGNED DEFAULT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `duration_minutes` INT UNSIGNED DEFAULT NULL,
  `lead_time_minutes` INT UNSIGNED DEFAULT NULL,
  `capacity` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`offering_id`),
  UNIQUE KEY `uk_vendor_product` (`tenant_id`, `vendor_id`, `product_id`),
  KEY `idx_product_status` (`tenant_id`, `product_id`, `status`),
  KEY `idx_vendor_status` (`tenant_id`, `vendor_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_vendor_locations` (
  `location_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `label` VARCHAR(160) DEFAULT NULL,
  `address_line1` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(120) DEFAULT NULL,
  `region` VARCHAR(120) DEFAULT NULL,
  `postal_code` VARCHAR(32) DEFAULT NULL,
  `country` CHAR(2) DEFAULT NULL,
  `latitude` DECIMAL(10,7) NOT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `service_radius_km` DECIMAL(8,2) DEFAULT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`location_id`),
  KEY `idx_vendor` (`tenant_id`, `vendor_id`, `is_primary`),
  KEY `idx_geo` (`tenant_id`, `latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_service_areas` (
  `area_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED DEFAULT NULL,
  `label` VARCHAR(160) NOT NULL,
  `city` VARCHAR(120) DEFAULT NULL,
  `region` VARCHAR(120) DEFAULT NULL,
  `postal_code_prefix` VARCHAR(32) DEFAULT NULL,
  `country` CHAR(2) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`area_id`),
  KEY `idx_vendor_product` (`tenant_id`, `vendor_id`, `product_id`),
  KEY `idx_region` (`tenant_id`, `country`, `region`, `city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
