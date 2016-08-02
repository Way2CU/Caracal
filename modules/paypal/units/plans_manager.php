<?php

class PayPal_PlansManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('paypal_recurring_plans');

		$this->add_property('id', 'int');
		$this->add_property('text_id', 'varchar');
		$this->add_property('name', 'ml_varchar');

		$this->add_property('trial', 'int');
		$this->add_property('trial_count', 'int');

		$this->add_property('interval', 'int');
		$this->add_property('interval_count', 'int');
		$this->add_property('price', 'decimal');
		$this->add_property('setup_price', 'decimal');

		$this->add_property('start_time', 'timestamp');
		$this->add_property('group_name', 'varchar');
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
