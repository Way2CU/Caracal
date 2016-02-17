<?php

/**
 * Links Manager
 *
 * Manager used to operate on links and their content.
 *
 * Author: Mladen Mijatov
 */
namespace Modules\Links;

use \ItemManager as ItemManager;


class Manager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('links');

		$this->addProperty('id', 'int');
		$this->addProperty('text', 'ml_varchar');
		$this->addProperty('description', 'ml_text');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('url', 'varchar');
		$this->addProperty('external', 'boolean');
		$this->addProperty('sponsored', 'boolean');
		$this->addProperty('display_limit', 'integer');
		$this->addProperty('sponsored_clicks', 'integer');
		$this->addProperty('total_clicks', 'integer');
		$this->addProperty('image', 'integer');
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
