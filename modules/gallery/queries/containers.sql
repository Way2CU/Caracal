-- Containers for gallery groups

CREATE TABLE IF NOT EXISTS `gallery_containers` (
	`id` int NOT NULL AUTO_INCREMENT,
	`text_id` varchar COLLATE utf8_bin NULL,
	`name` ml_varchar(50) NOT NULL,
	`description` ml_text NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
