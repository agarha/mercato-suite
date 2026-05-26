-- Provider profile extension. These columns power the public marketplace
-- (hero card, bio, vetting badges) and are populated during the
-- self-registration onboarding flow. All additive so existing rows
-- continue to work with NULLs.
ALTER TABLE `{prefix}mercato_vendors`
  ADD COLUMN `headline` VARCHAR(160) DEFAULT NULL AFTER `business_name`,
  ADD COLUMN `bio` TEXT DEFAULT NULL AFTER `headline`,
  ADD COLUMN `years_experience` SMALLINT UNSIGNED DEFAULT NULL AFTER `bio`,
  ADD COLUMN `hourly_rate_minor` BIGINT UNSIGNED DEFAULT NULL AFTER `years_experience`,
  ADD COLUMN `currency` CHAR(3) NOT NULL DEFAULT 'USD' AFTER `hourly_rate_minor`,
  ADD COLUMN `phone` VARCHAR(40) DEFAULT NULL AFTER `currency`,
  ADD COLUMN `contact_email` VARCHAR(190) DEFAULT NULL AFTER `phone`,
  ADD COLUMN `photo_url` VARCHAR(512) DEFAULT NULL AFTER `contact_email`,
  ADD COLUMN `cover_url` VARCHAR(512) DEFAULT NULL AFTER `photo_url`,
  ADD COLUMN `languages` VARCHAR(255) DEFAULT NULL AFTER `cover_url`,
  ADD COLUMN `license_number` VARCHAR(80) DEFAULT NULL AFTER `languages`,
  ADD COLUMN `license_state` VARCHAR(80) DEFAULT NULL AFTER `license_number`,
  ADD COLUMN `insurance_amount_minor` BIGINT UNSIGNED DEFAULT NULL AFTER `license_state`,
  ADD COLUMN `insurance_carrier` VARCHAR(160) DEFAULT NULL AFTER `insurance_amount_minor`,
  ADD COLUMN `background_check_status` ENUM('not_started','pending','passed','failed') NOT NULL DEFAULT 'not_started' AFTER `insurance_carrier`,
  ADD COLUMN `verified_at` DATETIME(3) DEFAULT NULL AFTER `background_check_status`;

-- Provider portfolio photos (work samples). Many per vendor; tenant-scoped.
CREATE TABLE IF NOT EXISTS `{prefix}mercato_vendor_portfolio` (
  `portfolio_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `caption` VARCHAR(255) DEFAULT NULL,
  `photo_url` VARCHAR(512) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`portfolio_id`),
  KEY `idx_vendor_sort` (`tenant_id`, `vendor_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
