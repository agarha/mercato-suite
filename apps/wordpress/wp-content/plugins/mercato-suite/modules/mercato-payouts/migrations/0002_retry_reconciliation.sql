ALTER TABLE `{prefix}mercato_payout_items`
  ADD COLUMN `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `status`,
  ADD COLUMN `next_retry_at` DATETIME(3) DEFAULT NULL AFTER `attempt_count`,
  ADD COLUMN `manual_review_required` TINYINT(1) NOT NULL DEFAULT 0 AFTER `next_retry_at`,
  ADD COLUMN `last_error` TEXT DEFAULT NULL AFTER `manual_review_required`;

ALTER TABLE `{prefix}mercato_reconciliation_runs`
  ADD COLUMN `ledger_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `status`,
  ADD COLUMN `provider_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `ledger_minor`,
  ADD COLUMN `report_url` VARCHAR(512) DEFAULT NULL AFTER `drift_minor`;
