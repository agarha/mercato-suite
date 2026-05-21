-- 0001_event_outbox.sql
-- See docs_v2/06_database/Database.md §3.21 — transactional outbox.

CREATE TABLE IF NOT EXISTS `{prefix}mercato_event_outbox` (
  `event_id`        CHAR(26)         NOT NULL,
  `tenant_id`       BIGINT UNSIGNED  NOT NULL,
  `event_type`      VARCHAR(96)      NOT NULL,
  `payload`         JSON             NOT NULL,
  `envelope`        JSON             NOT NULL,
  `partition_key`   VARCHAR(96)      NOT NULL,
  `status`          ENUM('pending','publishing','published','dlq') NOT NULL DEFAULT 'pending',
  `attempts`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `last_error`      VARCHAR(255)     DEFAULT NULL,
  `created_at`      DATETIME(3)      NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `published_at`    DATETIME(3)      DEFAULT NULL,
  `next_attempt_at` DATETIME(3)      DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`event_id`),
  KEY `idx_status_next`     (`status`, `next_attempt_at`),
  KEY `idx_tenant_created`  (`tenant_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_event_consumed` (
  `consumer_slug` VARCHAR(96) NOT NULL,
  `event_id`      CHAR(26)    NOT NULL,
  `consumed_at`   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`consumer_slug`, `event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
