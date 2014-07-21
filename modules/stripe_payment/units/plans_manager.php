<?php

class Stripe_PlansManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('stripe_recurring_plans');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('name', 'varchar');

		$this->addProperty('trial_days', 'int');
		$this->addProperty('interval', 'int');
		$this->addProperty('interval_count', 'int');

		$this->addProperty('price', 'decimal');
		$this->addProperty('currency', 'varchar');
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
