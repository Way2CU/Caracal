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

		$this->addProperty('id', 'int');
		$this->addProperty('parent', 'int');
		$this->addProperty('image', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('title', 'ml_varchar');
		$this->addProperty('description', 'ml_text');
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
