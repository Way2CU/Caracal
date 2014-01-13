<?php

class FeedbackManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('feedback');

		$this->addProperty('id', 'int');
		$this->addProperty('user', 'int');
		$this->addProperty('timestamp', 'timestamp');
		$this->addProperty('message', 'text');
		$this->addProperty('url', 'varchar');
		$this->addProperty('status', 'int');
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
