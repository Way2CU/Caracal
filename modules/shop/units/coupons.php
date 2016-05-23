<?php

/**
 * Coupon support by extending promotion system.
 */

namespace Modules\Shop\Promotions;


class Coupons extends Promotion {
	/**
	 * Get multi-language name for the promotion. This name
	 * is used for showing user applied promotion instead of using
	 * unique strings.
	 *
	 * @return string
	 */
	public function get_title() {
		$result = null;

		return $result;
	}

	/**
	 * Try to apply promotion to specified transaction. Return value
	 * denotes if changes were made to transaction and/or items.
	 *
	 * @param object $transaction
	 * @return boolean
	 */
	public function apply($transaction) {
		$result = false;

		return $result;
	}

	/**
	 * Check if promotion is applied to specified transaction.
	 *
	 * @param object $transaction
	 * @return boolean
	 */
	public function is_applied($transaction) {
		$result = false;

		return $result;
	}
}

?>
