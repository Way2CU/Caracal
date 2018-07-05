CREATE TABLE `download_categories` (
	`id` int NOT NULL AUTO_INCREMENT,
	`text_id` varchar(32) NOT NULL,
	`name` ml_varchar(100) NOT NULL,
	`description` ml_text NOT NULL,
	`parent` int NOT NULL,
	PRIMARY KEY (`id`),
	INDEX (`text_id`),
	INDEX (`parent`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
