ALTER TABLE `{prefix}mercato_vendors`
  ADD COLUMN `status_reason` VARCHAR(255) DEFAULT NULL AFTER `status`;
