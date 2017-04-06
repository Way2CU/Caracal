-- Available recurring payments

CREATE TABLE `shop_recurring_payments` (
	`id` int NOT NULL AUTO_INCREMENT,
	`plan` int NOT NULL,
	`amount` decimal(8,2) NOT NULL,
	`status` int NOT NULL,
	`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `index_by_plan` (`plan`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
