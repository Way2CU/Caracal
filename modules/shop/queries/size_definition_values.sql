-- Values in specific size definition

CREATE TABLE `shop_item_size_values` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`definition` INT NOT NULL,
	`value` ml_varchar(50) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `definition` (`definition`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
