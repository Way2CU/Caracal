<?php

/**
 * Supplier Mananager
 */

namespace Modules\Shop\Supplier;

use \ItemManager;


class Manager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_suppliers');

		$this->add_property('id', 'int');
		$this->add_property('name', 'varchar');
		$this->add_property('phone', 'varchar');
		$this->add_property('email', 'varchar');
		$this->add_property('url', 'varchar');
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
