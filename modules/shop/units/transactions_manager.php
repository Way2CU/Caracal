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

		$this->add_property('id', 'int');
		$this->add_property('buyer', 'int');
		$this->add_property('address', 'int');
		$this->add_property('uid', 'varchar');
		$this->add_property('type', 'smallint');
		$this->add_property('status', 'smallint');
		$this->add_property('currency', 'int');
		$this->add_property('handling', 'decimal');
		$this->add_property('shipping', 'decimal');
		$this->add_property('weight', 'decimal');
		$this->add_property('payment_method', 'varchar');
		$this->add_property('payment_token', 'int');
		$this->add_property('delivery_method', 'varchar');
		$this->add_property('delivery_type', 'varchar');
		$this->add_property('remark', 'text');
		$this->add_property('remote_id', 'varchar');
		$this->add_property('total', 'decimal');
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
