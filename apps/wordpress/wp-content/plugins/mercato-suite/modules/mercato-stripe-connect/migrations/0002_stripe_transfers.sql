ALTER TABLE `{prefix}mercato_stripe_accounts`
  ADD COLUMN `charges_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `onboarding_status`,
  ADD COLUMN `payouts_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `charges_enabled`,
  ADD COLUMN `details_submitted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payouts_enabled`,
  ADD COLUMN `onboarding_url` VARCHAR(512) DEFAULT NULL AFTER `details_submitted`;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_stripe_transfers` (
  `transfer_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `batch_id` BIGINT UNSIGNED NOT NULL,
  `payout_item_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `stripe_account_id` VARCHAR(128) NOT NULL,
  `stripe_transfer_id` VARCHAR(128) DEFAULT NULL,
  `amount_minor` BIGINT UNSIGNED NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `status` ENUM('pending','succeeded','failed') NOT NULL DEFAULT 'pending',
  `last_error` TEXT DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`transfer_id`),
  UNIQUE KEY `uk_payout_item` (`tenant_id`, `payout_item_id`),
  KEY `idx_batch` (`tenant_id`, `batch_id`),
  KEY `idx_vendor` (`tenant_id`, `vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
