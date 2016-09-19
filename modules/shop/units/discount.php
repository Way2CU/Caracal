<?php

namespace Modules\Shop\Promotion;


/**
 * Discount base class.
 */
abstract class Discount {
	protected $name;

	/**
	 * Get discount unique name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get multi-language name for the discount. This name
	 * is used for showing user applied discount instead of using
	 * unique strings.
	 *
	 * @return string
	 */
	abstract public function get_title();

	/**
	 * Apply discount on specified trancation. Result is a list
	 * of discount items. These items do not reflect shop items in the
	 * cart. Instead they represent different deduction from the final
	 * price.
	 *
	 *	$result = array(
	 *		// item name, count, total amount discounted
	 *		array('Item name', 1, 15),
	 *	);
	 *
	 * @param object $transaction
	 * @return array
	 */
	abstract public function apply($transaction);
}

?>
