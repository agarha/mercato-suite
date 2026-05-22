CREATE TABLE IF NOT EXISTS `{prefix}mercato_ledger_entries` (
  `ledger_entry_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `transaction_id` VARCHAR(64) NOT NULL,
  `source_type` VARCHAR(64) NOT NULL,
  `source_id` BIGINT UNSIGNED NOT NULL,
  `account` VARCHAR(64) NOT NULL,
  `vendor_id` BIGINT UNSIGNED DEFAULT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `debit_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `credit_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`ledger_entry_id`),
  UNIQUE KEY `uk_transaction_account` (`tenant_id`, `transaction_id`, `account`, `vendor_id`),
  KEY `idx_tenant_account` (`tenant_id`, `account`, `currency`),
  KEY `idx_source` (`tenant_id`, `source_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
