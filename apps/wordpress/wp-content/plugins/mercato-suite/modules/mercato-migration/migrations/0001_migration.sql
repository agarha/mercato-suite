CREATE TABLE IF NOT EXISTS `{prefix}mercato_migration_runs` (
  `run_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `source_system` VARCHAR(64) NOT NULL,
  `status` ENUM('queued','running','completed','failed') NOT NULL DEFAULT 'queued',
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`run_id`),
  KEY `idx_status` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_migration_items` (
  `item_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id` BIGINT UNSIGNED NOT NULL,
  `source_type` VARCHAR(64) NOT NULL,
  `source_id` VARCHAR(128) NOT NULL,
  `target_type` VARCHAR(64) DEFAULT NULL,
  `target_id` BIGINT UNSIGNED DEFAULT NULL,
  `status` ENUM('pending','imported','skipped','failed') NOT NULL DEFAULT 'pending',
  `error_message` TEXT DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`item_id`),
  KEY `idx_run_status` (`run_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
