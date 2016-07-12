<?php

/**
 * Shop item features manager. This class manages item specific features.
 *
 * Author: Mladen Mijatov
 */
namespace Modules\Shop\Property;

use \ItemManager as ItemManager;


final class Manager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_item_properties');

		$this->add_property('id', 'int');
		$this->add_property('item', 'int');
		$this->add_property('text_id', 'varchar');
		$this->add_property('name', 'ml_varchar');
		$this->add_property('type', 'varchar');
		$this->add_property('value', 'text');
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
