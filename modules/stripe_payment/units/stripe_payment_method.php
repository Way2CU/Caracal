<?php

/**
 * Shop payment method base class
 */

class Stripe_PaymentMethod extends PaymentMethod {
	private static $_instance;

	protected $name;
	protected $parent;	
	
	protected function __construct($parent) {
		parent::__construct($parent);

		// register payment method
		$this->name = 'stripe';
		$this->registerPaymentMethod();
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance($parent) {
		if (!isset(self::$_instance))
			self::$_instance = new self($parent);

		return self::$_instance;
	}

	/**
	 * Whether this payment method is able to provide user information
	 * @return boolean
	 */
	public function provides_information() {
		return false;
	}

	/**
	 * Get URL to be used in checkout form.
	 * @return string
	 */
	public function get_url() {
		return '';
	}
	
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
	public function new_payment($data, $items, $return_url, $cancel_url) {
		return '';
	}
	
	/**
	 * Verify origin of data and status of
	 * payment is complete.
	 * 
	 * @return boolean
	 */
	public function verify_payment_complete() {
		return false;
	}

	/**
	 * Verify origin of data and status of
	 * payment is canceled.
	 * 
	 * @return boolean
	 */
	public function verify_payment_canceled() {
		return false;
	}
	
	/**
	 * Get buyer information from data
	 * @return array
	 */
	public function get_buyer_info() {
		return array();
	}
	
	/**
	 * Extract custom field from parameters
	 * @return string
	 */
	public function get_transaction_id() {
		return '';
	}
}
