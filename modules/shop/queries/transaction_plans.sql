-- Subscription plans for transaction

CREATE TABLE `shop_transaction_plans` (
	`id` int NOT NULL AUTO_INCREMENT,
	`transaction` int NOT NULL,
	`plan_name` varchar(64) NOT NULL,
	`trial` int NOT NULL,
	`trial_count` int NOT NULL,
	`interval` int NOT NULL,
	`interval_count` int NOT NULL,
	`start_time` timestamp NULL,
	`end_time` timestamp NULL,
	PRIMARY KEY (`id`),
	KEY `transaction` (`transaction`),
	KEY `plan_name` (`plan_name`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
