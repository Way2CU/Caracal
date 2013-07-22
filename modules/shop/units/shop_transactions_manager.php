<?php

/**
 * Shop Transactions Manager
 *
 * Author: Mladen Mijatov
 */

class ShopTransactionsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_transactions');

		$this->addProperty('id', 'int');
		$this->addProperty('buyer', 'int');
		$this->addProperty('address', 'int');
		$this->addProperty('uid', 'varchar');
		$this->addProperty('type', 'smallint');
		$this->addProperty('status', 'smallint');
		$this->addProperty('currency', 'int');
		$this->addProperty('handling', 'decimal');
		$this->addProperty('shipping', 'decimal');
		$this->addProperty('payment_method', 'varchar');
		$this->addProperty('delivery_method', 'varchar');
		$this->addProperty('remark', 'text');
		$this->addProperty('token', 'varchar');
		$this->addProperty('total', 'decimal');
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
