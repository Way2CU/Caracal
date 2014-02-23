<?php

class PayPal_PlansManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('paypal_recurring_plans');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('name', 'ml_varchar');

		$this->addProperty('trial', 'int');
		$this->addProperty('trial_count', 'int');

		$this->addProperty('interval', 'int');
		$this->addProperty('interval_count', 'int');
		$this->addProperty('price', 'decimal');
		$this->addProperty('setup_price', 'decimal');

		$this->addProperty('start_time', 'timestamp');
		$this->addProperty('group_name', 'varchar');
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
