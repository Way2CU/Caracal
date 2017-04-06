<?php

/**
 * Remarks manager for shop items.
 *
 * Author: Mladen Mijatov
 */

namespace Modules\Shop\Item;
use ItemManager;


class RemarkManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_item_remarks');

		$this->add_property('id', 'int');
		$this->add_property('item', 'int');
		$this->add_property('remark', 'text');
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
