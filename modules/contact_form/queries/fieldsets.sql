-- Fieldset containers for contact form fields.

CREATE TABLE `contact_form_fieldsets` (
	`id` int NOT NULL AUTO_INCREMENT,
	`form` int NOT NULL,
	`name` varchar(50) NOT NULL,
	`legend` ml_varchar(250) NOT NULL DEFAULT '',
	PRIMARY KEY(`id`),
	INDEX `contact_form_fieldsets_by_form` (`form`),
	INDEX `contact_form_fieldsets_by_name` (`form`, `name`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
