-- Values for certain types of fields like comboboxes, lists, etc.

CREATE TABLE `contact_form_field_values` (
	`id` int NOT NULL AUTO_INCREMENT,
	`field` int NOT NULL,
	`name` ml_varchar(100) NOT NULL DEFAULT '',
	`value` varchar(255) NOT NULL,
	PRIMARY KEY(`id`),
	INDEX `contact_form_values_by_field` (`field`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
