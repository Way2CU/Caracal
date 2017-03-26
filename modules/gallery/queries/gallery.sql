-- Image storage table

CREATE TABLE `gallery` (
	`id` int NOT NULL AUTO_INCREMENT ,
	`text_id` varchar(32) NOT NULL,
	`group` int DEFAULT NULL ,
	`title` ml_varchar(255) NOT NULL DEFAULT '',
	`description` ml_text NOT NULL ,
	`size` bigint NOT NULL ,
	`filename` varchar(40) NOT NULL ,
	`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ,
	`visible` boolean NOT NULL DEFAULT '1',
	`protected` boolean NOT NULL DEFAULT '0',
	`slideshow` boolean NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	KEY `index_by_text_id` (`text_id`),
	KEY `index_by_group` (`group`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
