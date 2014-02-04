CREATE TABLE `system_access` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`username` varchar(50) COLLATE utf8_bin NOT NULL,
	`password` varchar(64) COLLATE utf8_bin NOT NULL,
	`fullname` varchar(100) COLLATE utf8_bin DEFAULT NULL,
	`email` varchar(200) COLLATE utf8_bin NOT NULL,
	`level` smallint(6) NOT NULL DEFAULT '1',
	`verified` boolean NOT NULL DEFAULT FALSE,
	`agreed` boolean NOT NULL DEFAULT FALSE,
	`salt` char(64) COLLATE ascii_bin NOT NULL DEFAULT '',
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `system_access_verification` (
	`user` int(11) NOT NULL,
	`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`code` varchar(64) NOT NULL,
	KEY `index_by_user` (`user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `system_cache` (
	`uid` char(32) NOT NULL,
	`url` varchar(256) NOT NULL,
	`times_used` bigint(20) NOT NULL DEFAULT '0',
	`times_renewed` int(11) NOT NULL DEFAULT '0',
	`expires` timestamp NULL DEFAULT NULL,
	PRIMARY KEY (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `system_modules` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`order` int(11) NOT NULL DEFAULT '0',
	`name` varchar(50) COLLATE utf8_bin NOT NULL,
	`preload` smallint(6) NOT NULL DEFAULT '0',
	`active` smallint(6) NOT NULL DEFAULT '1',
	PRIMARY KEY (`id`),
	KEY `index_by_preload` (`preload`),
	KEY `idnex_by_active` (`active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `system_retries` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`day` tinyint(4) NOT NULL,
	`address` varchar(15) NOT NULL,
	`count` tinyint(4) NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `system_settings` (
	`id` bigint(20) NOT NULL AUTO_INCREMENT,
	`module` varchar(50) COLLATE utf8_bin NOT NULL,
	`variable` varchar(50) COLLATE utf8_bin NOT NULL,
	`value` text COLLATE utf8_bin,
	PRIMARY KEY (`id`),
	KEY `index_by_module` (`module`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
