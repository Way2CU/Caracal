<?php

/**
 * Shop Item Membership Manager
 *
 * @author MeanEYE.rcf
 */

class ShopItemMembershipManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_item_membership');

		$this->addProperty('id', 'int');
		$this->addProperty('category', 'int');
		$this->addProperty('item', 'int');
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
