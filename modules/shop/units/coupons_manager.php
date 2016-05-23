<?php

/**
 * Manager for coupons.
 */
namespace Modules\Shop\Promotion;

use \ItemManager as ItemManager;


class CouponsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_coupons');

		$this->addProperty('id', 'int');
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
