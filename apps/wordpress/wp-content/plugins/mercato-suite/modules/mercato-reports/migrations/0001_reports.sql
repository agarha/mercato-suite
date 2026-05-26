CREATE TABLE IF NOT EXISTS `{prefix}mercato_metric_snapshots` (
  `snapshot_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `period_start` DATE NOT NULL,
  `period_end` DATE NOT NULL,
  `metric_key` VARCHAR(96) NOT NULL,
  `metric_value` DECIMAL(20,4) NOT NULL DEFAULT 0,
  `dimensions` JSON DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`snapshot_id`),
  UNIQUE KEY `uk_metric_period` (`tenant_id`, `period_start`, `period_end`, `metric_key`),
  KEY `idx_metric` (`tenant_id`, `metric_key`, `period_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_report_exports` (
  `export_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `report_type` VARCHAR(64) NOT NULL,
  `status` ENUM('ready','failed') NOT NULL DEFAULT 'ready',
  `file_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(128) NOT NULL DEFAULT 'text/csv',
  `row_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`export_id`),
  KEY `idx_tenant_type` (`tenant_id`, `report_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
