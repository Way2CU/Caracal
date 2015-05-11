<?php

namespace Modules\Shop;

use \ItemManager as ItemManager;


class TokenManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_payment_tokens');

		$this->addProperty('id', 'int');
		$this->addProperty('payment_method', 'string');
		$this->addProperty('buyer', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('token', 'varchar');
		$this->addProperty('expires', 'boolean');
		$this->addProperty('expiration_month', 'int');
		$this->addProperty('expiration_year', 'int');
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
