-- Gallery image groups

CREATE TABLE IF NOT EXISTS `gallery_groups` (
	`id` int NOT NULL AUTO_INCREMENT,
	`text_id` varchar(32) COLLATE utf8_bin NULL,
	`name` ml_varchar(50) NOT NULL,
	`description` ml_text NOT NULL,
	`thumbnail` int NULL,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
