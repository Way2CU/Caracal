-- Item remarks

CREATE TABLE `shop_item_remarks` (
	`id` int NOT NULL AUTO_INCREMENT,
	`item` int NOT NULL,
	`remark` text NOT NULL,
	PRIMARY KEY (`id`),
	KEY `item` (`item`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
