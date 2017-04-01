-- Warehouses for stock management

CREATE TABLE `shop_warehouse` (
	`id` int NOT NULL AUTO_INCREMENT,
	`name` varchar(60) NOT NULL,
	`street` varchar(200) NOT NULL,
	`street2` varchar(200) NOT NULL,
	`city` varchar(40) NOT NULL,
	`zip` varchar(20) NOT NULL,
	`country` varchar(64) NOT NULL,
	`state` varchar(40) NOT NULL,
	PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
