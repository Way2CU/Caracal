<?php

/**
 * Stripe Customer Manager
 *
 * Maps shop buyers to Stripe customers so repeated subscriptions
 * reuse the same customer record.
 */

class Stripe_CustomerManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('stripe_customers');

		$this->add_property('id', 'int');
		$this->add_property('buyer', 'int');
		$this->add_property('text_id', 'varchar');
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
