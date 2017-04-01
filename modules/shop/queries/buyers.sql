-- Previous buyers

CREATE TABLE `shop_buyers` (
	`id` int NOT NULL AUTO_INCREMENT,
	`first_name` varchar(64) NOT NULL,
	`last_name` varchar(64) NOT NULL,
	`email` varchar(127) NOT NULL,
	`phone` varchar(200) NOT NULL,
	`guest` boolean NOT NULL DEFAULT '0',
	`system_user` int NULL,
	`agreed` boolean NOT NULL DEFAULT '0',
	`promotions` boolean NOT NULL DEFAULT '0',
	`uid` varchar(50) NOT NULL,
	PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
