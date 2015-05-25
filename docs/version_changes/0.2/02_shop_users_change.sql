-- This file modifies database to adapt it to changes introduced with
-- switch in shop from local to system wide users.

ALTER TABLE `shop_buyers` ADD COLUMN `system_user` int AFTER `guest`;
ALTER TABLE `shop_buyers` ADD COLUMN `agreed` boolean DEFAULT '0' AFTER `system_user`;
ALTER TABLE `shop_buyers` DROP COLUMN `validated`;
ALTER TABLE `shop_buyers` DROP COLUMN `password`;
ALTER TABLE `shop_transactions` DROP COLUMN `system_user`;
ALTER TABLE `shop_transactions` ADD COLUMN `payment_token` int NOT NULL DEFAULT '0' AFTER `payment_method`;
ALTER TABLE `shop_transactions` CHANGE `token` `remote_id` varchar(255);
ALTER TABLE `shop_delivery_address` ADD COLUMN `access_code` varchar(100) AFTER `country`;
