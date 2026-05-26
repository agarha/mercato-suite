-- Mercato monthly partition maintenance template.
-- Run with the application database selected. Adjust table names if a custom WordPress prefix is used.

SET @next_month := DATE_FORMAT(DATE_ADD(CURRENT_DATE(), INTERVAL 1 MONTH), '%Y%m01');
SET @partition_name := DATE_FORMAT(DATE_ADD(CURRENT_DATE(), INTERVAL 1 MONTH), 'p%Y%m');
SET @less_than := TO_DAYS(DATE_ADD(STR_TO_DATE(@next_month, '%Y%m%d'), INTERVAL 1 MONTH));

-- High-volume tables that should be range-partitioned by created_at in production:
--   wp_mercato_event_outbox
--   wp_mercato_audit_log
--   wp_mercato_ledger_entries
--   wp_mercato_notification_deliveries
--
-- MySQL requires the partitioning expression to be compatible with all unique keys.
-- Execute online schema changes first where needed, then use ALTER TABLE ... REORGANIZE
-- for monthly partition creation.

SELECT
  @partition_name AS next_partition,
  @less_than AS less_than_to_days,
  'Use pt-online-schema-change or gh-ost for first-time partition conversion on production tables.' AS note;
