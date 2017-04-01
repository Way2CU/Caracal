-- Item membership in categories

CREATE TABLE `shop_item_membership` (
	`category` int NOT NULL,
	`item` int NOT NULL,
	KEY `category` (`category`),
	KEY `item` (`item`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
