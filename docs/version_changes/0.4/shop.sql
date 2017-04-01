ALTER TABLE `shop_items` ADD COLUMN `tags` varchar(255) NULL AFTER `colors`;
ALTER TABLE `shop_items` ADD COLUMN `supplier` int NOT NULL AFTER `manufacturer`;
