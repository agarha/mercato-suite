CREATE TABLE IF NOT EXISTS `{prefix}mercato_booking_requests` (
  `booking_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `client_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `offering_id` BIGINT UNSIGNED DEFAULT NULL,
  `scheduled_at` DATETIME(3) DEFAULT NULL,
  `status` ENUM('requested','accepted','cancelled') NOT NULL DEFAULT 'requested',
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`booking_id`),
  KEY `idx_tenant_status` (`tenant_id`, `status`, `created_at`),
  KEY `idx_vendor` (`tenant_id`, `vendor_id`, `created_at`)
) {charset_collate};

CREATE TABLE IF NOT EXISTS `{prefix}mercato_jobs` (
  `job_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `booking_id` BIGINT UNSIGNED DEFAULT NULL,
  `lead_id` BIGINT UNSIGNED DEFAULT NULL,
  `estimate_id` BIGINT UNSIGNED DEFAULT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED DEFAULT NULL,
  `assigned_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `status` ENUM('scheduled','assigned','enroute','inprogress','completed','closed','cancelled') NOT NULL DEFAULT 'scheduled',
  `scheduled_at` DATETIME(3) DEFAULT NULL,
  `version` INT UNSIGNED NOT NULL DEFAULT 1,
  `locked_by` BIGINT UNSIGNED DEFAULT NULL,
  `locked_until` DATETIME(3) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`job_id`),
  KEY `idx_tenant_status` (`tenant_id`, `status`, `created_at`),
  KEY `idx_vendor` (`tenant_id`, `vendor_id`, `created_at`),
  KEY `idx_assigned` (`tenant_id`, `assigned_user_id`, `status`)
) {charset_collate};

CREATE TABLE IF NOT EXISTS `{prefix}mercato_job_status_history` (
  `history_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `job_id` BIGINT UNSIGNED NOT NULL,
  `from_status` VARCHAR(32) DEFAULT NULL,
  `to_status` VARCHAR(32) NOT NULL,
  `actor_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `reason` TEXT DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`history_id`),
  KEY `idx_job` (`tenant_id`, `job_id`, `created_at`)
) {charset_collate};

CREATE TABLE IF NOT EXISTS `{prefix}mercato_leads` (
  `lead_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `client_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `vendor_id` BIGINT UNSIGNED DEFAULT NULL,
  `stage` ENUM('new','qualified','estimate_sent','won','lost') NOT NULL DEFAULT 'new',
  `source` VARCHAR(80) DEFAULT NULL,
  `title` VARCHAR(160) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`lead_id`),
  KEY `idx_tenant_stage` (`tenant_id`, `stage`, `created_at`)
) {charset_collate};

CREATE TABLE IF NOT EXISTS `{prefix}mercato_estimates` (
  `estimate_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `lead_id` BIGINT UNSIGNED DEFAULT NULL,
  `client_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('draft','sent','accepted','declined','expired') NOT NULL DEFAULT 'draft',
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `total_minor` BIGINT NOT NULL DEFAULT 0,
  `sent_at` DATETIME(3) DEFAULT NULL,
  `accepted_at` DATETIME(3) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`estimate_id`),
  KEY `idx_tenant_status` (`tenant_id`, `status`, `created_at`),
  KEY `idx_vendor` (`tenant_id`, `vendor_id`, `created_at`)
) {charset_collate};

CREATE TABLE IF NOT EXISTS `{prefix}mercato_estimate_line_items` (
  `line_item_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `estimate_id` BIGINT UNSIGNED NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_amount_minor` BIGINT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`line_item_id`),
  KEY `idx_estimate` (`tenant_id`, `estimate_id`)
) {charset_collate};

CREATE TABLE IF NOT EXISTS `{prefix}mercato_referrals` (
  `referral_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `referrer_vendor_id` BIGINT UNSIGNED NOT NULL,
  `referred_email_hash` CHAR(64) NOT NULL,
  `status` ENUM('tracked','converted','redeemed') NOT NULL DEFAULT 'tracked',
  `points` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`referral_id`),
  UNIQUE KEY `uk_referral` (`tenant_id`, `referrer_vendor_id`, `referred_email_hash`),
  KEY `idx_vendor` (`tenant_id`, `referrer_vendor_id`, `created_at`)
) {charset_collate};
