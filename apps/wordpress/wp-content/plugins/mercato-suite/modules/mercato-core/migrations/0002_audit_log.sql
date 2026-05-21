-- 0002_audit_log.sql
-- Append-only audit log. See docs_v2/06_database/Database.md §3.20 and docs_v2/09_security/Security.md §8.
-- Application role gets INSERT+SELECT only; no UPDATE/DELETE.

CREATE TABLE IF NOT EXISTS `{prefix}mercato_audit_log` (
  `audit_id`       BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tenant_id`      BIGINT UNSIGNED  NOT NULL,
  `occurred_at`    DATETIME(3)      NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `actor_id`       BIGINT UNSIGNED  DEFAULT NULL,
  `actor_role`     VARCHAR(32)      DEFAULT NULL,
  `actor_ip`       VARBINARY(16)    DEFAULT NULL,
  `action`         VARCHAR(64)      NOT NULL,
  `entity_type`    VARCHAR(48)      NOT NULL,
  `entity_id`      BIGINT UNSIGNED  NOT NULL,
  `before_state`   JSON             DEFAULT NULL,
  `after_state`    JSON             DEFAULT NULL,
  `correlation_id` CHAR(26)         DEFAULT NULL,
  PRIMARY KEY (`audit_id`, `occurred_at`),
  KEY `idx_entity`        (`entity_type`, `entity_id`, `occurred_at`),
  KEY `idx_tenant_action` (`tenant_id`, `action`, `occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
-- NOTE: partitioning omitted in MVP migration; added by maintenance job once monthly cadence required.
