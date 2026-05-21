-- 0004_tenants_and_rbac.sql
-- Tenants (§3.1), tenant settings (§3.2), feature flags (§3.3), RBAC (§3.25).

CREATE TABLE IF NOT EXISTS `{prefix}mercato_tenants` (
  `tenant_id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_slug`      VARCHAR(64)     NOT NULL,
  `display_name`     VARCHAR(255)    NOT NULL,
  `plan_code`        VARCHAR(32)     NOT NULL,
  `isolation_mode`   ENUM('pooled','silo','dedicated') NOT NULL DEFAULT 'pooled',
  `region_code`      VARCHAR(32)     NOT NULL DEFAULT 'us-east-1',
  `status`           ENUM('provisioning','active','suspended','closed') NOT NULL DEFAULT 'provisioning',
  `blog_id`          BIGINT UNSIGNED DEFAULT NULL,
  `control_plane_id` VARCHAR(64)     NOT NULL,
  `created_at`       DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at`       DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  `closed_at`        DATETIME(3)     DEFAULT NULL,
  PRIMARY KEY (`tenant_id`),
  UNIQUE KEY `uk_tenant_slug` (`tenant_slug`),
  UNIQUE KEY `uk_blog` (`blog_id`),
  KEY `idx_status` (`status`),
  KEY `idx_region` (`region_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_tenant_settings` (
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `version`    INT UNSIGNED    NOT NULL DEFAULT 1,
  `settings`   JSON            NOT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_tenant_feature_flags` (
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `feature_key` VARCHAR(64)     NOT NULL,
  `enabled`     TINYINT(1)      NOT NULL DEFAULT 0,
  `limit_value` BIGINT          DEFAULT NULL,
  `expires_at`  DATETIME(3)     DEFAULT NULL,
  PRIMARY KEY (`tenant_id`, `feature_key`),
  KEY `idx_feature` (`feature_key`, `enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_rbac_roles` (
  `role_id`      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `role_slug`    VARCHAR(64)     NOT NULL,
  `display_name` VARCHAR(128)    NOT NULL,
  `is_system`    TINYINT(1)      NOT NULL DEFAULT 1,
  `tenant_id`    BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uk_role_tenant` (`role_slug`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_rbac_capabilities` (
  `capability_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `capability_slug` VARCHAR(96)  NOT NULL,
  `description`     VARCHAR(255) NOT NULL,
  PRIMARY KEY (`capability_id`),
  UNIQUE KEY `uk_cap_slug` (`capability_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_rbac_role_caps` (
  `role_id`       INT UNSIGNED NOT NULL,
  `capability_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `capability_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_rbac_user_roles` (
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `role_id`    INT UNSIGNED    NOT NULL,
  `scope_type` VARCHAR(32)     NOT NULL DEFAULT '',
  `scope_id`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`tenant_id`, `user_id`, `role_id`, `scope_type`, `scope_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
