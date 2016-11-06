-- List of external domains allows to submit to a specific contact form.

CREATE TABLE `contact_form_domains` (
	`form` int NOT NULL,
	`domain` varchar(255) NOT NULL,
	INDEX `contact_forms_domains_by_form` (`form`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
