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

		$this->addProperty('id', 'int');
		$this->addProperty('buyer', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('street', 'varchar');
		$this->addProperty('street2', 'varchar');
		$this->addProperty('email', 'varchar');
		$this->addProperty('phone', 'varchar');
		$this->addProperty('city', 'varchar');
		$this->addProperty('zip', 'varchar');
		$this->addProperty('state', 'varchar');
		$this->addProperty('country', 'varchar');
		$this->addProperty('access_code', 'varchar');
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
