<?php

/**
 * Handler class for payment method tokens. This class is used to manage and
 * provide easy access to payment method specific tokens.
 *
 * Author: Mladen Mijatov
 */

namespace Shop;


final class TokenExistsError extends \Exception {}
final class UnknownTokenError extends \Exception {}


final class Token {
	private static $manager = null;

	/**
	 * Get transaction manager.
	 *
	 * @return object
	 */
	public static function get_manager() {
		if (is_null(self::$manager))
			self::$manager = TokenManager::getInstance();

		return self::$manager;
	}

	/**
	 * Save new token for specified user and payment method.
	 *
	 * @param string $payment_method
	 * @param integer/object $buyer
	 * @param string $name
	 * @param string $token
	 * @param boolean $default
	 * @throws TokenExistsError
	 */
	public static function save($payment_method, $buyer, $name, $token, $default=false) {
		$manager = self::get_manager();

		// check if token with specified name already exists
		$item = $manager->getSingleItem(
				array('id'),
				array(
					'method'	=> $payment_method,
					'buyer'		=> is_object($buyer) ? $buyer->id : $buyer,
					'name'		=> $name
				));

		if (is_object($item))
			throw new TokenExistsError('Unable to save!');

		// prepare data
		$data = array(
				'method'	=> $payment_method,
				'buyer'		=> is_object($buyer) ? $buyer->id : $buyer,
				'name'		=> $name,
				'token'		=> $token
			);

		// insert new data
		$manager->insertData($data);

		// set as default
		if ($default)
			self::set_default($payment_method, $buyer, $name);
	}

	/**
	 * Get default token for specified payment method and buyer.
	 *
	 * @param string $payment_method
	 * @param integer/object $buyer
	 * @return object
	 */
	public static function get_default($payment_method, $buyer) {
		$result = null;
		$manager = self::get_manager();

		// get specified item
		$item = $manager->getSingleItem(
			$manager->getFieldNames(),
			array(
				'method'	=> $payment_method,
				'buyer'		=> is_object($buyer) ? $buyer->id : $buyer,
				'default'	=> 1
			));

		if (is_object($item))
			$result = $item;

		return $result;
	}

	/**
	 * Get list of all tokens for specified pament method and buyer.
	 *
	 * @param string $payment_method
	 * @param integer/object $buyer
	 * @return array
	 */
	public static function get_list($payment_method, $buyer) {
		$result = array();
		$manager = self::get_manager();

		// get items
		$items = $manager->getItems(
			$manager->getFieldNames(),
			array(
				'method'	=> $payment_method,
				'buyer'		=> is_object($buyer) ? $buyer->id : $buyer
			));

		if (count($items) > 0)
			foreach ($items as $item)
				$result[] = $item;

		return $result;
	}

	/**
	 * Set default token for specified payment method and buyer.
	 *
	 * @param string $payment_method
	 * @param integer/object $buyer
	 * @param string $name
	 * @throws UnknownTokenError
	 */
	public static function set_default($payment_method, $buyer, $name) {
		$manager = self::get_manager();

		// get token that will be set as default
		$new_default = $manager->getSingleItem(
			array('id'),
			array(
				'method'	=> $payment_method,
				'buyer'		=> is_object($buyer) ? $buyer->id : $buyer,
				'name'		=> $name
			));

		// make sure it's valid
		if (!is_object($new_default))
			throw new UnknownTokenError('Unable to set default token!');

		// clear all tokens for specifed method and buyer
		$manager->updateData(
			array(
				'default' => 0
			),
			array(
				'method'	=> $payment_method,
				'buyer'		=> is_object($buyer) ? $buyer->id : $buyer
			));

		// set specified token as default
		$manager->updateData(array('default' => 1), array('id' => $new_default->id));
	}

	/**
	 * Remove token for specified buyer and payment method.
	 *
	 * @param string $payment_method
	 * @param integer/object $buyer
	 * @param string $name
	 * @throws UnknownTokenError
	 */
	public static function delete($payment_method, $buyer, $name) {
		$manager = self::get_manager();

		// find id for specified token
		$token = $manager->getSingleItem(
			array('id'),
			array(
				'method'	=> $payment_method,
				'buyer'		=> is_object($buyer) ? $buyer->id : $buyer,
				'name'		=> $name
			));

		// make sure token exists
		if (!is_object($token))
			throw new UnknownTokenError('Unable to remove token!');

		// remove token
		self::delete_by_id($token->id);
	}

	/**
	 * Delete token with specified id.
	 *
	 * @param integer $id
	 */
	public static function delete_by_id($id) {
		$manager = self::get_manager();
		$manager->deleteData(array('id' => $id));
	}
}

?>
