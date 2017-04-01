-- Transactions

CREATE TABLE `shop_transactions` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`buyer` INT NOT NULL,
	`address` INT NOT NULL,
	`uid` varchar(30) NOT NULL,
	`type` smallint(6) NOT NULL,
	`status` smallint(6) NOT NULL,
	`currency` INT NOT NULL,
	`handling` decimal(8,2) NOT NULL,
	`shipping` decimal(8,2) NOT NULL,
	`weight` decimal(4,2) NOT NULL,
	`payment_method` varchar(255) NOT NULL,
	`payment_token` int NOT NULL DEFAULT '0',
	`delivery_method` varchar(255) NOT NULL,
	`delivery_type` varchar(255) NOT NULL,
	`remark` text NOT NULL,
	`remote_id` varchar(255) NOT NULL,
	`total` decimal(8,2) NOT NULL,
	`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `buyer` (`buyer`),
	KEY `address` (`address`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;
