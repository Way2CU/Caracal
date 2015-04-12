<?php

/**
 * Transaction handling static class used for common operations regarding
 * transactions and transaction related items.
 *
 * Author: Mladen Mijatov
 */

namespace Shop;

use \ShopTransactionsManager as TransactionsManager;
use \ShopDeliveryAddressManager as DeliveryAddressManager;


final class Transaction {
	private static $manager = null;
	private static $address_manager = null;

	/**
	 * Get transaction manager.
	 *
	 * @return object
	 */
	public static function get_manager() {
		if (is_null(self::$manager))
			self::$manager = TransactionsManager::getInstance();

		return self::$manager;
	}

	/**
	 * Get delivery address manager.
	 *
	 * @return object
	 */
	public static function get_address_manager() {
		if (is_null(self::$address_manager))
			self::$address_manager = DeliveryAddressManager::getInstance();

		return self::$address_manager;
	}

	/**
	 * Get object for current transaction.
	 *
	 * @return object
	 */
	public static function get_current() {
		$id = null;
		$result = null;

		// get current transaction uid
		if (isset($_SESSION['transaction']) && isset($_SESSION['transaction']['uid']))
			$id = $_SESSION['transaction']['uid'];

		// make sure transaction is set
		if (is_null($id))
			return $result;

		$manager = self::get_manager();
		$result = $manager->getSingleItem($manager->getFieldNames(), array('uid' => $id));

		return $result;
	}

	/**
	 * Get address associated with currently active transaction.
	 *
	 * @return object
	 */
	public static function get_address() {
		$result = array();
		$transaction = self::get_current();

		// make sure transaction is set
		if (is_null($transaction))
			return $result;

		// get address
		$manager = self::get_address_manager();
		$address = $manager->getSingleItem($manager->getFieldNames(), array('id' => $transaction->address));

		if (is_object($address))
			$result = $address;

		return $result;
	}
}

?>
