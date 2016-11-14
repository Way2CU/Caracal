<?php

/**
 * Shop Manufacturer Manager
 *
 * Author: Mladen Mijatov
 */

class ShopManufacturerManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_manufacturers');

		$this->add_property('id', 'int');
		$this->add_property('name', 'ml_varchar');
		$this->add_property('web_site', 'varchar');
		$this->add_property('logo', 'int');
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
