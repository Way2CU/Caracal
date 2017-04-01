-- Categories to which items can belong to

CREATE TABLE `shop_categories` (
	`id` int NOT NULL AUTO_INCREMENT,
	`text_id` varchar(32) NOT NULL,
	`parent` int NOT NULL DEFAULT '0',
	`image` int NULL,
	`title` ml_varchar(255) NOT NULL DEFAULT '',
	`description` ml_text NOT NULL ,
	`order` int NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	KEY `parent` (`parent`),
	KEY `text_id` (`text_id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
