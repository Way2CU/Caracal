<?php

/**
 * Shop payment method base class
 */

class GoogleCheckout_PaymentMethod extends PaymentMethod {
	private static $_instance;

	private $url = "https://checkout.google.com/api/checkout/v2/merchantCheckout/Merchant/{0}";
	private $url_sandbox = "https://sandbox.google.com/checkout/api/checkout/v2/merchantCheckout/Merchant/{0}";

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		global $section;

		parent::__construct($parent);

		// register payment method
		$this->name = 'google_checkout';
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
		return true;
	}

	/**
	 * Get URL to be used in checkout form.
	 * @return string
	 */
	public function get_url() {
		return $this->url_sandbox;
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
	}
}
