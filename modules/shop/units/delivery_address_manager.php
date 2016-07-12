<?php

/**
 * Shop Buyer Addresses Manager
 *
 * Author: Mladen Mijatov
 */

class ShopDeliveryAddressManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_delivery_address');

		$this->add_property('id', 'int');
		$this->add_property('buyer', 'int');
		$this->add_property('name', 'varchar');
		$this->add_property('street', 'varchar');
		$this->add_property('street2', 'varchar');
		$this->add_property('email', 'varchar');
		$this->add_property('phone', 'varchar');
		$this->add_property('city', 'varchar');
		$this->add_property('zip', 'varchar');
		$this->add_property('state', 'varchar');
		$this->add_property('country', 'varchar');
		$this->add_property('access_code', 'varchar');
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
