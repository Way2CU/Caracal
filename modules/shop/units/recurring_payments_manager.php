<?php

class ShopRecurringPaymentsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_recurring_payments');

		$this->addProperty('id', 'int');
		$this->addProperty('plan', 'int');
		$this->addProperty('amount', 'decimal');
		$this->addProperty('status', 'int');
		$this->addProperty('timestamp', 'timestamp');
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
