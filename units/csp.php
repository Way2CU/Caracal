<?php

/**
 * Content Security Policy Parser
 *
 * This parser enables easy modification of content security policy string
 * without having to resort to manual configuration.
 *
 * This class automatically updates system configuration for CSP.
 *
 * Usage example:
 *
 *	Policy::add_value('script-src', 'domain.com/scripts/something.js');
 *
 * Author: Mladen Mijatov
 */
namespace Core\CSP;


final class Policy {
	private static $policy = array();
	private static $nonce = null;

	/**
	 * Parse all values and return associative array.
	 *
	 * @param string $policy
	 * @return array
	 */
	private static function parse($policy) {
		if (is_null(self::$policy))
			self::$policy = self::$default_policy;

		// split policy into manageable chunks
		$elements = array();
		$raw_elements = explode(';', $policy);

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
	 * Set values for all elements in one go.
	 *
	 * @param array $policy
	 */
	private static function set($policy) {
		self::$policy = $policy;
	}

	/**
	 * Return array containing list of values for specified element.
	 *
	 * @param string $element
	 * @return array
	 */
	public static function get_values($element) {
		return isset(self::$policy[$element]) ? self::$policy[$element] : null;
	}

	/**
	 * Set values for specified element.
	 *
	 * @param string $element
	 * @param array $values
	 */
	public static function set_values($element, $values) {
		self::$policy[$element] = $values;
	}

	/**
	 * Add NONCE number to element's policy. Number is randomly generated per
	 * request and can serve as an additional layer of security without having to resort
	 * to using `unsafe-inline` values.
	 *
	 * Usage:
	 *	<style href="..." cms:nonce/>
	 *
	 * @param string $element
	 */
	public static function add_nonce($element) {
		$value = self::get_nonce();

		if (!isset(self::$policy[$element]))
			self::$policy[$element] = array();
		self::$policy[$element][] = "'nonce-{$value}'";
	}

	/**
	 * Add single value to the element list.
	 *
	 * @param string $element
	 * @param string $value
	 */
	public static function add_value($element, $value) {
		if (!isset(self::$policy[$element]))
			self::$policy[$element] = array();
		self::$policy[$element][] = $value;
	}

	/**
	 * Add multiple values to the element list.
	 *
	 * @param string $element
	 * @param array $values
	 */
	public static function add_values($element, $values) {
		if (!isset(self::$policy[$element]))
			self::$policy[$element] = array();
		self::$policy[$element] = array_merge(self::$policy[$element], $values);
	}

	/**
	 * Return compiled policy string.
	 *
	 * @return string
	 */
	public static function get_policy() {
		$result = array();
		foreach (self::$policy as $element => $values)
			$result[] = $element.' '.join(' ', $values);
		return join('; ', $result);
	}

	/**
	 * Return value of NONCE.
	 *
	 * @return string
	 */
	public static function get_nonce() {
		global $db_config;

		if (is_null(self::$nonce))
			self::$nonce = hash_hmac('sha1', uniqid("", true), $db_config['pass']);

		return self::$nonce;
	}
}

?>
