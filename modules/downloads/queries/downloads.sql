CREATE TABLE `downloads` (
	`id` int NOT NULL AUTO_INCREMENT,
	`text_id` varchar(32) NOT NULL,
	`category` int NULL,
	`name` ml_varchar(100) NOT NULL,
	`description` ml_text NOT NULL,
	`count` int NOT NULL DEFAULT  '0',
	`filename` varchar(100) NOT NULL ,
	`size` int NOT NULL ,
	`visible` boolean NOT NULL DEFAULT  '1',
	`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ,
	PRIMARY KEY (`id`),
	INDEX (`text_id`),
	INDEX (`category`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
