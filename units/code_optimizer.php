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

	public const LEVEL_NONE = -1;
	public const LEVEL_BASIC = 0;
	public const LEVEL_ADVANCED = 1;

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
	 * @param string $data
	 */
	public function addScript($url=null, $data=null) {
	}

	/**
	 * Add style to be compiled.
	 *
	 * @param string $url
	 * @param string $data
	 */
	public function addStyle($url=null, $data=null) {
	}
}

?>
