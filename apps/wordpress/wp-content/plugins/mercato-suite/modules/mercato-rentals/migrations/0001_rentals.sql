-- Phase 9b — Rental availability + bookings + deposits.
--
-- Three new tables, all tenant-scoped:
--
--   listing_bookings   - the rental contract: who, what, when, how much,
--                        deposit status, return condition. Lifecycle:
--                        held -> confirmed -> active -> returned (or
--                        cancelled / overdue at any point).
--
--   listing_blackouts  - owner-marked unavailable windows (maintenance,
--                        personal use, seasonal closure). Excluded from
--                        availability searches.
--
--   listing_holds      - short-lived soft-holds placed during checkout
--                        before a confirmed booking. Expires after N
--                        minutes if the renter abandons. Prevents two
--                        renters from grabbing the same item concurrently.
--
-- The double-booking guarantee is enforced application-side via overlap
-- queries against bookings+blackouts+holds with WHERE status IN (...).

CREATE TABLE IF NOT EXISTS `{prefix}mercato_listing_bookings` (
  `booking_id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`            BIGINT UNSIGNED NOT NULL,
  `product_id`           BIGINT UNSIGNED NOT NULL,
  `vendor_id`            BIGINT UNSIGNED NOT NULL,
  `renter_user_id`       BIGINT UNSIGNED NOT NULL,
  `starts_at`            DATETIME(3)     NOT NULL,
  `ends_at`              DATETIME(3)     NOT NULL,
  `status`               ENUM('held','confirmed','active','returned','cancelled','overdue','disputed') NOT NULL DEFAULT 'held',
  `pricing_type`         ENUM('per_hour','per_day','per_week','per_month','fixed') NOT NULL DEFAULT 'per_day',
  `units`                DECIMAL(10,2)   NOT NULL DEFAULT 1.00,
  `rate_minor`           BIGINT          NOT NULL,
  `total_minor`          BIGINT          NOT NULL,
  `currency`             CHAR(3)         NOT NULL DEFAULT 'CAD',
  `deposit_minor`        BIGINT          NOT NULL DEFAULT 0,
  `deposit_payment_method_id` VARCHAR(64) DEFAULT NULL,
  `deposit_status`       ENUM('none','authorized','released','claimed','partially_claimed') NOT NULL DEFAULT 'none',
  `deposit_claim_minor`  BIGINT          NOT NULL DEFAULT 0,
  `pickup_location_id`   BIGINT UNSIGNED DEFAULT NULL,
  `pickup_notes`         TEXT DEFAULT NULL,
  `return_notes`         TEXT DEFAULT NULL,
  `condition_in_url`     VARCHAR(512)    DEFAULT NULL,
  `condition_out_url`    VARCHAR(512)    DEFAULT NULL,
  `picked_up_at`         DATETIME(3)     DEFAULT NULL,
  `returned_at`          DATETIME(3)     DEFAULT NULL,
  `cancelled_at`         DATETIME(3)     DEFAULT NULL,
  `cancelled_reason`     VARCHAR(255)    DEFAULT NULL,
  `created_at`           DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at`           DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`booking_id`),
  KEY `idx_product_window` (`tenant_id`, `product_id`, `starts_at`, `ends_at`),
  KEY `idx_renter` (`tenant_id`, `renter_user_id`, `created_at`),
  KEY `idx_vendor` (`tenant_id`, `vendor_id`, `status`, `created_at`),
  KEY `idx_status_overdue_check` (`tenant_id`, `status`, `ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_listing_blackouts` (
  `blackout_id`  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    BIGINT UNSIGNED NOT NULL,
  `product_id`   BIGINT UNSIGNED NOT NULL,
  `vendor_id`    BIGINT UNSIGNED NOT NULL,
  `starts_at`    DATETIME(3)     NOT NULL,
  `ends_at`      DATETIME(3)     NOT NULL,
  `reason`       VARCHAR(255)    DEFAULT NULL,
  `created_at`   DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`blackout_id`),
  KEY `idx_product_window` (`tenant_id`, `product_id`, `starts_at`, `ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_listing_holds` (
  `hold_id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`      BIGINT UNSIGNED NOT NULL,
  `product_id`     BIGINT UNSIGNED NOT NULL,
  `renter_user_id` BIGINT UNSIGNED NOT NULL,
  `session_token`  CHAR(64)        NOT NULL,
  `starts_at`      DATETIME(3)     NOT NULL,
  `ends_at`        DATETIME(3)     NOT NULL,
  `expires_at`     DATETIME(3)     NOT NULL,
  `created_at`     DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`hold_id`),
  KEY `idx_product_window` (`tenant_id`, `product_id`, `starts_at`, `ends_at`),
  KEY `idx_session` (`session_token`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
