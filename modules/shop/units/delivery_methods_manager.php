<?php

/**
 * Shop Delivery Methods Manager
 *
 * Author: Mladen Mijatov
 */

class ShopDeliveryMethodsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_delivery_methods');

		$this->add_property('id', 'int');
		$this->add_property('name', 'ml_varchar');
		$this->add_property('international', 'boolean');
		$this->add_property('domestic', 'boolean');
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

class ShopDeliveryMethodPricesManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_delivery_method_prices');

		$this->add_property('id', 'int');
		$this->add_property('method', 'int');
		$this->add_property('value', 'numeric');
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

class ShopDeliveryItemRelationsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_delivery_item_relations');

		$this->add_property('item', 'int');
		$this->add_property('price', 'int');
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
