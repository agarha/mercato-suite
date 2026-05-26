CREATE TABLE IF NOT EXISTS `{prefix}mercato_kyc_cases` (
  `case_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `provider` ENUM('stripe_identity') NOT NULL DEFAULT 'stripe_identity',
  `provider_reference` VARCHAR(128) DEFAULT NULL,
  `status` ENUM('required','processing','verified','rejected') NOT NULL DEFAULT 'required',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`case_id`),
  UNIQUE KEY `uk_vendor_provider` (`tenant_id`, `vendor_id`, `provider`),
  KEY `idx_status` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_kyc_documents` (
  `document_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `case_id` BIGINT UNSIGNED NOT NULL,
  `s3_key` VARCHAR(512) NOT NULL,
  `status` ENUM('uploaded','scanned','rejected') NOT NULL DEFAULT 'uploaded',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`document_id`),
  KEY `idx_case` (`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
