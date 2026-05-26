ALTER TABLE `{prefix}mercato_suborders`
  ADD COLUMN `discount_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `subtotal_minor`;

ALTER TABLE `{prefix}mercato_suborder_items`
  ADD COLUMN `line_subtotal_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `quantity`,
  ADD COLUMN `discount_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `line_subtotal_minor`,
  ADD COLUMN `tax_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `line_total_minor`;
