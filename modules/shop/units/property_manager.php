<?php

/**
 * Shop item features manager. This class manages item specific features.
 *
 * Author: Mladen Mijatov
 */
namespace Modules\Shop\Managers;

use \ItemManager as ItemManager;


final class PropertyManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_item_properties');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('name', 'ml_varchar');
		$this->addProperty('type', 'int');
		$this->addProperty('values', 'text');
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
