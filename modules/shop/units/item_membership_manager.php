<?php

/**
 * Shop Item Membership Manager
 *
 * Author: Mladen Mijatov
 */

class ShopItemMembershipManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_item_membership');

		$this->add_property('category', 'int');
		$this->add_property('item', 'int');
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
