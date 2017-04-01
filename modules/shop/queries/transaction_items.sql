-- Transaction items

CREATE TABLE `shop_transaction_items` (
	`id` int NOT NULL AUTO_INCREMENT,
	`transaction` int NOT NULL,
	`item` int NOT NULL,
	`price` decimal(8,2) NOT NULL,
	`tax` decimal(8,2) NOT NULL,
	`amount` int NOT NULL,
	`description` varchar(500) NOT NULL,
	PRIMARY KEY (`id`),
	KEY `transaction` (`transaction`),
	KEY `item` (`item`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
