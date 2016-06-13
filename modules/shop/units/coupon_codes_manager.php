<?php
/**
 * Manager for coupon codes.
 */
namespace Modules\Shop\Promotion;

use \ItemManager as ItemManager;


class CouponCodesManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_coupon_codes');

		$this->addProperty('id', 'int');
		$this->addProperty('coupon', 'int');
		$this->addProperty('code', 'varchar');
		$this->addProperty('times_used', 'int');
		$this->addProperty('timestamp', 'timestamp');
		$this->addProperty('discount', 'varchar');
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
