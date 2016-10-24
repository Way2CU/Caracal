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

		$this->addProperty('id', 'int');
		$this->addProperty('method', 'varchar');
		$this->addProperty('name', 'varchar');
		$this->addProperty('version', 'varchar');
		$this->addProperty('value', 'int');
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
