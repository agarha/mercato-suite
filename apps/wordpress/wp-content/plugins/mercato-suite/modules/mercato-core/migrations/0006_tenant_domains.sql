CREATE TABLE IF NOT EXISTS `{prefix}mercato_tenant_domains` (
  `domain_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `domain` VARCHAR(255) NOT NULL,
  `path_prefix` VARCHAR(128) DEFAULT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('pending','active','disabled') NOT NULL DEFAULT 'active',
  `verified_at` DATETIME(3) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`domain_id`),
  UNIQUE KEY `uk_domain_path` (`domain`, `path_prefix`),
  KEY `idx_tenant_status` (`tenant_id`, `status`, `is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
