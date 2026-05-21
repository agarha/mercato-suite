ALTER TABLE `{prefix}mercato_suborders`
  ADD COLUMN `payment_status` ENUM('unpaid','authorized','paid','refunded','partially_refunded') NOT NULL DEFAULT 'unpaid' AFTER `status`,
  ADD COLUMN `payment_intent_id` VARCHAR(128) DEFAULT NULL AFTER `payment_status`,
  ADD COLUMN `paid_at` DATETIME(3) DEFAULT NULL AFTER `payment_intent_id`,
  ADD COLUMN `refunded_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `total_minor`;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_refunds` (
  `refund_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `wc_order_id` BIGINT UNSIGNED NOT NULL,
  `suborder_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `stripe_refund_id` VARCHAR(128) DEFAULT NULL,
  `amount_minor` BIGINT UNSIGNED NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `reason` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','succeeded','failed') NOT NULL DEFAULT 'succeeded',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`refund_id`),
  KEY `idx_suborder` (`tenant_id`, `suborder_id`),
  KEY `idx_vendor` (`tenant_id`, `vendor_id`, `created_at`),
  KEY `idx_stripe_refund` (`stripe_refund_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
