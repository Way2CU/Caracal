ALTER TABLE `shop_items` ADD COLUMN `videos` int NOT NULL AFTER `gallery`;
ALTER TABLE `shop_transactions` ADD COLUMN `issue_receipt` boolean NOT NULL DEFAULT false AFTER `total`;
ALTER TABLE `shop_transactions` ADD COLUMN `receipt_name` varchar(128) NULL AFTER `issue_receipt`;
ALTER TABLE `shop_transactions` ADD COLUMN `receipt_vat_number` varchar(32) NULL AFTER `receipt_name`;

-- Add indexes for columns used as lookup keys. Transactions are resolved by `uid` on
-- every checkout step and on every payment gateway callback, and buyers are resolved
-- by `uid` and `email`. All of these scanned tables which only ever grow.
--
-- Plain keys rather than unique constraints so the migration cannot fail on existing
-- data. Both `uid` columns are generated to be unique and can be tightened once
-- verified:
--
--     SELECT `uid`, COUNT(*) AS total FROM `shop_transactions`
--         GROUP BY `uid` HAVING total > 1;
--     SELECT `uid`, COUNT(*) AS total FROM `shop_buyers`
--         GROUP BY `uid` HAVING total > 1;

ALTER TABLE `shop_transactions` ADD KEY `index_by_uid` (`uid`);
ALTER TABLE `shop_buyers` ADD KEY `index_by_uid` (`uid`);
ALTER TABLE `shop_buyers` ADD KEY `index_by_email` (`email`);
