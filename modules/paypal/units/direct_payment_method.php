<?php

/**
 * PayPal Direct Payment Method
 *
 * This method provides ability to pay with credit card
 * using PayPal as provider.
 *
 * Author: Mladen Mijatov
 */

class PayPal_Direct extends PaymentMethod {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		global $section;

		parent::__construct($parent);
		
		// register payment method
		$this->name = 'paypal_direct';
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
	 * If recurring payments are supported by this payment method.
	 * @return boolean
	 */
	public function supports_recurring() {
		return true;
	}

	/**
	 * Return URL for checkout form
	 * @return string
	 */
	public function get_url() {
		return '';
	}

	/**
	 * Get display name of payment method
	 * @return string
	 */
	public function get_title() {
		return $this->parent->getLanguageConstant('direct_method_title');
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
		return url_GetFromFilePath($this->parent->path.'images/direct_image.png');
	}

	/**
	 * Get list of plans for recurring payments.
	 * @return array
	 */
	public function get_recurring_plans() {
	}

	/**
	 * Make new payment form with specified items and return
	 * boolean stating the success of initial payment process.
	 * 
	 * @param array $data
	 * @param array $billing_information
	 * @param array $items
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	public function new_payment($data, $billing_information, $items, $return_url, $cancel_url) {
	}

	/**
	 * Make new recurring payment based on named plan.
	 *
	 * @param string $plan_name
	 * @param array $billing_information
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	public function new_recurring_payment($plan_name, $billing_information, $return_url, $cancel_url) {
	}
}

?>
