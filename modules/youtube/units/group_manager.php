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

		$this->add_property('id', 'int');
		$this->add_property('text_id', 'varchar');
		$this->add_property('name', 'ml_varchar');
		$this->add_property('description', 'ml_text');
		$this->add_property('visible', 'boolean');
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
