-- Payment tokens

CREATE TABLE `shop_payment_tokens` (
	`id` int NOT NULL AUTO_INCREMENT,
	`payment_method` varchar(64) NOT NULL,
	`buyer` int NOT NULL,
	`name` varchar(50) NOT NULL,
	`token` varchar(200) NOT NULL,
	`expires` boolean NOT NULL DEFAULT '0',
	`expiration_month` int NOT NULL,
	`expiration_year` int NOT NULL,
	PRIMARY KEY (`id`),
	KEY `index_by_name` (`payment_method`, `buyer`, `name`),
	KEY `index_by_buyer` (`payment_method`, `buyer`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
