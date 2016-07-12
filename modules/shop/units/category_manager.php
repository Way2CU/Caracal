<?php

/**
* Shop Item Manager
*
* Manager used to manipulate shop item categories.
*
* Author: Mladen Mijatov
*/

class ShopCategoryManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_categories');

		$this->add_property('id', 'int');
		$this->add_property('parent', 'int');
		$this->add_property('image', 'int');
		$this->add_property('text_id', 'varchar');
		$this->add_property('title', 'ml_varchar');
		$this->add_property('description', 'ml_text');
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
