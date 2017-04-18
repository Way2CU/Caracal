-- Group membership in a container

CREATE TABLE IF NOT EXISTS `gallery_group_membership` (
	`id` int NOT NULL AUTO_INCREMENT,
	`group` int NOT NULL,
	`container` int NOT NULL,
	PRIMARY KEY (`id`),
	KEY `index_by_container` (`container`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
