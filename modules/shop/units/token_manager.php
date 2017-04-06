<?php

namespace Modules\Shop;
use ItemManager;


class TokenManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_payment_tokens');

		$this->add_property('id', 'int');
		$this->add_property('payment_method', 'varchar');
		$this->add_property('buyer', 'int');
		$this->add_property('name', 'varchar');
		$this->add_property('token', 'varchar');
		$this->add_property('expires', 'boolean');
		$this->add_property('expiration_month', 'int');
		$this->add_property('expiration_year', 'int');
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
