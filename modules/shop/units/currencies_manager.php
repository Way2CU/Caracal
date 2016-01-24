<?php

/**
 * Shop Currency Manager
 *
 * This manager is used to manipulate stored currencies.
 *
 * Author: Mladen Mijatov
 */

class ShopCurrenciesManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_currencies');

		$this->addProperty('id', 'int');
		$this->addProperty('currency', 'varchar');
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
