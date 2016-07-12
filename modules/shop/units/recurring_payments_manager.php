<?php

class ShopRecurringPaymentsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_recurring_payments');

		$this->add_property('id', 'int');
		$this->add_property('plan', 'int');
		$this->add_property('amount', 'decimal');
		$this->add_property('status', 'int');
		$this->add_property('timestamp', 'timestamp');
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
