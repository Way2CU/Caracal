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
	 * Get list of plans for recurring payments.
	 *
	 * Plan groups, if not empty, are used to group plans. When creating
	 * new recurring payment all plans from the same group will be canceled.
	 *
	 * $result = array(
	 * 			'text_id' => array(
	 * 				'name'				=> array(),
	 * 				'trial'				=> RecurringPayment::DAY,
	 * 				'trial_count'		=> 7,
	 * 				'interval'			=> RecurringPayment::MONTH,
	 * 				'interval_count'	=> 1,
	 * 				'price'				=> 13,
	 * 				'setup_price'		=> 0,
	 * 				'start_time'		=> time(),
	 * 				'end_time'			=> time() + (365 * 24 * 60 * 60),
	 * 				'group'				=> ''
	 * 			)
	 * 		);
	 *
	 * @return array
	 */
	abstract public function get_recurring_plans();

	/**
	 * Get billing information from payment method.
	 *
	 * $result = array(
	 * 			'first_name'	=> '',
	 * 			'last_name'		=> '',
	 * 			'email'			=> ''
	 * 		);
	 *
	 * @return array
	 */
	abstract public function get_information();

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

	/**
	 * Make new recurring payment based on named plan.
	 *
	 * @param array $transaction_data
	 * @param array $billing_information
	 * @param string $plan_name
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	abstract public function new_recurring_payment($transaction_data, $billing_information, $plan_name, $return_url, $cancel_url);

	/**
	 * Cancel existing recurring payment.
	 *
	 * @param object $transaction
	 * @return boolean
	 */
	abstract public function cancel_recurring_payment($transaction);
}
