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
	 * Apply discount on specified trancation.
	 *
	 * @param object $transaction
	 */
	abstract public function apply($transaction);

	/**
	 * Check if discount is applied on specified transaction
	 *
	 * @param object $transaction
	 * @return boolean
	 */
	abstract public function is_applied($transaction);
}

?>
