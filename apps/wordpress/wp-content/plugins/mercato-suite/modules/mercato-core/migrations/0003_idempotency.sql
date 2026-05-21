-- 0003_idempotency.sql
-- See docs_v2/06_database/Database.md §3.35.

CREATE TABLE IF NOT EXISTS `{prefix}mercato_idempotency` (
  `tenant_id`        BIGINT UNSIGNED NOT NULL,
  `user_id`          BIGINT UNSIGNED NOT NULL,
  `endpoint`         VARCHAR(255)    NOT NULL,
  `idempotency_key`  VARCHAR(96)     NOT NULL,
  `response_body`    MEDIUMBLOB,
  `status_code`      SMALLINT        NOT NULL,
  `created_at`       DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `expires_at`       DATETIME(3)     NOT NULL,
  PRIMARY KEY (`tenant_id`, `user_id`, `endpoint`, `idempotency_key`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
