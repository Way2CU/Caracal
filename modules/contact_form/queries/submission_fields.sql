-- Values of submitted form fields.

CREATE TABLE `contact_form_submission_fields` (
	`id` int NOT NULL AUTO_INCREMENT,
	`submission` int NOT NULL,
	`field` int NULL,
	`value` text NOT NULL,
	PRIMARY KEY(`id`),
	INDEX `contact_form_submissions` (`submission`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
