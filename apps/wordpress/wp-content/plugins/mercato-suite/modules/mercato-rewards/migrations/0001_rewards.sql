-- Independent rewards/credits subsystem. Two currencies per tenant:
--   sparks  - pro currency. Earned via activity + single-tier referrals,
--             spent on job bids / featured listings / area expansions.
--   credits - customer currency, denominated in minor units (cents).
--             Earned via referrals + reviews, spent at checkout as a
--             percentage discount.
--
-- Configuration lives in mercato_reward_config (per-tenant, single-row).
-- The default seed gives every new tenant a sensible economy.
--
-- Balances snapshot: read-fast, write-rarely. Lifetime totals are tracked
-- alongside current balance for analytics.
--
-- Ledger is append-only. Every earn/spend/refund/adjust writes a row.
-- Replaying the ledger from time-zero must reproduce the balance.

CREATE TABLE IF NOT EXISTS `{prefix}mercato_reward_config` (
  `tenant_id`                BIGINT UNSIGNED NOT NULL,
  `pro_currency_name`        VARCHAR(40)  NOT NULL DEFAULT 'Sparks',
  `customer_currency_name`   VARCHAR(40)  NOT NULL DEFAULT 'Credits',
  `signup_bonus_sparks`      INT          NOT NULL DEFAULT 10,
  `profile_complete_sparks`  INT          NOT NULL DEFAULT 5,
  `insurance_verified_sparks` INT         NOT NULL DEFAULT 5,
  `completed_job_sparks`     INT          NOT NULL DEFAULT 2,
  `five_star_sparks`         INT          NOT NULL DEFAULT 2,
  `referral_sparks`          INT          NOT NULL DEFAULT 10,
  `bid_cost_sparks`          INT          NOT NULL DEFAULT 1,
  `premium_bid_cost_sparks`  INT          NOT NULL DEFAULT 3,
  `premium_bid_threshold_minor` BIGINT    NOT NULL DEFAULT 50000,
  `featured_listing_sparks`  INT          NOT NULL DEFAULT 10,
  `extra_area_sparks_per_month` INT       NOT NULL DEFAULT 5,
  `customer_signup_credit_minor` INT      NOT NULL DEFAULT 1000,
  `customer_referral_credit_minor` INT    NOT NULL DEFAULT 1000,
  `customer_review_credit_minor` INT      NOT NULL DEFAULT 500,
  `sparks_per_usd`           INT          NOT NULL DEFAULT 2,
  `enabled`                  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`               DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at`               DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_user_balances` (
  `tenant_id`                    BIGINT UNSIGNED NOT NULL,
  `user_id`                      BIGINT UNSIGNED NOT NULL,
  `sparks`                       INT          NOT NULL DEFAULT 0,
  `credits_minor`                BIGINT       NOT NULL DEFAULT 0,
  `lifetime_sparks_earned`       INT          NOT NULL DEFAULT 0,
  `lifetime_credits_earned_minor` BIGINT      NOT NULL DEFAULT 0,
  `referrals_completed`          INT          NOT NULL DEFAULT 0,
  `updated_at`                   DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`tenant_id`, `user_id`),
  KEY `idx_sparks_desc` (`tenant_id`, `sparks` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_reward_ledger` (
  `entry_id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`        BIGINT UNSIGNED NOT NULL,
  `user_id`          BIGINT UNSIGNED NOT NULL,
  `currency`         ENUM('sparks','credits') NOT NULL,
  `kind`             ENUM('earned','spent','refunded','expired','adjusted') NOT NULL,
  `amount`           BIGINT NOT NULL,
  `balance_after`    BIGINT NOT NULL,
  `reason`           VARCHAR(80) NOT NULL,
  `reference_type`   VARCHAR(40) DEFAULT NULL,
  `reference_id`     BIGINT UNSIGNED DEFAULT NULL,
  `actor_user_id`    BIGINT UNSIGNED DEFAULT NULL,
  `metadata`         JSON DEFAULT NULL,
  `created_at`       DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`entry_id`),
  KEY `idx_user_time` (`tenant_id`, `user_id`, `created_at`),
  KEY `idx_reason_time` (`tenant_id`, `reason`, `created_at`),
  KEY `idx_reference` (`tenant_id`, `reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Extend the existing referrals table with reward bookkeeping. Idempotent
-- via additive ALTERs guarded by the absence-of-column convention; if you
-- re-run, duplicate-column errors are harmless.
ALTER TABLE `{prefix}mercato_referrals`
  ADD COLUMN `kind` ENUM('pro_to_pro','pro_to_customer','customer_to_customer') NOT NULL DEFAULT 'pro_to_pro' AFTER `status`,
  ADD COLUMN `reward_sparks` INT NOT NULL DEFAULT 0,
  ADD COLUMN `reward_credits_minor` INT NOT NULL DEFAULT 0,
  ADD COLUMN `activated_at` DATETIME(3) DEFAULT NULL,
  ADD COLUMN `sale_reference_id` BIGINT UNSIGNED DEFAULT NULL;
