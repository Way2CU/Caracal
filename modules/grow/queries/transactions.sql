-- Grow payment processes and resulting transactions

CREATE TABLE `grow_transactions` (
	`id` int NOT NULL AUTO_INCREMENT,
	`transaction` varchar(30) NOT NULL,
	`process_id` varchar(32) NOT NULL,
	`process_token` varchar(64) NOT NULL,
	`transaction_id` varchar(32) NOT NULL DEFAULT '',
	`transaction_token` varchar(64) NOT NULL DEFAULT '',
	`asmachta` varchar(32) NOT NULL DEFAULT '',
	`card_suffix` varchar(4) NOT NULL DEFAULT '',
	`status` smallint(6) NOT NULL DEFAULT '0',
	`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `index_by_transaction` (`transaction`),
	KEY `index_by_process` (`process_id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
