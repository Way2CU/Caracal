<?php

/**
 * Automated testing storage value manager.
 */

namespace Core\Testing;
use ItemManager;


class Manager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_tests');

		$this->add_property('id', 'int');
		$this->add_property('method', 'varchar');
		$this->add_property('name', 'varchar');
		$this->add_property('version', 'varchar');
		$this->add_property('value', 'int');
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
