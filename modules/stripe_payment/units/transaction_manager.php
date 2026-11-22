<?php

/**
 * Stripe Transaction Manager
 *
 * Keeps track of checkout sessions created for shop transactions along
 * with payment intents and subscriptions that resulted from them.
 */

final class Stripe_TransactionStatus {
	const CREATED = 0;     // checkout session created, buyer redirected
	const PAID = 1;        // regular payment completed
	const AUTHORIZED = 2;  // delayed payment authorized, waiting to be captured
	const CAPTURED = 3;    // delayed payment captured
	const SUBSCRIBED = 4;  // subscription created
	const CANCELED = 5;    // buyer canceled or subscription ended
	const FAILED = 6;      // payment failed or amount mismatch
	const EXPIRED = 7;     // checkout session expired without payment
}


class Stripe_TransactionManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('stripe_transactions');

		$this->add_property('id', 'int');
		$this->add_property('transaction', 'varchar');
		$this->add_property('session_id', 'varchar');
		$this->add_property('payment_intent', 'varchar');
		$this->add_property('subscription', 'varchar');
		$this->add_property('customer', 'varchar');
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
