-- Actvitify tracker log.

CREATE TABLE `activity_log` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`activity` INT NOT NULL,
	`user` INT NULL,
	`address` VARCHAR (15) NULL,
	`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `index_by_user` (`activity`, `user`),
	KEY `index_by_address` (`activity`, `user`, `address`),
	KEY `index_without_user` (`activity`, `address`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
