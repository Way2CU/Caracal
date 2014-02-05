<?php

class ActivityManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('activities');

		$this->addProperty('id', 'int');
		$this->addProperty('activity', 'varchar');
		$this->addProperty('function', 'varchar');
		$this->addProperty('timeout', 'int');
		$this->addProperty('ignore_address', 'boolean');
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
