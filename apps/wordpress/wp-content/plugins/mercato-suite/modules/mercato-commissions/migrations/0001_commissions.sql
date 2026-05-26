CREATE TABLE IF NOT EXISTS `{prefix}mercato_commission_rules` (
  `rule_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED DEFAULT NULL,
  `category_id` BIGINT UNSIGNED DEFAULT NULL,
  `rate_bps` INT UNSIGNED NOT NULL,
  `hold_days` INT UNSIGNED NOT NULL DEFAULT 7,
  `priority` INT UNSIGNED NOT NULL DEFAULT 100,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`rule_id`),
  KEY `idx_tenant_vendor` (`tenant_id`, `vendor_id`, `active`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_commissions` (
  `commission_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `suborder_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `gross_minor` BIGINT UNSIGNED NOT NULL,
  `commission_minor` BIGINT UNSIGNED NOT NULL,
  `vendor_net_minor` BIGINT UNSIGNED NOT NULL,
  `rate_bps` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','available','reversed') NOT NULL DEFAULT 'pending',
  `available_at` DATETIME(3) NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`commission_id`),
  UNIQUE KEY `uk_suborder` (`tenant_id`, `suborder_id`),
  KEY `idx_vendor_status` (`tenant_id`, `vendor_id`, `status`, `available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
