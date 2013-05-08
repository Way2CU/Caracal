<?php

/**
 * Code optimizer object is used to compile JavaScript and CSS. By reducting its
 * size, web page responsivness is considerably increased.
 *
 * There's no need to use this class manually as both template handler and head tag
 * module will automatically use this class if configured.
 */

class CodeOptimizer {
	private static $_instance;

	private $script_list = array();
	private $style_list = array();

	const LEVEL_NONE = 0;
	const LEVEL_BASIC = 1;
	const LEVEL_ADVANCED = 2;

	/**
	 * Constructor
	 */
	protected function __construct() {
	}

	/**
	 * Get a single instance of this object.
	 * @return object
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Add script to be compiled.
	 *
	 * @param string $url
	 */
	public function addScript($url) {
	}

	/**
	 * Add style to be compiled.
	 *
	 * @param string $url
	 */
	public function addStyle($url) {
	}

	/**
	 * Return compiled scripts and styles.
	 *
	 * @return string
	 */
	public function getData() {
	}
}

?>
