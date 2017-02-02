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
use Modules\Shop\Transaction\PromotionManager as PromotionManager;


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
			self::$manager = TransactionsManager::get_instance();

		return self::$manager;
	}

	/**
	 * Get transaction object based on specified unique id.
	 *
	 * @param mixed $transaction_id
	 * @return object
	 * @throws UnknownTransactionError
	 */
	public static function get($transaction_id) {
		$manager = self::get_manager();

		// prepare conditions
		$conditions = array();
		if (is_numeric($transaction_id)) {
			$conditions['id'] = $transaction_id; else
			$conditions['uid'] = $transaction_id;

		// get transaction
		$transaction = $manager->get_single_item($manager->get_field_names(), $conditions);

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
		$result = $manager->get_single_item($manager->get_field_names(), array('uid' => $id));

		return $result;
	}

	/**
	 * Get buyer for specified transaction.
	 *
	 * @param object $transaction
	 * @return object
	 */
	public static function get_buyer($transaction) {
		$result = null;

		// get address
		$manager = BuyersManager::get_instance();
		$buyer = $manager->get_single_item(
				$manager->get_field_names(),
				array('id' => $transaction->buyer)
			);

		if (is_object($buyer))
			$result = $buyer;

		return $result;
	}

	/**
	 * Get buyer associated with currently active transaction.
	 *
	 * @return object
	 */
	public static function get_current_buyer() {
		$transaction = self::get_current();

		// make sure transaction is set
		if (is_null($transaction))
			return $result;

		// get buyer
		return self::get_buyer($transaction);
	}

	/**
	 * Get address for specified transaction.
	 *
	 * @param object $transaction
	 * @return object
	 */
	public static function get_address($transaction) {
		$result = null;

		// get address
		$manager = DeliveryAddressManager::get_instance();
		$address = $manager->get_single_item(
				$manager->get_field_names(),
				array('id' => $transaction->address)
			);

		if (is_object($address))
			$result = $address;

		return $result;
	}

	/**
	 * Get address associated with currently active transaction.
	 *
	 * @return object
	 */
	public static function get_current_address() {
		$transaction = self::get_current();

		// make sure transaction is set
		if (is_null($transaction))
			return $result;

		return self::get_address($transaction);
	}

	/**
	 * Get promotions for specified transaction. If no transaction is
	 * specified system will try to return promotions for current transaction.
	 *
	 * @param object $transaction
	 * @return array
	 */
	public static function get_promotions($transaction=null) {
		$result = array();

		// make sure we have transaction to work with
		if ($transaction === null)
			$transaction = self::get_current();

		if (is_null($transaction))
			return $result;

		// get promotions
		$manager = PromotionManager::get_instance();
		$promotions = $manager->get_items(
				$manager->get_field_names(),
				array('transaction' => $transaction->id)
			);

		if (count($promotions) == 0)
			return $result;

		// prepare result
		foreach ($promotions as $promotion)
			$result []= array(
					'promotion' => $promotion->promotion,
					'discount' => $promotion->discount
				);

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
		$manager->update_items(
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
		$manager->update_items(
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
		$manager->update_items($data, array('id' => $transaction->id));
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
		$manager->update_items(
				array('shipping' => $value),
				array('id' => $transaction->id)
			);
	}
}

?>
