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
	 * Whether this payment method is able to provide user information.
	 * @return boolean
	 */
	abstract public function provides_information();

	/**
	 * If recurring payments are supported by this payment method.
	 * @return boolean
	 */
	abstract public function supports_recurring();

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
	public function get_title() {
		return $this->parent->getLanguageConstant('payment_method_title');
	}

	/**
	 * Get icon URL for payment method
	 * @return string
	 */
	public function get_icon_url() {
		return url_GetFromFilePath($this->parent->path.'images/icon.png');
	}

	/**
	 * Get image URL for payment method
	 * @return string
	 */
	public function get_image_url() {
		return url_GetFromFilePath($this->parent->path.'images/image.png');
	}

	/**
	 * Get list of plans for recurring payments.
	 *
	 * $result = array(
	 * 			array(
	 * 				'id'				=> 'plan_1',
	 * 				'name'				=> 'Plan 1',
	 * 				'trial_period'		=> 7,
	 * 				'trial_unit'		=> RecurringPayment::DAY,
	 * 				'interval'			=> RecurringPayment::MONTH,
	 * 				'interval_count'	=> 1,
	 * 				'price'				=> 13,
	 * 				'setup_price'		=> 0
	 * 			)
	 * 		);
	 *
	 * @return array
	 */
	abstract public function get_recurring_plans();
	
	/**
	 * Make new payment form with specified items and return
	 * boolean stating the success of initial payment process.
	 * 
	 * @param array $transaction_data
	 * @param array $billing_information
	 * @param array $items
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	abstract public function new_payment($transaction_data, $billing_information, $items, $return_url, $cancel_url);
}
