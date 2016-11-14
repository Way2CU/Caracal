<?php

/**
 * Article manager class
 *
 * Author: Mladen Mijatov
 */
namespace Modules\Articles;
use ItemManager;


class Manager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('articles');

		$this->add_property('id', 'int');
		$this->add_property('group', 'int');
		$this->add_property('text_id', 'varchar');
		$this->add_property('timestamp', 'timestamp');
		$this->add_property('title', 'ml_varchar');
		$this->add_property('content', 'ml_text');
		$this->add_property('author', 'int');
		$this->add_property('gallery', 'int');
		$this->add_property('visible', 'boolean');
		$this->add_property('views', 'int');
		$this->add_property('votes_up', 'int');
		$this->add_property('votes_down', 'int');
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
