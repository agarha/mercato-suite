CREATE TABLE IF NOT EXISTS `{prefix}mercato_ai_requests` (
  `request_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `feature_key` VARCHAR(96) NOT NULL,
  `provider` VARCHAR(64) DEFAULT NULL,
  `status` ENUM('queued','completed','failed') NOT NULL DEFAULT 'queued',
  `prompt_hash` CHAR(64) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`request_id`),
  KEY `idx_feature` (`tenant_id`, `feature_key`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_ai_usage` (
  `usage_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `request_id` BIGINT UNSIGNED DEFAULT NULL,
  `input_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
  `output_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
  `cost_micro_usd` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`usage_id`),
  KEY `idx_request` (`tenant_id`, `request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
