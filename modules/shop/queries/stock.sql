-- Item stock

CREATE TABLE `shop_stock` (
	`id` int NOT NULL AUTO_INCREMENT,
	`item` int NOT NULL,
	`size` int DEFAULT NULL,
	`amount` int NOT NULL,
	PRIMARY KEY (`id`),
	KEY `item` (`item`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
