<?php

/**
 * PayPal Payment Method Integration
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */


class PayPal_PaymentMethod extends PaymentMethod {
	private static $_instance;

	private $url = 'https://www.paypal.com/cgi-bin/webscr';
	private $sandbox_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

	/**
	 * Transaction type
	 * @var array
	 */
	private $type = array(
				'cart'	=> TransactionType::SHOPPING_CART,
			);

	/**
	 * Transaction status
	 * @var array
	 */
	private $status = array(
				'Pending'	=> TransactionStatus::PENDING,
				'Completed'	=> TransactionStatus::COMPLETED,
				'Denied'	=> TransactionStatus::DENIED,
			);

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		global $section;

		parent::__construct($parent);
		
		// register payment method
		$this->name = 'paypal';
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
	 * Return URL for checkout form
	 * @return string
	 */
	public function get_url() {
		return $this->url;
	}

	/**
	 * Get account from parent
	 *
	 * @return string
	 */
	private function _getAccount() {
		if (array_key_exists('account', $this->parent->settings))
			$account = $this->parent->settings['account']; else
			$account = 'seller_1322054168_biz@gmail.com';

		return $account;
	}

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
	public function new_payment($items, $currency, $return_url, $cancel_url) {
		global $language;

		$account = $this->_getAccount();

		// prepare basic parameters
		$params = array(
				'cmd'			=> '_cart',
				'upload'		=> '1',
				'business'		=> $account,  // paypal merchant account email
				'currency_code'	=> $currency,
				'weight_unit'	=> 'kgs',
				'lc'			=> $language,
				'return'		=> $return_url,
				'cancel_return'	=> $cancel_url,
			);

		// prepare items for checkout
		$item_count = count($items);
		for ($i = 1; $i <= $item_count; $i++) {
			$item = array_shift($items);

			$params["item_name_{$i}"] = $item['name'][$language];
			$params["item_number_{$i}"] = $item['uid'];
			$params["item_description_{$i}"] = $item['description'];
			$params["amount_{$i}"] = $item['price'];
			$params["quantity_{$i}"] = $item['count'];
			$params["tax_{$i}"] = $item['price'] * ($item['tax'] / 100);
			$params["weight_{$i}"] = $item['weight'];
		}

		// create HTML form
		$result = '';

		foreach ($params as $key => $value)
			$result .= "<input type=\"hidden\" name=\"{$key}\" value=\"{$value}\">";

		return $result;
	}
	
	/**
	 * Handle verification received from payment gateway
	 * and return boolean denoting success of complete payment.
	 * 
	 * @return boolean
	 */
	public function verify_payment() {
		define('_OMIT_STATS', 1);

		$result = false;

		// prepare response data
		$strip = get_magic_quotes_gpc();
		$response = "cmd=_notify-validate";

		foreach ($_POST as $key => $value) {
			if ($strip)	$value = stripslashes($value);
			$value = urlencode($value);

			$response .= "&{$key}={$value}";
		}

		// validate with paypal.com this transaction
		$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . strlen($response) . "\r\n\r\n";
		$socket = fsockopen('ssl://www.paypal.com', 443, $error_number, $error_string, 30);

		if ($socket) {
			// send request
			fputs($socket, $header.$response);

			// get response from server
			$response = fgets($socket);

			// set result
			$result = ($_POST['receiver_email'] == $this->_getAccount()) && strcmp($response, 'VERIFIED');
		}

		return $result;
	}

	/**
	 * Verify origin of data and status of
	 * payment is complete.
	 * 
	 * @return boolean
	 */
	public function verify_payment_complete() {
	}

	/**
	 * Verify origin of data and status of
	 * payment is canceled.
	 * 
	 * @return boolean
	 */
	public function verify_payment_canceled() {
	}
	
	/**
	 * Get items from data
	 * 
	 * @return array
	 */
	public function get_items() {
		$result = array();
		$item_count = fix_id($_POST['num_cart_items']);

		for ($i = 1; $i < $item_count + 1; $i++) {
			$result[] = array(
					'uid'		=> fix_chars($_POST["item_number{$i}"]),
					'quantity'	=> fix_id($_POST["quantity{$i}"]),
					'price'		=> escape_chars($_POST["mc_gross_{$i}"]),
					'tax'		=> 0
				);
		}

		return $result;
	}
	
	/**
	 * Get buyer information from data
	 * 
	 * @return array
	 */
	public function get_buyer_info() {
		$address = array(
				'name'		=> fix_chars($_POST['address_name']),
				'street'	=> fix_chars($_POST['address_street']),
				'city'		=> fix_chars($_POST['address_city']),
				'zip'		=> fix_chars($_POST['address_zip']),
				'state'		=> fix_chars($_POST['address_state']),
				'country'	=> fix_chars($_POST['address_country']),
			);

		$result = array(
				'first_name'	=> fix_chars($_POST['first_name']),
				'last_name'		=> fix_chars($_POST['last_name']),
				'email'			=> fix_chars($_POST['payer_email']),
				'uid'			=> fix_chars($_POST['payer_id']),
				'address'		=> $address,
			);

		return $result;
	}
	
	/**
	 * Return transaction id.
	 *
	 * @return string
	 */
	public function get_transaction_id() {
		return fix_chars($_POST['txn_id']);
	}

	/**
	 * Get transaction information from data
	 * 
	 * @return array
	 */
	public function get_transaction_info() {
		$type = array_key_exists($_POST['txn_type'], $this->type) ? $this->type[$_POST['txn_type']] : TransactionType::SHOPPING_CART;
		$status = array_key_exists($_POST['payment_status'], $this->status) ? $this->status[$_POST['payment_status']] : TransactionStatus::DENIED;

		$result = array(
				'id'		=> fix_chars($_POST['txn_id']),
				'type'		=> $type,
				'status'	=> $status,
				'custom'	=> isset($_POST['custom']) ? fix_chars($_POST['custom']) : ''
			);
		
		return $result;
	}
	
	/**
	 * Get payment infromation from data
	 * 
	 * @return array
	 */
	public function get_payment_info() {
		$result = array(
				'tax'		=> escape_chars($_POST['tax']),
				'fee'		=> escape_chars($_POST['mc_fee']),
				'gross'		=> escape_chars($_POST['mc_gross']),
				'handling'	=> escape_chars($_POST['mc_handling']),
				'shipping'	=> escape_chars($_POST['mc_shipping']),
				'currency'	=> escape_chars($_POST['mc_currency'])
			);

		return $result;
	}
	
}
