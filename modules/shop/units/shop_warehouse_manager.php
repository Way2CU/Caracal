<?php

/**
 * Shop Warehouse Manager
 */

class ShopWarehouseManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_warehouse');

		$this->addProperty('id', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('street', 'varchar');
		$this->addProperty('street2', 'varchar');
		$this->addProperty('city', 'varchar');
		$this->addProperty('zip', 'varchar');
		$this->addProperty('country', 'varchar');
		$this->addProperty('state', 'varchar');
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
