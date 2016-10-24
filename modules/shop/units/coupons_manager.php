<?php

/**
 * Manager for coupons.
 */
namespace Modules\Shop\Promotion;
use ItemManager;


class CouponsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_coupons');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('name', 'ml_varchar');
		$this->addProperty('has_limit', 'boolean');
		$this->addProperty('has_timeout', 'boolean');
		$this->addProperty('limit', 'int');
		$this->addProperty('timeout', 'timestamp');
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
