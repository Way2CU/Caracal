-- Recurring prices synchronized from Stripe

CREATE TABLE `stripe_recurring_plans` (
	`id` int NOT NULL AUTO_INCREMENT,
	`text_id` varchar(255) NOT NULL,
	`name` varchar(255) NOT NULL,
	`trial_days` int NOT NULL DEFAULT '0',
	`interval` int NOT NULL DEFAULT '0',
	`interval_count` int NOT NULL DEFAULT '1',
	`price` decimal(8,2) NOT NULL,
	`currency` varchar(3) NOT NULL,
	PRIMARY KEY (`id`),
	KEY `index_by_text_id` (`text_id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
