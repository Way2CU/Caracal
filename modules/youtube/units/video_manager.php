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

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('video_id', 'varchar');
		$this->addProperty('title', 'ml_varchar');
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