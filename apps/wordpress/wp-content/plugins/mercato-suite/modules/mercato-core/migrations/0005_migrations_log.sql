-- 0005_migrations_log.sql
-- Mercato migration registry. See docs_v2/06_database/Database.md §3.34.

CREATE TABLE IF NOT EXISTS `{prefix}mercato_migrations` (
  `migration_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plugin`       VARCHAR(64)     NOT NULL,
  `version_from` VARCHAR(32)     DEFAULT NULL,
  `version_to`   VARCHAR(32)     NOT NULL,
  `applied_at`   DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `checksum`     VARBINARY(32)   NOT NULL,
  PRIMARY KEY (`migration_id`),
  UNIQUE KEY `uk_plugin_version` (`plugin`, `version_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_deprecation_log` (
  `deprecation_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `symbol`         VARCHAR(255)    NOT NULL,
  `replacement`    VARCHAR(255)    DEFAULT NULL,
  `caller`         VARCHAR(512)    DEFAULT NULL,
  `since_version`  VARCHAR(32)     NOT NULL,
  `removed_in`     VARCHAR(32)     DEFAULT NULL,
  `triggered_at`   DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`deprecation_id`),
  KEY `idx_symbol_time` (`symbol`, `triggered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
