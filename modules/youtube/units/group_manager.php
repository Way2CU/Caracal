<?php

/**
 * YouTube Implmenetation Module
 * Group Manager
 *
 * @copyright Way2CU, 2011.
 * @author MeanEYE
 */

class YouTube_GroupManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('youtube_groups');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('name', 'ml_varchar');
		$this->addProperty('description', 'ml_text');
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