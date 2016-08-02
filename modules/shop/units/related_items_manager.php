<?php

/**
 * Shop Related Items Manager
 *
 * Author: Mladen Mijatov
 */

class ShopRelatedItemsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_related_items');

		$this->add_property('item', 'int');
		$this->add_property('related', 'int');
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
