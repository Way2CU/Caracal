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

		$this->add_property('id', 'int');
		$this->add_property('first_name', 'varchar');
		$this->add_property('last_name', 'varchar');
		$this->add_property('email', 'varchar');
		$this->add_property('phone', 'varchar');
		$this->add_property('guest', 'boolean');
		$this->add_property('system_user', 'int');
		$this->add_property('agreed', 'boolean');
		$this->add_property('promotions', 'boolean');
		$this->add_property('uid', 'varchar');
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
