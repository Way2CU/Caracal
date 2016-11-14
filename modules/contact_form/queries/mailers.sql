-- Table containing associations between mailers and individual
-- contact forms. All associated mailers will be used for sending.

CREATE TABLE `contact_form_mailers` (
	`form` int NOT NULL,
	`mailer` varchar(100) NOT NULL,
	INDEX `contact_form_mailers_by_form` (`form`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
