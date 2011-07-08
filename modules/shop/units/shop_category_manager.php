<?php

/**
* Shop Item Manager
*
* Manager used to manipulet shop item categories. 
*
* @author MeanEYE.rcf
*/

class ShopCategoryManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_categories');

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