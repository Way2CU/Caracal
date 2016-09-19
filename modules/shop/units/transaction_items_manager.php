<?php

/**
 * Shop Transaction Items Manager
 *
 * Author: Mladen Mijatov
 */

class ShopTransactionItemsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_transaction_items');

		$this->add_property('id', 'int');
		$this->add_property('transaction', 'int');
		$this->add_property('item', 'int');
		$this->add_property('price', 'decimal');
		$this->add_property('tax', 'decimal');
		$this->add_property('amount', 'int');
		$this->add_property('description', 'varchar');
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
