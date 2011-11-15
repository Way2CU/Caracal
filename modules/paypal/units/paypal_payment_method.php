<?php


class PayPal_PaymentMethod extends PaymentMethod {
	private static $_instance;
	private static $url = 'https://www.paypal.com/cgi-bin/webscr';

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
	 * Test if payment method can handle data
	 * 
	 * @param mixed $data
	 * @return boolean
	 */
	public function can_handle($data) { 

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
	 * @return string
	 */
	public function new_payment($items, $currency) {
		$account = $this->parent->settings['account'];

		// prepare basic parameters
		$params = array(
				'cmd'			=> '_cart',
				'upload'		=> '1',
				'business'		=> $account,  // paypal merchant account email
				'currency_code'	=> $currency,
				'weight_unit'	=> 'kgs'
			);

		// prepare items for checkout
		for ($i = 1; $i <= count($items); $i++) {
			$item = $items[$i];

			$params["item_name_{$i}"] = $item['name'];
			$params["amount_{$i}"] = $item['price'];
			$params["quantity_{$i}"] = $item['quantity'];
			$params["tax_{$i}"] = $item['tax'];
			$params["weight_{$i}"] = $item['weight'];
		}

		// create HTML form


		return $result;
	}
	
	/**
	 * Handle verification received from payment gateway
	 * and return boolean denoting success of complete payment.
	 * 
	 * @param string $id
	 * @return boolean
	 */
	public function verify_payment($id) {
		define('_OMIT_STATS', 1);

		trigger_error(print_r($_REQUEST, true));
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
			$result = fgets($socket);

			if (strcmp($result, 'VERIFIED') && isset($_POST['txn_type'])) {
				// record payment
				$this->recordTransaction();

				// source data verified, now we can process them
				switch (strtolower($_POST['txn_type'])) {
					case 'subscr_payment':
						// subscription payment
						$custom = fix_chars($_REQUEST['custom']);
						$item_code = fix_chars($_REQUEST['item_number']);
						$manager = PayPal_SubscriptionManager::getInstance();

						$item = $manager->getSingleItem(
													array('id'),
													array(
														'custom'	=> $custom,
														'item_code'	=> $item_code
													));

						// prepare data for insertion
						$time = new DateTime();
						$time->modify('next month');

						$data = array(
								'custom'		=> $custom,
								'item_code'		=> $item_code,
								'valid_until'	=> $time->format('Y-m-d H:m:s')
							);

						if (is_object($item)) {
							// transaction already exists, we only need to update the time
							$manager->updateData($data, array('id' => $item->id));

						} else {
							// no transaction found, create new
							$manager->insertData($data);
						}
				}

			} else if (strcmp($result, 'INVALID')) {
				// data did not came from paypal.com

			}
		} else {

		}
	}
	
	/**
	 * Get items from data
	 * 
	 * @param mixed $data
	 * @return array
	 */
	public function get_items($data) {
		
	}
	
	/**
	 * Get buyer information from data
	 * 
	 * @param mixed $data
	 * @return array
	 */
	public function get_buyer_info($data) {
		
	}
	
	/**
	 * Get transaction information from data
	 * 
	 * @param mixed $data
	 * @return array
	 */
	public function get_transaction_info($data) {
		
	}
	
	/**
	 * Get payment infromation from data
	 * 
	 * @param mixed $data
	 * @return array
	 */
	public function get_payment_info($data) {
		
	}
	
}
