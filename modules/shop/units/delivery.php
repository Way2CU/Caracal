<?php

/**
 * Delivery method handler class. This class will manage and set delivery
 * method related properties.
 *
 * Author: Mladen Mijatov
 */

namespace Modules\Shop;

use \PackageType as PackageType;
use \UnitType as UnitType;
use \ShopItemManager as ItemManager;


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
					'name'             => $name,
					'title'            => $method->getTitle(),
					'icon'             => $method->getIcon(),
					'image'            => $method->getImage(),
					'small_image'      => $method->getSmallImage(),
					'custom_interface' => $method->hasCustomInterface(),
					'user_information' => $method->requiresUserInformation(),
					'international'    => $international
				);

				// add method data to result
				if (
					$type == self::ALL ||
					($international && $type == self::INTERNATIONAL) ||
					(!$international && $type == self::DOMESTIC)
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
				'delivery_type'		=> is_null($type) ? '' : $type
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

	/**
	 * Create array containing item specification to be used by delivery
	 * methods when estimating delivery costs.
	 *
	 * @return array
	 */
	public static function get_items_for_estimate() {
		$result = array();
		$uid_list = array();
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();

		// prepare result structure
		foreach ($cart as $uid => $item)
			if (count($item['variations']) > 0)
				foreach ($item['variations'] as $variation_id => $data) {
					// remember uid for later data retrieval
					if (!in_array($uid, $uid_list))
						$uid_list[] = $uid;

					// add partial result
					$result[] = array(
						'uid'			=> $uid,
						'package'		=> 1,
						'package_type'	=> PackageType::USER_PACKAGING,
						'properties'	=> array(),
						'width'			=> 0,
						'height'		=> 0,
						'length'		=> 0,
						'units'			=> UnitType::METRIC,
						'count'			=> $data['count'],
						'weight'		=> 0,
						'price'			=> 0
					);
				}

		// populate missing fields
		$manager = ItemManager::getInstance();
		$items = $manager->getItems($manager->getFieldNames(), array('uid' => $uid_list));
		$item_map = array();

		// cache item data
		if (count($items) > 0)
			foreach ($items as $item)
				$item_map[$item->uid] = $item;

		// update missing data
		foreach ($result as $item_data) {
			// get item unique id
			$uid = $item_data['uid'];
			unset($item_data['uid']);

			// get cached item data
			$db_data = $item_map[$uid];

			// update missing
			$item_data['weight'] = $db_data->weight;
			$item_data['price'] = $db_data->price;
		}

		return $result;
	}
}

?>
