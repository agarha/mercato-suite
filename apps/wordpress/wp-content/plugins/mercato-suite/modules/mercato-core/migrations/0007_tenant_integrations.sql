CREATE TABLE IF NOT EXISTS `{prefix}mercato_tenant_integrations` (
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `provider_key` VARCHAR(64) NOT NULL,
  `status` ENUM('disabled','test','live') NOT NULL DEFAULT 'disabled',
  `public_config` JSON NOT NULL,
  `secret_refs` JSON NOT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`tenant_id`, `provider_key`),
  KEY `idx_provider_status` (`provider_key`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
