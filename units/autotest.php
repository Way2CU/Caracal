<?php

/**
 * Auto-testing support for pages.
 *
 * This class is designed to provide easy way to do automatic testing and
 * selection of best choice.
 *
 * Author: Mladen Mijatov
 */

final class AutoTest {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Handle rendering test tag.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Test($tag_params, $children) {
	}
}

?>
