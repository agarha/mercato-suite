CREATE TABLE IF NOT EXISTS `{prefix}mercato_shippo_shipments` (
  `shipment_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `suborder_id` BIGINT UNSIGNED DEFAULT NULL,
  `provider_shipment_id` VARCHAR(128) DEFAULT NULL,
  `carrier` VARCHAR(64) DEFAULT NULL,
  `tracking_number` VARCHAR(128) DEFAULT NULL,
  `status` ENUM('created','label_purchased','in_transit','delivered','failed') NOT NULL DEFAULT 'created',
  `label_url` VARCHAR(512) DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`shipment_id`),
  KEY `idx_suborder` (`tenant_id`, `suborder_id`),
  KEY `idx_tracking` (`tracking_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{prefix}mercato_shippo_events` (
  `event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `shipment_id` BIGINT UNSIGNED DEFAULT NULL,
  `event_type` VARCHAR(128) NOT NULL,
  `payload` JSON NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`event_id`),
  KEY `idx_shipment` (`tenant_id`, `shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
