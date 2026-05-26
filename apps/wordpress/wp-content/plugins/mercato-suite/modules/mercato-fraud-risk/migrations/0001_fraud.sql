CREATE TABLE IF NOT EXISTS `{prefix}mercato_risk_rules` (
  `rule_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `rule_key` VARCHAR(96) NOT NULL,
  `threshold_value` DECIMAL(20,4) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`rule_id`),
  UNIQUE KEY `uk_rule` (`tenant_id`, `rule_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_risk_scores` (
  `score_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `entity_type` VARCHAR(64) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `score` DECIMAL(8,4) NOT NULL,
  `decision` ENUM('allow','review','block') NOT NULL DEFAULT 'allow',
  `signals` JSON DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`score_id`),
  KEY `idx_entity` (`tenant_id`, `entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
