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

require_once('method.php');
require_once('manager.php');
require_once('methods/simple.php');


class TestExistsError extends \Exception {}


final class Handler {
	private static $_instance;
	private $tests;

	/**
	 * Constructor
	 */
	protected function __construct() {
		$this->tests = array();

		// create built-in tests
		new Methods\Simple();
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
	 * Return instace of database manager.
	 *
	 * @return object
	 */
	public function get_manager() {
		return Manager::get_instance();
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

		// make sure name and method of the test are specified
		if (!isset($tag_params['name']) || !isset($tag_params['method'])) {
			trigger_error('Missing `name` and/or `method` for `cms:test` tag.', E_USER_WARNING);
			return;
		}

		// get name and method
		$method = fix_chars($tag_params['method']);
		$name = fix_chars($tag_params['name']);

		// make sure we have a test with specified method
		if (!array_key_exists($method, $this->tests)) {
			trigger_error("Unknown test method `{$method}`.", E_USER_NOTICE);
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
			unset($options['method']);
			unset($options['name']);

			$versions = array();
			foreach ($children as $tag) {
				if (!$tag->tagName == 'version')
					continue;

				if (!isset($tag->tagAttrs['name'])) {
					trigger_error("Missing version name for `cms:test` tag with name `{$name}`.", E_USER_WARNING);
					continue;
				}

				$versions[] = $tag->tagAttrs['name'];
			}

			// ask test class to provide choice
			$test = $this->tests[$method];
			$version = $test->get_version($name, $options, $versions);

			// store choice for current session
			$_SESSION['testing'][$name] = $version;
		}

		// show specified version
		foreach ($children as $tag) {
			// skip versions until we reach one we need
			if (!isset($tag->tagAttrs['name']) || $tag->tagAttrs['name'] != $version)
				continue;

			// transfer control back to template handler and break the loop
			$template->parse($tag->tagChildren);
			break;
		}
	}

	/**
	 * Register testing method to be used by the system.
	 *
	 * @param string $test_name
	 * @param object $text
	 * @throws TestExistsError
	 */
	public function register_method($test_name, &$test) {
		if (array_key_exists($test_name, $this->tests))
			throw TestExistsError("Specified name `{$test_name}` is already present in the system!");

		$this->tests[$test_name] = $test;
	}
}

?>
