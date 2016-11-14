-- Generic index for individual form submission. IP address and timestamp
-- are kept for security reasons and flood prevention.

CREATE TABLE `contact_form_submissions` (
	`id` int NOT NULL AUTO_INCREMENT,
	`form` int NOT NULL,
	`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`address` varchar(45) NOT NULL,
	PRIMARY KEY(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
