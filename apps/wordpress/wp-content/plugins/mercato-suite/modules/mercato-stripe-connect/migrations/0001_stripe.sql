CREATE TABLE IF NOT EXISTS `{prefix}mercato_stripe_accounts` (
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `stripe_account_id` VARCHAR(128) NOT NULL,
  `onboarding_status` ENUM('pending','complete','restricted') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`tenant_id`, `vendor_id`),
  UNIQUE KEY `uk_stripe_account` (`stripe_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_stripe_webhook_events` (
  `event_id` VARCHAR(128) NOT NULL,
  `type` VARCHAR(128) NOT NULL,
  `payload` JSON NOT NULL,
  `processed_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
