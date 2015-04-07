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
		self::$methods[$name] = $module;
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
	 * Return delivery method for current transaction.
	 *
	 * @return string
	 */
	public static function get_current_method() {
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
	public static function get_current_method_type() {
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
