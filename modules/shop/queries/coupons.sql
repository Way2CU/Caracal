-- Coupons

CREATE TABLE `shop_coupons` (
	`id` int NOT NULL AUTO_INCREMENT,
	`text_id` varchar(64) NOT NULL,
	`name` ml_varchar(255) NOT NULL DEFAULT '',
	`has_limit` boolean NOT NULL DEFAULT '0',
	`has_timeout` boolean NOT NULL DEFAULT '0',
	`limit` int NOT NULL DEFAULT '0',
	`timeout` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `index_by_text_id` (`text_id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
