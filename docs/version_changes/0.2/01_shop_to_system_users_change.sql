-- This file modifies database to adapt it to changes introduced with
-- switch in shop from local to system wide users.

ALTER TABLE `system_access` ADD COLUMN `first_name` varchar(50) AFTER `fullname`;
ALTER TABLE `system_access` ADD COLUMN `last_name` varchar(50) AFTER `first_name`;
ALTER TABLE `shop_buyers` ADD COLUMN `system_user` int AFTER `guest`;
ALTER TABLE `shop_buyers` DROP COLUMN `validated`;
ALTER TABLE `shop_buyers` DROP COLUMN `password`;
ALTER TABLE `shop_transactions` DROP COLUMN `system_user`;
