-- Item manufacturers

CREATE TABLE `shop_manufacturers` (
	`id` int NOT NULL AUTO_INCREMENT,
	`name` ml_varchar(255) NOT NULL DEFAULT '',
	`web_site` varchar(255) NOT NULL,
	`logo` int NOT NULL,
	PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
