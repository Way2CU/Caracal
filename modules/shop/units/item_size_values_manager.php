<?php

class ShopItemSizeValuesManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_item_size_values');

		$this->addProperty('id', 'int');
		$this->addProperty('definition', 'int');
		$this->addProperty('value', 'ml_varchar');
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
