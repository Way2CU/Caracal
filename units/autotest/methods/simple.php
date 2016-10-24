<?php

/**
 * Simple multiple-choice test.
 *
 * This test object will equally favor all choices and distribute
 * views among them. It's commonly called "AB test" but with added
 * ability of specifying more than two versions.
 *
 * Author: Mladen Mijatov
 */
namespace Core\Testing\Tests;


class Simple extends Core\Testing\Base {
	private $
	private function __construct() {
		$tests = \Core\Testing\AutoTest::get_instance();
		$tests->register_test('simple', $this);
	}

	/**
	 * Return version of template to display.
	 *
	 * @param string $name
	 * @param array $options
	 * @param array $versions
	 * @return string
	 */
	public function get_version($name, $options, $versions) {
	}
}

?>
