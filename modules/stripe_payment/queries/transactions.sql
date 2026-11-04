-- Checkout sessions and resulting payments or subscriptions

CREATE TABLE `stripe_transactions` (
	`id` int NOT NULL AUTO_INCREMENT,
	`transaction` varchar(30) NOT NULL,
	`session_id` varchar(255) NOT NULL,
	`payment_intent` varchar(255) NOT NULL DEFAULT '',
	`subscription` varchar(255) NOT NULL DEFAULT '',
	`customer` varchar(255) NOT NULL DEFAULT '',
	`status` smallint(6) NOT NULL DEFAULT '0',
	`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `index_by_transaction` (`transaction`),
	KEY `index_by_session` (`session_id`),
	KEY `index_by_subscription` (`subscription`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
