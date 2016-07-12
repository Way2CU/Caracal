<?php

class FeedbackManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('feedback');

		$this->add_property('id', 'int');
		$this->add_property('user', 'int');
		$this->add_property('timestamp', 'timestamp');
		$this->add_property('message', 'text');
		$this->add_property('url', 'varchar');
		$this->add_property('status', 'int');
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
