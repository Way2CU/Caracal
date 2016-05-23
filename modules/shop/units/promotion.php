<?php

namespace Modules\Shop\Promotion;

/**
 * Promotion base class.
 */
abstract class Promotion {
	protected $name;

	/**
	 * Get promotion unique name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get multi-language name for the promotion. This name
	 * is used for showing user applied promotion instead of using
	 * unique strings.
	 *
	 * @return string
	 */
	abstract public function get_title();

	/**
	 * Try to apply promotion to specified transaction. Return value
	 * denotes if changes were made to transaction and/or items.
	 *
	 * @param object $transaction
	 * @return boolean
	 */
	abstract public function apply($transaction);

	/**
	 * Check if promotion is applied to specified transaction.
	 *
	 * @param object $transaction
	 * @return boolean
	 */
	abstract public function is_applied($transaction);
}

?>
