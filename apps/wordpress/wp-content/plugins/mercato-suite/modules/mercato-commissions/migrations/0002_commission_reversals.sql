CREATE TABLE IF NOT EXISTS `{prefix}mercato_commission_reversals` (
  `reversal_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `commission_id` BIGINT UNSIGNED NOT NULL,
  `refund_id` BIGINT UNSIGNED NOT NULL,
  `suborder_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `gross_reversal_minor` BIGINT UNSIGNED NOT NULL,
  `commission_reversal_minor` BIGINT UNSIGNED NOT NULL,
  `vendor_net_reversal_minor` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`reversal_id`),
  UNIQUE KEY `uk_refund_commission` (`tenant_id`, `refund_id`, `commission_id`),
  KEY `idx_commission` (`tenant_id`, `commission_id`),
  KEY `idx_vendor` (`tenant_id`, `vendor_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
