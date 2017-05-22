<?php

/**
 * Manager for page descriptions.
 *
 * Author: Mladen Mijatov
 */

namespace Modules\PageDescription;
use ItemManager;


class Manager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('page_descriptions');

		$this->add_property('id', 'int');
		$this->add_property('url', 'varchar');
		$this->add_property('title', 'ml_varchar');
		$this->add_property('content', 'ml_varchar');
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
