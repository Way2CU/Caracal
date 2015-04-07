<?php

/**
 * Transaction handling static class used for common operations regarding
 * transactions and transaction related items.
 *
 * Author: Mladen Mijatov
 */

namespace Shop;


final class Transaction {
	private static $manager = null;

	/**
	 * Get transaction manager.
	 *
	 * @return object
	 */
	public static function get_manager() {
		if (is_null(self::$manager))
			self::$manager = ShopTransactionsManager::getInstance();

		return self::$manager;
	}

	/**
	 * Get object for current transaction.
	 *
	 * @return object
	 */
	private static function get_current() {
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

}

?>
