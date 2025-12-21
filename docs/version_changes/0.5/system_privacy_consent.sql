CREATE TABLE IF NOT EXISTS `system_privacy_consent` (
	`uid` char(36) NOT NULL UNIQUE,
	`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`categories` varchar(255) NOT NULL DEFAULT '',
	`gpc` boolean NOT NULL DEFAULT FALSE,
	`gpp` varchar(255) NULL,
	`desktop_version` boolean NOT NULL,
	PRIMARY KEY (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
