<?php

/**
 * Handler class for payment method tokens. This class is used to manage and
 * provide easy access to payment method specific tokens.
 *
 * Author: Mladen Mijatov
 */

namespace Modules\Shop;


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
			self::$manager = TokenManager::get_instance();

		return self::$manager;
	}

	/**
	 * Get token for specified details.
	 *
	 * @param string $payment_method
	 * @param integer/object $buyer
	 * @param string $name
	 * @return object
	 * @throws UnknownTokenError
	 */
	public static function get($payment_method, $buyer, $name) {
		$manager = self::get_manager();

		// try to get item
		$result = $manager->get_single_item(
			$manager->get_field_names(),
			array(
				'payment_method'	=> $payment_method,
				'buyer'				=> is_object($buyer) ? $buyer->id : $buyer,
				'name'				=> $name
			));

		// make sure token is real
		if (!is_object($result))
			throw new UnknownTokenError('Unable to get specified token!');

		return $result;
	}

	/**
	 * Save new token for specified user and payment method.
	 *
	 * @param string $payment_method
	 * @param integer/object $buyer
	 * @param string $name
	 * @param string $token
	 * @param array $expires
	 * @param boolean $default
	 * @return object
	 * @throws TokenExistsError
	 */
	public static function save($payment_method, $buyer, $name, $token, $expires=null, $default=false) {
		$manager = self::get_manager();

		// check if token with specified name already exists
		try {
			$item = self::get($payment_method, $buyer, $name);
		} catch (UnknownTokenError $error) {
			$item = null;
		}

		// make sure token with specified name doesn't already exist
		if (!is_null($item))
			throw new TokenExistsError('Unable to save!');

		// prepare data
		$data = array(
				'payment_method'	=> $payment_method,
				'buyer'				=> is_object($buyer) ? $buyer->id : $buyer,
				'name'				=> $name,
				'token'				=> $token,
				'expires'			=> 0
			);

		if (!is_null($expires)) {
			$data['expires'] = 1;
			$data['expiration_month'] = $expires[0];
			$data['expiration_year'] = $expires[1];
		}

		// insert new data
		$manager->insert_item($data);
		$id = $manager->get_inserted_id();
		$result = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		// set as default
		if ($default)
			self::set_default($payment_method, $buyer, $name);

		return $result;
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
		$item = $manager->get_single_item(
			$manager->get_field_names(),
			array(
				'payment_method'	=> $payment_method,
				'buyer'				=> is_object($buyer) ? $buyer->id : $buyer,
				'default'			=> 1
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
		$items = $manager->get_items(
			$manager->get_field_names(),
			array(
				'payment_method'	=> $payment_method,
				'buyer'				=> is_object($buyer) ? $buyer->id : $buyer
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
		try {
			$new_default = self::get($payment_method, $buyer, $name);
		} catch (UnknownTokenError $error) {
			throw new UnknownTokenError('Unable to set default token!');
		}

		// clear all tokens for specifed method and buyer
		$manager->update_items(
			array(
				'default' => 0
			),
			array(
				'payment_method'	=> $payment_method,
				'buyer'				=> is_object($buyer) ? $buyer->id : $buyer
			));

		// set specified token as default
		$manager->update_items(array('default' => 1), array('id' => $new_default->id));
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
		try {
			$token = self::get($payment_method, $buyer, $name);
		} catch (UnknownTokenError $error) {
			throw new UnknownTokenError('Unable to remove token!');
		}

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
		$manager->delete_items(array('id' => $id));
	}

	/**
	 * Check if token has expired.
	 *
	 * @param object $token
	 * @return boolean
	 */
	public static function has_expired($token) {
		$expiration_time = mktime(0, 0, 0, $token->expiration_month + 1, 0, $token->expiration_year);
		$can_expire = $token->expires;
		$date_expired = time() > $expiration_time;

		return $can_expire && $date_expired;
	}
}

?>
