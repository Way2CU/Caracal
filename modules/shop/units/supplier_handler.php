<?php

/**
 * Shop Supplier Handler
 *
 * Provides functionality for associating suppliers with individual
 * items.
 */

namespace Modules\Shop\Supplier;


class Handler {
	private static $_instance;

	const SUB_ACTION = 'suppliers';

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		$this->parent = $parent;
		$this->name = $this->parent->name;
		$this->path = $this->parent->path;
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance($parent) {
		if (!isset(self::$_instance))
			self::$_instance = new self($parent);

		return self::$_instance;
	}

	/**
	 * Transfer control to group
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transfer_control($params = array(), $children = array()) {
		$action = isset($params['sub_action']) ? $params['sub_action'] : null;
	}
}


?>
