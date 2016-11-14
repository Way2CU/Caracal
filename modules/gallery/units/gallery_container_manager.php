<?php

class GalleryContainerManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('gallery_containers');

		$this->add_property('id', 'int');
		$this->add_property('text_id', 'varchar');
		$this->add_property('name', 'ml_varchar');
		$this->add_property('description', 'ml_text');
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
