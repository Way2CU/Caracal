<?php

/**
 * Membership manager for item properties. This manager handles relations between
 * properties table and shop items table.
 *
 * Author: Mladen Mijatov
 */
namespace Modules\Shop\Managers;

use \ItemManager as ItemManager;


final class PropertyMembership extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_item_property_membership');

		$this->addProperty('item', 'int');
		$this->addProperty('property', 'int');
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
