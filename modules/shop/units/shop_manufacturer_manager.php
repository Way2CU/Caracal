<?php

/**
 * Shop Manufacturer Manager
 *
 * @author MeanEYE.rcf
 */

class ShopManufacturerManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_items');

		$this->addProperty('id', 'int');
		$this->addProperty('name', 'ml_varchar');
		$this->addProperty('web_site', 'varchar');
		$this->addProperty('logo', 'int');
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
