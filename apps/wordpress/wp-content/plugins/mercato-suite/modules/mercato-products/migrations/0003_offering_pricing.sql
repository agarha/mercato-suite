-- Pricing-type extension on vendor offerings. TaskRabbit / Workiz / Jiffy all
-- support a mix of hourly, fixed-per-job and per-unit (e.g. "$25 per window")
-- pricing on the same provider. Additive: existing rows default to 'fixed'
-- which matches today's price_minor semantics.
ALTER TABLE `{prefix}mercato_vendor_service_offerings`
  ADD COLUMN `pricing_type` ENUM('hourly','fixed','per_unit','quote_required') NOT NULL DEFAULT 'fixed' AFTER `currency`,
  ADD COLUMN `unit_label` VARCHAR(40) DEFAULT NULL AFTER `pricing_type`,
  ADD COLUMN `min_charge_minor` BIGINT UNSIGNED DEFAULT NULL AFTER `unit_label`,
  ADD COLUMN `summary` VARCHAR(255) DEFAULT NULL AFTER `min_charge_minor`;

-- Service-area geo extension: enable proximity-based discovery without
-- requiring providers to declare formal vendor_locations.
ALTER TABLE `{prefix}mercato_service_areas`
  ADD COLUMN `latitude` DECIMAL(10,7) DEFAULT NULL AFTER `country`,
  ADD COLUMN `longitude` DECIMAL(10,7) DEFAULT NULL AFTER `latitude`,
  ADD COLUMN `radius_km` DECIMAL(8,2) DEFAULT NULL AFTER `longitude`,
  ADD INDEX `idx_geo` (`tenant_id`, `latitude`, `longitude`);
