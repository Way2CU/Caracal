<?php

/**
 * Delivery method handler class. This class will manage and set delivery
 * method related properties.
 *
 * Author: Mladen Mijatov
 */

namespace Shop;


final class Delivery {
	private static $methods = array();

	const ALL = 0;
	const INTERNATIONAL = 1;
	const DOMESTIC = 2;

	/**
	 * Register delivery method to be used in shop.
	 *
	 * @param string $name
	 * @param object $method
	 * @return boolean
	 */
	public static function register_method($name, &$method) {
		$result = false;

		// make sure name is unique
		if (array_key_exists($name, self::$methods)) {
			trigger_error("Delivery method '{$name}' is already registered with the system.", E_USER_WARNING);
			return $result;
		}

		// register new method
		self::$methods[$name] = $method;
		$result = true;

		return $result;
	}

	/**
	 * Get delivery method object with specified name.
	 *
	 * @param string $name
	 * @return object
	 */
	public static function get_method($name) {
		$result = null;

		// find method with specified name
		if (array_key_exists($name, self::$methods))
			$result = self::$methods[$name];

		return $result;
	}

	/**
	 * Check if delivery method exists.
	 *
	 * @param string $name
	 * @return boolean
	 */
	public static function exists($name) {
		return array_key_exists($name, self::$methods);
	}

	/**
	 * Return total number registered delivery methods.
	 *
	 * @return integer
	 */
	public static function method_count() {
		return count(self::$methods);
	}

	/**
	 * Get printable list of methods and their properties. If specified
	 * only delivery methods of certain type will be returned.
	 *
	 * Example response:
	 *
	 * $result = array(
	 * 		'method_name'	=> array(
	 * 				'title'	=> 'localized method name',
	 * 				'icon'	=> 'http://domain.com/url/to/icon.png',
	 * 				'image'	=> 'http://domain.com/url/to/image_200x55.png',
	 * 				'small_image'	=> 'http://domain.com/url/to/image_100x28.png',
	 * 				'international'	=> false
	 * 			)
	 * );
	 *
	 * @param integer $type
	 * @return array
	 */
	public static function get_printable_list($type=self::ALL) {
		$result = array();

		if (count(self::$methods) > 0)
			foreach (self::$methods as $name => $method) {
				$international = $method->isInternational();
				$data = array(
					'title'			=> $method->getTitle(),
					'icon'			=> $method->getIcon(),
					'image'			=> $method->getImage(),
					'small_image'	=> $method->getSmallImage(),
					'international'	=> $international
				);

				// add method data to result
				if (
					($international && $type == self::INTERNATIONAL) ||
					(!$international && $type == self::DOMESTIC) ||
					$type == self::ALL
				)
					$result[] = $data;
			}

		return $result;
	}

	/**
	 * Set delivery method for current transaction and optionally set
	 * delivery type.
	 *
	 * @param string $method
	 * @param string $type
	 * @return boolean;
	 */
	public static function set_method($method, $type=null) {
		$result = false;
		$transaction = Transaction::get_current();

		// make sure transaction exists
		if (is_null($transaction))
			return $result;

		// update method and type
		if (array_key_exists($method, self::$methods)) {
			$manager = Transaction::get_manager();

			// prepare data
			$data = array(
				'delivery_method'	=> $method,
				'delivery_type'		=> $type
			);

			// update data
			$manager->updateData($data, array('id' => $transaction->id));
			$result = true;
		}

		return $result;
	}

	/**
	 * Get method object for currently selected name.
	 *
	 * @return object
	 */
	public static function get_current() {
		$name = self::get_current_name();
		return self::get_method($name);
	}

	/**
	 * Return delivery method for current transaction.
	 *
	 * @return string
	 */
	public static function get_current_name() {
		$result = '';
		$transaction = Transaction::get_current();

		// make sure transaction exists
		if (is_null($transaction))
			return $result;

		// prepare result
		$result = $transaction->delivery_method;

		return $result;
	}

	/**
	 * Return type of delivery for current transaction.
	 *
	 * @return string
	 */
	public static function get_current_type() {
		$result = '';
		$transaction = Transaction::get_current();

		// make sure transaction exists
		if (is_null($transaction))
			return $result;

		// prepare result
		$result = $transaction->delivery_type;

		return $result;
	}
}

?>
