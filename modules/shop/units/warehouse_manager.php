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

		$this->add_property('id', 'int');
		$this->add_property('name', 'varchar');
		$this->add_property('street', 'varchar');
		$this->add_property('street2', 'varchar');
		$this->add_property('city', 'varchar');
		$this->add_property('zip', 'varchar');
		$this->add_property('country', 'varchar');
		$this->add_property('state', 'varchar');
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
