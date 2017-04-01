-- Coupon codes

CREATE TABLE `shop_coupon_codes` (
	`id` int NOT NULL AUTO_INCREMENT,
	`coupon` int NOT NULL,
	`code` varchar(64) NOT NULL,
	`times_used` int NOT NULL DEFAULT '0',
	`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`discount` varchar(64) NOT NULL,
	PRIMARY KEY (`id`),
	KEY `index_by_timestamp` (`timestamp`),
	KEY `index_by_code` (`code`),
	KEY `index_by_coupon` (`coupon`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
