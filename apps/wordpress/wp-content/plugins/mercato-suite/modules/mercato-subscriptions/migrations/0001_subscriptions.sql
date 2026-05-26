CREATE TABLE IF NOT EXISTS `{prefix}mercato_subscriptions` (
  `subscription_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `subject_type` ENUM('tenant','vendor') NOT NULL DEFAULT 'tenant',
  `subject_id` BIGINT UNSIGNED NOT NULL,
  `plan_code` VARCHAR(64) NOT NULL,
  `status` ENUM('trialing','active','past_due','cancelled') NOT NULL DEFAULT 'trialing',
  `current_period_start` DATETIME(3) DEFAULT NULL,
  `current_period_end` DATETIME(3) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`subscription_id`),
  KEY `idx_subject` (`tenant_id`, `subject_type`, `subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_subscription_events` (
  `event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `subscription_id` BIGINT UNSIGNED DEFAULT NULL,
  `event_type` VARCHAR(128) NOT NULL,
  `payload` JSON DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`event_id`),
  KEY `idx_subscription` (`tenant_id`, `subscription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
