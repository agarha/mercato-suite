CREATE TABLE IF NOT EXISTS `{prefix}mercato_stripe_payment_intents` (
  `payment_intent_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `wc_order_id` BIGINT UNSIGNED NOT NULL,
  `stripe_payment_intent_id` VARCHAR(128) NOT NULL,
  `amount_minor` BIGINT UNSIGNED NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `status` ENUM('requires_payment_method','requires_confirmation','succeeded','canceled') NOT NULL DEFAULT 'requires_confirmation',
  `client_secret` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`payment_intent_id`),
  UNIQUE KEY `uk_stripe_payment_intent` (`stripe_payment_intent_id`),
  KEY `idx_order` (`tenant_id`, `wc_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_stripe_refunds` (
  `stripe_refund_row_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `wc_order_id` BIGINT UNSIGNED NOT NULL,
  `stripe_payment_intent_id` VARCHAR(128) NOT NULL,
  `stripe_refund_id` VARCHAR(128) NOT NULL,
  `amount_minor` BIGINT UNSIGNED NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `status` ENUM('pending','succeeded','failed') NOT NULL DEFAULT 'succeeded',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`stripe_refund_row_id`),
  UNIQUE KEY `uk_stripe_refund` (`stripe_refund_id`),
  KEY `idx_order` (`tenant_id`, `wc_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
