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
			$shop->registerPaymentMethod($this->name, &$this);
		}
	}
	
	/**
	 * Get URL to be used in checkout form.
	 * @return string
	 */
	abstract public function get_url();

	/**
	 * Get name of payment method
	 * @return string
	 */
	abstract public function get_name();

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
	 * Make new payment form with specified items and return
	 * boolean stating the success of initial payment process.
	 * 
	 * @param array $items
	 * @param string $currency
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	abstract public function new_payment($items, $currency, $return_url, $cancel_url);
	
	/**
	 * Handle verification received from payment gateway
	 * and return boolean denoting success of complete payment.
	 * 
	 * @param string $id	
	 * @param string $currency	
	 * @return boolean
	 */
	abstract public function verify_payment();
	
	/**
	 * Get items from data
	 * 
	 * @return array
	 */
	abstract public function get_items();
	
	/**
	 * Get buyer information from data
	 * 
	 * @return array
	 */
	abstract public function get_buyer_info();
	
	/**
	 * Get transaction information from data
	 * 
	 * @return array
	 */
	abstract public function get_transaction_info();
	
	/**
	 * Get payment infromation from data
	 * 
	 * @return array
	 */
	abstract public function get_payment_info();
}
