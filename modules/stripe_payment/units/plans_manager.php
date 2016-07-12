<?php

class Stripe_PlansManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('stripe_recurring_plans');

		$this->add_property('id', 'int');
		$this->add_property('text_id', 'varchar');
		$this->add_property('name', 'varchar');

		$this->add_property('trial_days', 'int');
		$this->add_property('interval', 'int');
		$this->add_property('interval_count', 'int');

		$this->add_property('price', 'decimal');
		$this->add_property('currency', 'varchar');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}

?>
