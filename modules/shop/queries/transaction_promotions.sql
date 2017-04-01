-- Promotions applied to transaction

CREATE TABLE `shop_transaction_promotions` (
	`id` int NOT NULL AUTO_INCREMENT,
	`transaction` int NOT NULL,
	`promotion` varchar(64) NOT NULL,
	`discount` varchar(64) NOT NULL,
	PRIMARY KEY (`id`),
	KEY `transaction` (`transaction`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
