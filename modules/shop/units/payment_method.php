<?php

/**
 * Shop payment method base class
 */

abstract class PaymentMethod {
	protected $name;
	protected $parent;	
	
	protected function __construct($parent) {
		$this->parent = $parent;
	}

	/**
	 * Register payment method with main shop module
	 */
	protected function registerPaymentMethod() {
		if (class_exists('shop')) {
			$shop = shop::getInstance();
			$shop->registerPaymentMethod($this->name, $this);
		}
	}

	/**
	 * Whether this payment method is able to provide user information
	 * @return boolean
	 */
	abstract public function provides_information();

	/**
	 * Get URL to be used in checkout form.
	 * @return string
	 */
	abstract public function get_url();

	/**
	 * Get name of payment method
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get display name of payment method
	 * @return string
	 */
	abstract public function get_title();

	/**
	 * Get icon URL for payment method
	 * @return string
	 */
	abstract public function get_icon_url();

	/**
	 * Get image URL for payment method
	 * @return string
	 */
	abstract public function get_image_url();
	
	/**
	 * Make new payment form with specified items and return
	 * boolean stating the success of initial payment process.
	 * 
	 * @param array $data
	 * @param array $items
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	abstract public function new_payment($data, $items, $return_url, $cancel_url);
	
	/**
	 * Verify origin of data and status of
	 * payment is complete.
	 * 
	 * @return boolean
	 */
	abstract public function verify_payment_complete();

	/**
	 * Verify origin of data and status of
	 * payment is canceled.
	 * 
	 * @return boolean
	 */
	abstract public function verify_payment_canceled();
	
	/**
	 * Get buyer information from data
	 * @return array
	 */
	abstract public function get_buyer_info();
	
	/**
	 * Extract custom field from parameters
	 * @return string
	 */
	abstract public function get_transaction_id();
}
