-- Articles storage table.

CREATE TABLE `articles` (
	`id` int NOT NULL AUTO_INCREMENT ,
	`group` int(11) DEFAULT NULL ,
	`text_id` varchar (32) NULL ,
	`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ,
	`title` ml_varchar( 255 ) NOT NULL DEFAULT '',
	`content` ml_text NOT NULL ,
	`author` int NOT NULL ,
	`gallery` int NOT NULL ,
	`visible` boolean NOT NULL DEFAULT '0',
	`views` int NOT NULL DEFAULT '0',
	`votes_up` int NOT NULL DEFAULT '0',
	`votes_down` int NOT NULL DEFAULT '0',
	PRIMARY KEY ( `id` ),
	INDEX ( `author` ),
	INDEX ( `group` ),
	INDEX ( `text_id` )
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
