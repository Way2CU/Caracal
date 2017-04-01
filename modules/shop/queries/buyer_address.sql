-- Shipping address for previous buyers

CREATE TABLE `shop_delivery_address` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`buyer` INT NOT NULL,
	`name` varchar(128) NOT NULL,
	`street` varchar(200) NOT NULL,
	`street2` varchar(200) NOT NULL,
	`email` varchar(127) NOT NULL,
	`phone` varchar(200) NOT NULL,
	`city` varchar(40) NOT NULL,
	`zip` varchar(20) NOT NULL,
	`state` varchar(40) NOT NULL,
	`country` varchar(64) NOT NULL,
	`access_code` varchar(100) NOT NULL,
	PRIMARY KEY (`id`),
	KEY `buyer` (`buyer`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
