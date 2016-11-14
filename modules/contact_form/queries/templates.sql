-- Templates for contact forms. These are used to properly format
-- messages before sending.

CREATE TABLE `contact_form_templates` (
	`id` int NOT NULL AUTO_INCREMENT ,
	`text_id` varchar(32) NULL ,
	`name` ml_varchar(50) NOT NULL DEFAULT '',
	`subject` ml_varchar(255) NOT NULL DEFAULT '',
	`plain` ml_text NOT NULL,
	`html` ml_text NOT NULL,
	PRIMARY KEY(`id`),
	INDEX `contact_form_templates_by_text_id` (`text_id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
