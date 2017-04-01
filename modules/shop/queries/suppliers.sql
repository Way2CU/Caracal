-- Item suppliers

CREATE TABLE `shop_suppliers` (
	`id` int NOT NULL AUTO_INCREMENT,
	`name` varchar(100) NOT NULL DEFAULT '',
	`phone` varchar(100) NOT NULL,
	`email` varchar(100) NOT NULL,
	`url` varchar(255) NOT NULL,
	PRIMARY KEY (`id`),
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
