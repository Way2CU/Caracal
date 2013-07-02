<?php

/**
 * Shop payment method base class
 */

class Stripe_PaymentMethod extends PaymentMethod {
	private static $_instance;

	protected $name;
	protected $parent;	
	protected $url;
	
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
	 *
	 * Note: Stripe is a JavaScript based payment method. This means that
	 * form doesn't really need to point anywhere else other than return
	 * page since all the operations are done on client side.
	 *
	 * @return string
	 */
	public function get_url() {
		return $this->url;
	}
	
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
	public function new_payment($transaction_data, $billing_information, $items, $return_url, $cancel_url) {
		$this->url = $return_url;

		// load script template and populate it with real data
		$script_file = file_get_contents($this->parent->path.'/include/checkout.js');
		$script_file = str_replace(
							array(
								'%cc-number%',
								'%cc-cvv%',
								'%cc-exp-month%',
								'%cc-exp-year%',
								'%stripe-key%'
							),
							array(
								$billing_information['billing_credit_card'],
								$billing_information['billing_cvv'],
								$billing_information['billing_expire_month'],
								$billing_information['billing_expire_year'],
								$this->parent->getPublicKey()
							),
							$script_file
						);

		return '<script type="text/javascript">'.$script_file.'</script>';
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
