-- Item properties

CREATE TABLE `shop_item_properties` (
	`id` int NOT NULL AUTO_INCREMENT,
	`item` int NOT NULL,
	`text_id` varchar(32) NOT NULL,
	`type` varchar(32) NOT NULL,
	`name` ml_varchar(255) NOT NULL DEFAULT '',
	`value` text NOT NULL,
	PRIMARY KEY (`id`),
	KEY `item` (`item`),
	KEY `text_id` (`text_id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
