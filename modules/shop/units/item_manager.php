<?php

/**
 * Shop Item Manager
 *
 * This manager is used to manipulate data in shop_items table.
 * Don't try to access this table manually as other tables depend
 * on it (like payment logs and similar).
 *
 * Author: Mladen Mijatov
 */

class ShopItemManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_items');

		$this->add_property('id', 'int');
		$this->add_property('uid', 'varchar');
		$this->add_property('name', 'ml_varchar');
		$this->add_property('description', 'ml_text');
		$this->add_property('gallery', 'int');
		$this->add_property('manufacturer', 'int');
		$this->add_property('size_definition', 'int');
		$this->add_property('colors', 'varchar');
		$this->add_property('author', 'int');
		$this->add_property('views', 'int');
		$this->add_property('price', 'decimal');
		$this->add_property('discount', 'decimal');
		$this->add_property('tax', 'decimal');
		$this->add_property('weight', 'decimal');
		$this->add_property('votes_up', 'int');
		$this->add_property('votes_down', 'int');
		$this->add_property('timestamp', 'timestamp');
		$this->add_property('priority', 'int');
		$this->add_property('visible', 'boolean');
		$this->add_property('deleted', 'boolean');
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
