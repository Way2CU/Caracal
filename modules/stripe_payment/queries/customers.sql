-- Stripe customers associated with shop buyers

CREATE TABLE `stripe_customers` (
	`id` int NOT NULL AUTO_INCREMENT,
	`buyer` int NOT NULL,
	`text_id` varchar(255) NOT NULL,
	PRIMARY KEY (`id`),
	KEY `index_by_buyer` (`buyer`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
