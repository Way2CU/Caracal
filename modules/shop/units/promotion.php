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
 	 * Check if specified transaction qualified for this promotion.
	 *
	 * @param object $transaction
	 * @return boolean
	 */
	abstract public function qualifies($transaction);

	/**
	 * Return discount associated with this promotion. This object
	 * will specify the amount being deduced from final.
	 *
	 * @return object
	 */
	abstract public function get_discount();
}

?>
