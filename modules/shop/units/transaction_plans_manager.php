<?php

class ShopTransactionPlansManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_transaction_plans');

		$this->addProperty('id', 'int');
		$this->addProperty('transaction', 'int');
		$this->addProperty('plan_name', 'varchar');
		$this->addProperty('trial', 'int');
		$this->addProperty('trial_count', 'int');
		$this->addProperty('interval', 'int');
		$this->addProperty('interval_count', 'int');
		$this->addProperty('start_time', 'timestamp');
		$this->addProperty('end_time', 'timestamp');
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
