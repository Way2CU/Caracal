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

		$this->add_property('id', 'int');
		$this->add_property('text_id', 'varchar');
		$this->add_property('name', 'ml_varchar');
		$this->add_property('has_limit', 'boolean');
		$this->add_property('has_timeout', 'boolean');
		$this->add_property('limit', 'int');
		$this->add_property('timeout', 'timestamp');
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
