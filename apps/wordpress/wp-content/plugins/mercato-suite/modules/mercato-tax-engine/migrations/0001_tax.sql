CREATE TABLE IF NOT EXISTS `{prefix}mercato_tax_rates` (
  `rate_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `country` CHAR(2) NOT NULL,
  `region` VARCHAR(64) DEFAULT NULL,
  `rate_bps` INT UNSIGNED NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`rate_id`),
  KEY `idx_lookup` (`tenant_id`, `country`, `region`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_tax_transactions` (
  `tax_transaction_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `suborder_id` BIGINT UNSIGNED DEFAULT NULL,
  `provider` VARCHAR(64) NOT NULL DEFAULT 'manual',
  `tax_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `payload` JSON DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`tax_transaction_id`),
  KEY `idx_suborder` (`tenant_id`, `suborder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
