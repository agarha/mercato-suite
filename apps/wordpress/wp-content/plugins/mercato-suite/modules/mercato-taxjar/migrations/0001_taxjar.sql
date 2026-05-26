CREATE TABLE IF NOT EXISTS `{prefix}mercato_taxjar_events` (
  `event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `provider_event_id` VARCHAR(128) DEFAULT NULL,
  `event_type` VARCHAR(128) NOT NULL,
  `payload` JSON NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`event_id`),
  KEY `idx_type` (`tenant_id`, `event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
