-- Containers for multiple articles.

CREATE TABLE `article_groups` (
	`id` int NOT NULL AUTO_INCREMENT ,
	`text_id` varchar (32) NULL ,
	`title` ml_varchar( 255 ) NOT NULL DEFAULT '',
	`description` ml_text NOT NULL ,
	`visible` boolean NOT NULL DEFAULT '1',
	PRIMARY KEY ( `id` ),
	INDEX ( `text_id` )
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
