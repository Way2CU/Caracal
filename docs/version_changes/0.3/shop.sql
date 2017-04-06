ALTER TABLE `shop_items` ADD COLUMN `expires` timestamp NULL AFTER `timestamp`;
ALTER TABLE `shop_categories` ADD COLUMN `order` int NULL;
