<?php

class UserPageManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('user_pages');

		$this->addProperty('id', 'int');
		$this->addProperty('timestamp', 'timestamp');
		$this->addProperty('author', 'int');
		$this->addProperty('owner', 'int');
		$this->addProperty('title', 'ml_varchar');
		$this->addProperty('content', 'ml_text');
		$this->addProperty('editable', 'boolean');
		$this->addProperty('visible', 'boolean');
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