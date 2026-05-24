-- Hierarchical region taxonomy: country -> province -> city -> neighborhood.
-- Tenant-scoped because different marketplaces ship with different
-- jurisdictions. Used by:
--   - signup-page.php (cascading dropdowns instead of free-text)
--   - storefront discovery (?region_id=NN filter)
--   - service-area polygon shorthand (storing region_id alongside lat/lng)
--
-- Each row carries its own lat/lng + bounding radius so the geo filter
-- can short-circuit Haversine math against a region centroid before
-- falling back to per-vendor service_areas. The idx_parent_type pair
-- makes "give me all cities under Ontario" a single index seek.
CREATE TABLE IF NOT EXISTS `{prefix}mercato_geo_regions` (
  `region_id`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`     BIGINT UNSIGNED NOT NULL,
  `parent_id`     BIGINT UNSIGNED DEFAULT NULL,
  `type`          ENUM('country','province','state','city','neighborhood','postal_zone') NOT NULL,
  `code`          VARCHAR(16)     DEFAULT NULL,
  `name`          VARCHAR(160)    NOT NULL,
  `slug`          VARCHAR(160)    NOT NULL,
  `country_code`  CHAR(2)         DEFAULT NULL,
  `latitude`      DECIMAL(10,7)   DEFAULT NULL,
  `longitude`     DECIMAL(10,7)   DEFAULT NULL,
  `radius_km`     DECIMAL(8,2)    DEFAULT NULL,
  `population`    INT UNSIGNED    DEFAULT NULL,
  `sort_order`    INT             NOT NULL DEFAULT 0,
  `created_at`    DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at`    DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`region_id`),
  UNIQUE KEY `uk_tenant_slug` (`tenant_id`, `slug`),
  KEY `idx_parent_type` (`tenant_id`, `parent_id`, `type`, `sort_order`),
  KEY `idx_tenant_type_country` (`tenant_id`, `type`, `country_code`, `sort_order`),
  KEY `idx_geo` (`tenant_id`, `latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Optional structured FK from service areas to a region. Nullable so
-- providers can still declare ad-hoc lat/lng polygons; populated when the
-- signup form picks from the dropdown taxonomy.
ALTER TABLE `{prefix}mercato_service_areas`
  ADD COLUMN `region_id` BIGINT UNSIGNED DEFAULT NULL AFTER `country`,
  ADD INDEX `idx_region` (`tenant_id`, `region_id`);
