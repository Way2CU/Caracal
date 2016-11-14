-- Activity definition table.

CREATE TABLE `activities` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`activity` VARCHAR(32) NOT NULL,
	`function` VARCHAR(32) NOT NULL,
	`timeout` INT NOT NULL DEFAULT '900',
	`ignore_address` BOOLEAN NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	KEY `index_activity_and_function` (`activity`, `function`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
