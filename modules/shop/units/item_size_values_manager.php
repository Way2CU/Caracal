<?php

class ShopItemSizeValuesManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_item_size_values');

		$this->add_property('id', 'int');
		$this->add_property('definition', 'int');
		$this->add_property('value', 'ml_varchar');
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
