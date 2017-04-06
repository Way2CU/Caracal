-- Table for storing generic contact form information. All the fields
-- and submissions made for individual contact form are stored in different
-- place.

CREATE TABLE `contact_forms` (
	`id` int NOT NULL AUTO_INCREMENT,
	`text_id` varchar(32) NULL,
	`name` ml_varchar(50) NOT NULL DEFAULT '',
	`action` varchar(255) NULL,
	`template` varchar(32) NOT NULL,
	`use_ajax` boolean NOT NULL DEFAULT '1',
	`show_submit` boolean NOT NULL DEFAULT '1',
	`show_reset` boolean NOT NULL DEFAULT '1',
	`show_cancel` boolean NOT NULL DEFAULT '0',
	`include_reply_to` boolean NOT NULL DEFAULT '0',
	`reply_to_field` int NULL,
	PRIMARY KEY(`id`),
	INDEX `contact_forms_by_text_id` (`text_id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
