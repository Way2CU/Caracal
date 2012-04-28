<?php


class Tranzilla_PaymentMethod extends PaymentMethod {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		global $section;

		parent::__construct($parent);
		
		// register payment method
		$this->name = 'tranzilla';
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
	 * Return URL for checkout form
	 * @return string
	 */
	public function get_url() {
		return $this->url;
	}

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
	}
	
	/**
	 * Handle verification received from payment gateway
	 * and return boolean denoting success of complete payment.
	 * 
	 * @return boolean
	 */
	public function verify_payment() {
	}
	
	/**
	 * Get items from data
	 * 
	 * @return array
	 */
	public function get_items() {
		$result = array();
		$item_count = fix_id($_POST['num_cart_items']);

		trigger_error(print_r($item_count, true));

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
