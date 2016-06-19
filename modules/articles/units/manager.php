<?php

/**
 * Article manager class.
 * Author: Mladen Mijatov
 */
namespace Modules\Articles;

use \ItemManager as ItemManager;


class Manager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('articles');

		$this->addProperty('id', 'int');
		$this->addProperty('group', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('timestamp', 'timestamp');
		$this->addProperty('title', 'ml_varchar');
		$this->addProperty('content', 'ml_text');
		$this->addProperty('author', 'int');
		$this->addProperty('gallery', 'int');
		$this->addProperty('visible', 'boolean');
		$this->addProperty('views', 'int');
		$this->addProperty('votes_up', 'int');
		$this->addProperty('votes_down', 'int');
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
