<?php
/**
 * Shop promotions manager.
 *
 * Author: Mladen Mijatov
 */

namespace Modules\Shop\Transaction;
use ItemManager;


class PromotionManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_transaction_promotions');

		$this->addProperty('id', 'int');
		$this->addProperty('transaction', 'int');
		$this->addProperty('promotion', 'varchar');
		$this->addProperty('discount', 'varchar');
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
