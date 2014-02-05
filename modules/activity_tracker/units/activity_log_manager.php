<?php

class ActivityLogManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('activitie_log');

		$this->addProperty('id', 'int');
		$this->addProperty('activity', 'int');
		$this->addProperty('user', 'int');
		$this->addProperty('address', 'varchar');
		$this->addProperty('timestamp', 'timestamp');
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
