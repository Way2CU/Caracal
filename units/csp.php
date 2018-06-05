<?php

/**
 * Content Security Policy Parser
 *
 * This parser enables easy modification of content security policy string
 * without having to resort to manual configuration.
 *
 * This class automatically updates system configuration for CSP.
 *
 * Author: Mladen Mijatov
 */
namespace Core\CSP;

final class Element {
	const DEF = 'default-src';
	const SCRIPTS = 'script-src';
	const STYLES = 'style-src';
	const IMAGES = 'img-src';
	const FONTS = 'font-src';
	const MEDIA = 'media-src';
}


final class Parser {
	private static $default_policy = 'script-src \'self\'';
	private static $policy = null;

	/**
	 * Parse all values and return associative array.
	 *
	 * @return array
	 */
	private static function get_elements() {
		if (is_null(self::$policy))
			self::$policy = self::$default_policy;

		// split policy into manageable chunks
		$elements = array();
		$raw_elements = explode(';', self::$policy);

		// parse each chunk
		foreach ($raw_elements as $raw_values) {
			// parse values
			$values = array();
			preg_match_all('/\'(?:\\\\.|[^\\\\\'])*\'|\S+/', $raw_values, $values);

			// pack result
			$key = array_shift($values[0]);
			$elements[$key] = $values[0];
		}

		return $elements;
	}

	/**
	 * Set values for all elements.
	 *
	 * @param array $values
	 */
	private static function set_elements($elements) {
		// prepare elements for update
		$raw_elements = array();
		foreach ($elements as $key => $values)
			$raw_elements[] = $key.' '.join(' ', $values);

		// update policy
		self::$policy = join(';', $raw_elements);
	}

	/**
	 * Return array containing list of values for specified element.
	 *
	 * @param string $element
	 * @return array
	 */
	public static function get_values($element) {
		$elements = self::get_elements();

		$result = null;
		if (isset($elements[$element]))
			$result = $elements[$element];

		return $result;
	}

	/**
	 * Set values for specified element.
	 *
	 * @param string $element
	 * @param array $values
	 */
	public static function set_values($element, $values) {
		$elements = self::get_elements();
		$elements[$element] = $values;
		self::set_elements($elements);
	}

	/**
	 * Add single value to the element list.
	 *
	 * @param string $element
	 * @param string $value
	 */
	public static function add_value($element, $value) {
		$elements = self::get_elements();

		if (!isset($elements[$element]))
			$elements[$element] = array();
		$elements[$element][] = $value;

		self::set_elements($elements);
	}

	/**
	 * Return compiled policy string.
	 *
	 * @return string
	 */
	public static function get_policy() {
		return self::$policy;
	}
}

?>
