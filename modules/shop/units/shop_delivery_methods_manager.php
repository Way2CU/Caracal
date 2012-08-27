<?php

/**
 * Shop Delivery Methods Manager
 *
 * @author MeanEYE.rcf
 */

class ShopDeliveryMethodsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_delivery_methods');

		$this->addProperty('id', 'int');
		$this->addProperty('name', 'ml_varchar');
		$this->addProperty('international', 'boolean');
		$this->addProperty('domestic', 'boolean');
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

		$this->addProperty('id', 'int');
		$this->addProperty('method', 'int');
		$this->addProperty('value', 'numeric');
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

		$this->addProperty('item', 'int');
		$this->addProperty('price', 'int');
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
