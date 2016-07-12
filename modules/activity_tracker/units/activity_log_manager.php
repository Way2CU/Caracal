<?php

class ActivityLogManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('activity_log');

		$this->add_property('id', 'int');
		$this->add_property('activity', 'int');
		$this->add_property('user', 'int');
		$this->add_property('address', 'varchar');
		$this->add_property('timestamp', 'timestamp');
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
