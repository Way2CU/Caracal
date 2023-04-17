ALTER TABLE `shop_items` ADD COLUMN `videos` int NOT NULL AFTER `gallery`;
ALTER TABLE `shop_transactions` ADD COLUMN `issue_receipt` boolean NOT NULL DEFAULT false AFTER `total`;
ALTER TABLE `shop_transactions` ADD COLUMN `receipt_name` varchar(128) NULL AFTER `issue_receipt`;
ALTER TABLE `shop_transactions` ADD COLUMN `receipt_vat_number` varchar(32) NULL AFTER `receipt_name`;
