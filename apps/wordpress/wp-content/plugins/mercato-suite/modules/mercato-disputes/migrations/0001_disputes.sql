CREATE TABLE IF NOT EXISTS `{prefix}mercato_disputes` (
  `dispute_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `suborder_id` BIGINT UNSIGNED DEFAULT NULL,
  `vendor_id` BIGINT UNSIGNED DEFAULT NULL,
  `opened_by_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `reason` VARCHAR(128) NOT NULL,
  `status` ENUM('open','needs_response','escalated','resolved','closed') NOT NULL DEFAULT 'open',
  `requested_refund_minor` BIGINT UNSIGNED DEFAULT NULL,
  `resolution` TEXT DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`dispute_id`),
  KEY `idx_status` (`tenant_id`, `status`),
  KEY `idx_vendor` (`tenant_id`, `vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_dispute_events` (
  `event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dispute_id` BIGINT UNSIGNED NOT NULL,
  `actor_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `event_type` VARCHAR(64) NOT NULL,
  `payload` JSON DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`event_id`),
  KEY `idx_dispute` (`dispute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
