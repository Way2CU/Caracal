<?php

/**
 * Link Group Manager
 *
 * Manager used to operate on link groups.
 *
 * Author: Mladen Mijatov
 */
namespace Modules\Links;

use \ItemManager as ItemManager;


class GroupManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('link_groups');

		$this->add_property('id', 'int');
		$this->add_property('name', 'ml_varchar');
		$this->add_property('text_id', 'varchar');
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
