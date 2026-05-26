-- Phase 9a — Rental support, additive only.
--
-- Two additions to the existing catalogue model:
--   1. listing_type column on products: distinguishes a service (Gigsii),
--      a rental (Agarha), a digital good, or a physical item. Default
--      'service' so every existing Gigsii row stays valid without backfill.
--   2. Extended pricing_type ENUM on vendor_service_offerings to include
--      per_hour / per_day / per_week / per_month — the standard rental
--      cadences. Original values (hourly, fixed, per_unit, quote_required)
--      remain so Gigsii continues to work unchanged.
--
-- Both changes are additive. No data migration required. Gigsii's existing
-- 39 services keep their pricing_type='fixed' / 'hourly' / etc.
ALTER TABLE `{prefix}mercato_products`
  ADD COLUMN `listing_type` ENUM('service','rental','digital','physical') NOT NULL DEFAULT 'service' AFTER `product_type`,
  ADD COLUMN `min_rental_window_minutes` INT UNSIGNED DEFAULT NULL AFTER `listing_type`,
  ADD COLUMN `max_rental_window_minutes` INT UNSIGNED DEFAULT NULL AFTER `min_rental_window_minutes`,
  ADD COLUMN `deposit_minor` BIGINT UNSIGNED DEFAULT NULL AFTER `max_rental_window_minutes`,
  ADD COLUMN `replacement_value_minor` BIGINT UNSIGNED DEFAULT NULL AFTER `deposit_minor`,
  ADD INDEX `idx_tenant_listing_type` (`tenant_id`, `listing_type`, `status`);

ALTER TABLE `{prefix}mercato_vendor_service_offerings`
  MODIFY COLUMN `pricing_type`
    ENUM('hourly','fixed','per_unit','quote_required','per_hour','per_day','per_week','per_month')
    NOT NULL DEFAULT 'fixed';
