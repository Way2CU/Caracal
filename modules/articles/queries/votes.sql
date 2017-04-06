-- User votes for individual articles.

CREATE TABLE `article_votes` (
	`id` int NOT NULL AUTO_INCREMENT ,
	`address` varchar(64) NOT NULL ,
	`article` int NOT NULL ,
	PRIMARY KEY (`id`),
	INDEX (`address`, `article`)
) ENGINE = MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
