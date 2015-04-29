-- This file modifies database to adapt it to changes introduced with
-- switch in shop from local to system wide users.

ALTER TABLE `shop_buyers` ADD COLUMN `system_user` int AFTER `guest`;
ALTER TABLE `shop_buyers` ADD COLUMN `agreed` boolean DEFAULT '0' AFTER `system_user`;
ALTER TABLE `shop_buyers` DROP COLUMN `validated`;
ALTER TABLE `shop_buyers` DROP COLUMN `password`;
ALTER TABLE `shop_transactions` DROP COLUMN `system_user`;
