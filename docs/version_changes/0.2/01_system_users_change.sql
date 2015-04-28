-- This file modifies database to adapt it to changes introduced with changes to
-- `system_access` table.

ALTER TABLE `system_access` ADD COLUMN `first_name` varchar(50) AFTER `fullname`;
ALTER TABLE `system_access` ADD COLUMN `last_name` varchar(50) AFTER `first_name`;
