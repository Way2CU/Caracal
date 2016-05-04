<?php

/**
 * Transaction handling static class used for common operations regarding
 * transactions and transaction related items.
 *
 * Author: Mladen Mijatov
 */

namespace Modules\Shop;

use \ShopTransactionsManager as TransactionsManager;
use \ShopDeliveryAddressManager as DeliveryAddressManager;
use \ShopBuyersManager as BuyersManager;


class UnknownTransactionError extends \Exception {}


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
	 * Get transaction object based on specified unique id.
	 *
	 * @param string $transaction_id
	 * @return object
	 * @throws UnknownTransactionError
	 */
	public static function get($transaction_id) {
		$manager = self::get_manager();

		// get transaction
		$transaction = $manager->getSingleItem(
			$manager->getFieldNames(),
			array('uid' => $transaction_id)
		);

		// make sure transaction is valid
		if (!is_object($transaction))
			throw new UnknownTransactionError('Unable to get transaction!');

		return $transaction;
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
	 * Get buyer associated with currently active transaction.
	 *
	 * @return object
	 */
	public static function get_current_buyer() {
		$result = null;
		$transaction = self::get_current();

		// make sure transaction is set
		if (is_null($transaction))
			return $result;

		// get address
		$manager = BuyersManager::getInstance();
		$buyer = $manager->getSingleItem(
				$manager->getFieldNames(),
				array('id' => $transaction->buyer)
			);

		if (is_object($buyer))
			$result = $buyer;

		return $result;
	}

	/**
	 * Get address associated with currently active transaction.
	 *
	 * @return object
	 */
	public static function get_current_address() {
		$result = null;
		$transaction = self::get_current();

		// make sure transaction is set
		if (is_null($transaction))
			return $result;

		// get address
		$manager = DeliveryAddressManager::getInstance();
		$address = $manager->getSingleItem(
				$manager->getFieldNames(),
				array('id' => $transaction->address)
			);

		if (is_object($address))
			$result = $address;

		return $result;
	}

	/**
	 * Set remote id for specified transaction.
	 *
	 * @param string $transaction_id
	 * @param string $remote_id
	 * @throws UnknownTransactionError
	 */
	public static function set_remote_id_by_uid($transaction_id, $remote_id) {
		try {
			// get transaction
			$transaction = self::get($transaction_id);

		} catch (UnknownTransactionError $error) {
			// throw new error
			throw new UnknownTransactionError('Unable to set remote id for transaction.');
		}

		// set remote id
		self::set_remote_id($transaction, $remote_id);
	}

	/**
	 * Set remote id for specified transaction.
	 *
	 * @param object $transaction
	 * @param string $remote_id
	 */
	public static function set_remote_id($transaction, $remote_id) {
		$manager = self::get_manager();
		$manager->updateData(
			array('remote_id' => $remote_id),
			array('id' => $transaction->id)
		);
	}

	/**
	 * Associate token with specified transaction.
	 *
	 * @param object $transaction
	 * @param object $token
	 */
	public static function set_token($transaction, $token) {
		$manager = self::get_manager();
		$manager->updateData(
			array('payment_token' => $token->id),
			array('id' => $transaction->id)
		);
	}

	/**
	 * Set transaction totals.
	 *
	 * @param object $transaction
	 * @param float $total
	 * @param float $handling
	 */
	public static function set_totals($transaction, $total=null, $shipping=null, $handling=null) {
		$manager = self::get_manager();

		// prepare data
		$data = array();

		if (!is_null($total))
			$data['total'] = $total;

		if (!is_null($shipping))
			$data['shipping'] = $shipping;

		if (!is_null($handling))
			$data['handling'] = $handling;

		// update data
		$manager->updateData($data, array('id' => $transaction->id));
	}

	/**
	 * Set shipping cost for specified transactions.
	 *
	 * @param object $transaction
	 * @param float $value
	 */
	public static function set_shipping($transaction, $value) {
		$manager = self::get_manager();

		// update data
		$manager->updateData(
				array('shipping' => $value),
				array('id' => $transaction->id)
			);
	}
}

?>
