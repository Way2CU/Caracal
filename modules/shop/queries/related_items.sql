-- Related items

CREATE TABLE `shop_related_items` (
	`item` int NOT NULL,
	`related` int NOT NULL,
	KEY `item` (`item`, `related`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
