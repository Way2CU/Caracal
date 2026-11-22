<?php

/**
 * Grow Transaction Manager
 *
 * Keeps track of payment processes created with Grow and transactions
 * that resulted from them. Process identifiers are used to verify server
 * notifications while transaction identifiers are needed for settling
 * delayed payments.
 */

final class Grow_TransactionStatus {
	const CREATED = 0;     // payment process created, buyer redirected
	const PAID = 1;        // regular payment confirmed
	const AUTHORIZED = 2;  // delayed payment authorized, waiting to be settled
	const SETTLED = 3;     // delayed payment settled
	const CANCELED = 4;    // buyer canceled payment
	const FAILED = 5;      // notification was rejected or charging failed
}


class Grow_TransactionManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('grow_transactions');

		$this->add_property('id', 'int');
		$this->add_property('transaction', 'varchar');
		$this->add_property('process_id', 'varchar');
		$this->add_property('process_token', 'varchar');
		$this->add_property('transaction_id', 'varchar');
		$this->add_property('transaction_token', 'varchar');
		$this->add_property('asmachta', 'varchar');
		$this->add_property('card_suffix', 'varchar');
		$this->add_property('status', 'smallint');
		$this->add_property('timestamp', 'timestamp');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}

?>
