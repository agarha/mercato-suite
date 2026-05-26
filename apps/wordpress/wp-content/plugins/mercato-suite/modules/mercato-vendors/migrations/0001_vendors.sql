CREATE TABLE IF NOT EXISTS `{prefix}mercato_vendors` (
  `vendor_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `owner_user_id` BIGINT UNSIGNED NOT NULL,
  `stripe_account_id` VARCHAR(128) DEFAULT NULL,
  `status` ENUM('pending','kyc_required','approved','rejected','suspended','closed') NOT NULL DEFAULT 'pending',
  `business_name` VARCHAR(255) NOT NULL,
  `store_slug` VARCHAR(128) NOT NULL,
  `return_policy` TEXT DEFAULT NULL,
  `suspension_reason` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`vendor_id`),
  UNIQUE KEY `uk_tenant_store_slug` (`tenant_id`, `store_slug`),
  KEY `idx_tenant_status` (`tenant_id`, `status`),
  KEY `idx_owner` (`owner_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_vendor_staff` (
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role` ENUM('owner') NOT NULL DEFAULT 'owner',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`vendor_id`, `user_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
