<?php

/**
 * YouTube Implmenetation Module
 * Video Manager
 *
 * @copyright Way2CU, 2011.
 * @author MeanEYE
 */

class YouTube_VideoManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('youtube_video');

		$this->add_property('id', 'int');
		$this->add_property('text_id', 'varchar');
		$this->add_property('video_id', 'varchar');
		$this->add_property('title', 'ml_varchar');
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
