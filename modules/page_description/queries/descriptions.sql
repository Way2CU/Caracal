-- Page titles and descriptions

CREATE TABLE `page_descriptions` (
	`id` int NOT NULL AUTO_INCREMENT,
	`url` varchar(200) NOT NULL,
	`title` ml_varchar(140) NOT NULL DEFAULT '',
	`content` ml_varchar(160) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `index_by_url` (`url`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
