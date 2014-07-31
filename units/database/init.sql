CREATE TABLE `system_access` (
	`id` int NOT NULL AUTO_INCREMENT,
	`username` varchar(50) COLLATE utf8_bin NOT NULL,
	`password` varchar(64) COLLATE utf8_bin NOT NULL,
	`fullname` varchar(100) COLLATE utf8_bin DEFAULT NULL,
	`email` varchar(200) COLLATE utf8_bin NOT NULL,
	`level` smallint NOT NULL DEFAULT '1',
	`verified` boolean NOT NULL DEFAULT FALSE,
	`agreed` boolean NOT NULL DEFAULT FALSE,
	`salt` char(64) COLLATE ascii_bin NOT NULL DEFAULT '',
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `system_access_verification` (
	`user` int NOT NULL,
	`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`code` varchar(64) NOT NULL,
	KEY `index_by_user` (`user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `system_user_data` (
	`user` int NOT NULL,
	`namespace` varchar(30) NOT NULL,
	`key` varchar(30) NOT NULL,
	`value` text NOT NULL,
	KEY `index_by_user` (`user`),
	KEY `index_by_user_and_namespace` (`user`, `namespace`),
	KEY `index_by_user_and_key` (`user`, `key`),
	KEY `index_by_all` (`user`, `namespace`, `key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `system_cache` (
	`uid` char(32) NOT NULL,
	`url` varchar(256) NOT NULL,
	`times_used` bigint NOT NULL DEFAULT '0',
	`times_renewed` int NOT NULL DEFAULT '0',
	`expires` timestamp NULL DEFAULT NULL,
	PRIMARY KEY (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `system_modules` (
	`id` int NOT NULL AUTO_INCREMENT,
	`order` int NOT NULL DEFAULT '0',
	`name` varchar(50) COLLATE utf8_bin NOT NULL,
	`preload` smallint NOT NULL DEFAULT '0',
	`active` smallint NOT NULL DEFAULT '1',
	PRIMARY KEY (`id`),
	KEY `index_by_preload` (`preload`),
	KEY `idnex_by_active` (`active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `system_retries` (
	`id` int NOT NULL AUTO_INCREMENT,
	`day` tinyint NOT NULL,
	`address` varchar(15) NOT NULL,
	`count` tinyint NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `system_settings` (
	`id` bigint NOT NULL AUTO_INCREMENT,
	`module` varchar(50) COLLATE utf8_bin NOT NULL,
	`variable` varchar(50) COLLATE utf8_bin NOT NULL,
	`value` text COLLATE utf8_bin,
	PRIMARY KEY (`id`),
	KEY `index_by_module` (`module`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
