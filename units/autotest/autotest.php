<?php

/**
 * Auto-testing support for pages.
 *
 * This class is designed to provide easy way to do automatic testing and
 * selection of best choice. It is a central point aorund which other testing
 * extensions work.
 *
 * Author: Mladen Mijatov
 */
namespace Core\Testing;


final class Tests {
	private static $_instance;
	private $tests;
	private $manager;

	/**
	 * Constructor
	 */
	protected function __construct() {
		$this->tests = array();
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Handle rendering test tag.
	 *
	 * @param object $template
	 * @param array $tag_params
	 * @param array $children
	 */
	public function show_version($template, $tag_params, $children) {
		$name = null;
		$version = null;

		// make sure name and type of the test are specified
		if (!isset($tag_params['name']) || !isset($tag_params['type'])) {
			trigger_error('Missing \'name\' and/or \'type\' for \'cms:test\' tag.', E_USER_WARNING);
			return;
		}

		// get name and type
		$type = fix_chars($tag_params['type']);
		$name = fix_chars($tag_params['name']);

		// make sure we have a test with specified type
		if (!array_key_exists($type, $this->tests)) {
			trigger_error("Unknown test type '{$type}'.", E_USER_NOTICE);
			return;
		}

		// check session storage
		if (!isset($_SESSION['testing']))
			$_SESSION['testing'] = array();

		// get version to display
		if (isset($_SESSION['testing'][$name])) {
			// get stored value
			$version = $_SESSION['testing'][$name];

		} else {
			// prepare options
			$options = $tag_params;
			unset($options['type']);
			unset($options['name']);

			$versions = array();
			foreach ($children as $tag) {
				if (!$tag->tagName == 'version')
					continue;

				if (!isset($tag->tagAttrs['name'])) {
					trigger_error('Missing version name for \'cms:test\' tag with name \''.$name.'\'.', E_USER_WARNING);
					continue;
				}

				$versions[] = $tag->tagAttrs['name'];
			}

			// ask test class to provide choice
			$test = $this->tests[$type];
			$version = $test->get_version($name, $options, $versions);

			// store choice for current session
			$_SESSION['testing'][$name] = $version;
		}
	}

	/**
	 * Return manager used for saving and loading test related settings.
	 *
	 * @return object
	 */
	public function get_manager() {
	}
}

?>
