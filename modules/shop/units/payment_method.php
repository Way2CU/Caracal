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
		$this->parent->registerPaymentMethod($this->name, &$this);
	}
	
	/**
	 * Test if payment method can handle data
	 * 
	 * @param mixed $data
	 * @return boolean
	 */
	abstract public function can_handle($data);
	
	/**
	 * Make new payment form with specified items and return
	 * boolean stating the success of initial payment process.
	 * 
	 * @param array $items
	 * @return string
	 */
	abstract public function new_payment($items, $currency);
	
	/**
	 * Handle verification received from payment gateway
	 * and return boolean denoting success of complete payment.
	 * 
	 * @param string $id	
	 * @param string $currency	
	 * @return boolean
	 */
	abstract public function verify_payment($id);
	
	/**
	 * Get items from data
	 * 
	 * @param mixed $data
	 * @return array
	 */
	abstract public function get_items($data);
	
	/**
	 * Get buyer information from data
	 * 
	 * @param mixed $data
	 * @return array
	 */
	abstract public function get_buyer_info($data);
	
	/**
	 * Get transaction information from data
	 * 
	 * @param mixed $data
	 * @return array
	 */
	abstract public function get_transaction_info($data);
	
	/**
	 * Get payment infromation from data
	 * 
	 * @param mixed $data
	 * @return array
	 */
	abstract public function get_payment_info($data);
}
