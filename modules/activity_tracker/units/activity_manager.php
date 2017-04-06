<?php

class ActivityManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('activities');

		$this->add_property('id', 'int');
		$this->add_property('activity', 'varchar');
		$this->add_property('function', 'varchar');
		$this->add_property('timeout', 'int');
		$this->add_property('ignore_address', 'boolean');
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
