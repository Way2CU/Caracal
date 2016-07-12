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

		$this->add_property('id', 'int');
		$this->add_property('text', 'ml_varchar');
		$this->add_property('description', 'ml_text');
		$this->add_property('text_id', 'varchar');
		$this->add_property('url', 'varchar');
		$this->add_property('external', 'boolean');
		$this->add_property('sponsored', 'boolean');
		$this->add_property('display_limit', 'integer');
		$this->add_property('sponsored_clicks', 'integer');
		$this->add_property('total_clicks', 'integer');
		$this->add_property('image', 'integer');
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
