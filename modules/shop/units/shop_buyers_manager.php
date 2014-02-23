<?php

/**
 * Buyers Manager
 * Shop Module
 *
 * Author: Mladen Mijatov
 */

class ShopBuyersManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_buyers');

		$this->addProperty('id', 'int');
		$this->addProperty('first_name', 'varchar');
		$this->addProperty('last_name', 'varchar');
		$this->addProperty('email', 'varchar');
		$this->addProperty('validated', 'boolean');
		$this->addProperty('password', 'varchar');
		$this->addProperty('guest', 'boolean');
		$this->addProperty('uid', 'varchar');
		$this->addProperty('system_user', 'int');
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
