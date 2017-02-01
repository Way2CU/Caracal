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
	 * Implementations of this class should not try to access transaction
	 * as objects are used in places when transaction is not yet created.
	 * Instead `shop::getCartSummary` can provide some information
	 * found in transaction object.
	 *
	 * @return boolean
	 */
	abstract public function qualifies();

	/**
	 * Return discount associated with this promotion. This object
	 * will specify the amount being deduced from final.
	 *
	 * @return object
	 */
	abstract public function get_discount();
}

?>
