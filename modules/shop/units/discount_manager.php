<?php

/**
 * Shop Discount Manager
 *
 * Author: Mladen Mijatov
 */

class ShopDiscountManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_discounts');

		$this->add_property('id', 'int');
		$this->add_property('type', 'int');
		$this->add_property('name', 'ml_varchar');
		$this->add_property('description', 'ml_text');
		$this->add_property('percent', 'decimal');
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

class ShopDiscountItemsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_discounts');

		$this->add_property('id', 'int');
		$this->add_property('discount', 'int');
		$this->add_property('item', 'int');
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
