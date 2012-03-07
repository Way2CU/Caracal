<?php

class CacheManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('cache');

		$this->addProperty('uid', 'char');
		$this->addProperty('url', 'varchar');
		$this->addProperty('times_used', 'int');
		$this->addProperty('expires', 'timestamp');
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
