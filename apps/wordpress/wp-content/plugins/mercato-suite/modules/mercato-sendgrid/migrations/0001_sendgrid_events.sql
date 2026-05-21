CREATE TABLE IF NOT EXISTS `{prefix}mercato_sendgrid_events` (
  `event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `delivery_id` BIGINT UNSIGNED DEFAULT NULL,
  `message_id` VARCHAR(255) DEFAULT NULL,
  `recipient` VARCHAR(255) NOT NULL,
  `event_type` ENUM('processed','delivered','open','click','bounce','dropped','spamreport') NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `payload` JSON DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`event_id`),
  KEY `idx_tenant_delivery` (`tenant_id`, `delivery_id`),
  KEY `idx_message` (`message_id`),
  KEY `idx_type` (`tenant_id`, `event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
