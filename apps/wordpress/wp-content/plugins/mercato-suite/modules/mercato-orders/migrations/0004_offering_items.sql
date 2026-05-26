ALTER TABLE `{prefix}mercato_suborder_items`
  ADD COLUMN `offering_id` BIGINT UNSIGNED DEFAULT NULL AFTER `wc_product_id`,
  ADD KEY `idx_offering` (`offering_id`);
