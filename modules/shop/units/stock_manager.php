<?php

/**
 * Shop Stock Manager
 *
 * Author: Mladen Mijatov
 */

class ShopStockManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_stock');

		$this->add_property('id', 'int');
		$this->add_property('item', 'int');
		$this->add_property('size', 'int');
		$this->add_property('amount', 'int');
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
