-- Contact form fields and its configuration.

CREATE TABLE `contact_form_fields` (
	`id` int NOT NULL AUTO_INCREMENT,
	`form` int NOT NULL,
	`name` varchar(32) NULL,
	`type` varchar(32) NOT NULL,
	`label` ml_varchar(100) NOT NULL DEFAULT '',
	`placeholder` ml_varchar(100) NOT NULL DEFAULT '',
	`min` int NOT NULL,
	`max` int NOT NULL,
	`maxlength` int NOT NULL,
	`value` varchar(255) NOT NULL,
	`pattern` varchar(255) NOT NULL,
	`disabled` boolean NOT NULL DEFAULT '0',
	`required` boolean NOT NULL DEFAULT '0',
	`checked` boolean NOT NULL DEFAULT '0',
	`autocomplete` boolean NOT NULL DEFAULT '0',
	PRIMARY KEY(`id`),
	INDEX `contact_form_fields_by_form` (`form`),
	INDEX `contact_form_fields_by_form_and_type` (`form`, `type`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
