<?php

class ShopTransactionPlansManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_transaction_plans');

		$this->add_property('id', 'int');
		$this->add_property('transaction', 'int');
		$this->add_property('plan_name', 'varchar');
		$this->add_property('trial', 'int');
		$this->add_property('trial_count', 'int');
		$this->add_property('interval', 'int');
		$this->add_property('interval_count', 'int');
		$this->add_property('start_time', 'timestamp');
		$this->add_property('end_time', 'timestamp');
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
