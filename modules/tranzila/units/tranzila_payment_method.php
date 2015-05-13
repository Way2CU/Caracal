<?php


class Tranzila_PaymentMethod extends PaymentMethod {
	private static $_instance;

	private $url = 'https://direct.tranzila.com/%terminal%';
	private $token_url = 'https://secure5.tranzila.com/cgi-bin/tranzila71u.cgi';

	private $currency = array(
		'ILS'	=> 1,
		'USD'	=> 2,
		'GBP'	=> 3,
		'HKD'	=> 5,
		'JPY'	=> 6,
		'EUR'	=> 7
	);

	private $currency_aliases = array(
		'₪'	=> 'ILS',
		'$'	=> 'USD',
		'£'	=> 'GBP',
		'¥'	=> 'JPY',
		'€'	=> 'EUR',
	);

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		global $section;

		parent::__construct($parent);

		// register payment method
		$this->name = 'tranzila';
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
	 * If recurring payments are supported by this payment method.
	 * @return boolean
	 */
	public function supports_recurring() {
		return false;
	}

	/**
	 * If delayed payments are supported by this payment method.
	 * @return boolean
	 */
	public function supports_delayed() {
		return true;
	}

	/**
	 * Return URL for checkout form
	 * @return string
	 */
	public function get_url() {
		$url = $this->url;
		$terminal = $this->parent->settings['terminal'];

		$url = str_replace('%terminal%', $terminal, $url);

		return $url;
	}

	/**
	 * Get display name of payment method
	 * @return string
	 */
	public function get_title() {
		return 'Tranzilla';
	}

	/**
	 * Get icon URL for payment method
	 * @return string
	 */
	public function get_icon_url() {
		return url_GetFromFilePath($this->parent->path.'images/icon.svg');
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
	 * @return array
	 */
	public function get_recurring_plans() {
		return array();
	}

	/**
	 * Get billing information from payment method.
	 *
	 * @return array
	 */
	public function get_information() {
		return array();
	}

	/**
	 * Make new payment form with specified items and return
	 * hidden elements for posting to URL.
	 *
	 * @param array $transaction_data
	 * @param array $billing_information
	 * @param array $items
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	public function new_payment($data, $billing_information, $items, $return_url, $cancel_url) {
		global $language;

		$description = '';
		$tmp_items = array_slice($items, 0, 5);
		$tmp_names = array();

		foreach($tmp_items as $item)
			$tmp_names[] = $item['name'][$language];

		$description = implode(', ', $tmp_names);

		// add dots if there are more than 5 items
		if (count($items) > 5)
			$description .= ', ...';

		// get proper currency code
		$shop_module = shop::getInstance();
		$currency = $shop_module->getDefaultCurrency();

		if (array_key_exists($currency, $this->currency_aliases))
			$currency = $this->currency_aliases[$currency];

		$currency_code = -1;
		if (array_key_exists($currency, $this->currency))
			$currency_code = $this->currency[$currency];

		// prepare basic parameters
		$params = array(
			'currency'		=> $currency_code,
			'sum'			=> $data['total'] + $data['shipping'] + $data['handling'],
			'cred_type'		=> 1,
			'pdesc'			=> $description,
			'tranmode'		=> 'AK',
			'myid'			=> $data['uid']
		);

		// create HTML form
		$result = '';

		foreach ($params as $key => $value)
			$result .= "<input type=\"hidden\" name=\"{$key}\" value=\"{$value}\">";

		return $result;
	}

	/**
	 * Make nwe delayed payment form with specified items and return
	 * hidden elements for posting to URL.
	 *
	 * @param array $transaction_data
	 * @param array $billing_information
	 * @param array $items
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	public function new_delayed_payment($data, $billing_information, $items, $return_url, $cancel_url) {
		global $language;

		$description = '';
		$tmp_items = array_slice($items, 0, 5);
		$tmp_names = array();

		foreach($tmp_items as $item)
			$tmp_names[] = $item['name'][$language];

		$description = implode(', ', $tmp_names);

		// add dots if there are more than 5 items
		if (count($items) > 5)
			$description .= ', ...';

		// get proper currency code
		$shop_module = shop::getInstance();
		$currency = $shop_module->getDefaultCurrency();

		if (array_key_exists($currency, $this->currency_aliases))
			$currency = $this->currency_aliases[$currency];

		$currency_code = -1;
		if (array_key_exists($currency, $this->currency))
			$currency_code = $this->currency[$currency];

		// prepare basic parameters
		$params = array(
			'currency'		=> $currency_code,
			'sum'			=> $data['total'] + $data['shipping'] + $data['handling'],
			'pdesc'			=> $description,
			'tranmode'		=> 'K',
			'myid'			=> $data['uid']
		);

		// create HTML form
		$result = '';

		foreach ($params as $key => $value)
			$result .= "<input type=\"hidden\" name=\"{$key}\" value=\"{$value}\">";

		return $result;
	}

	/**
	 * Make new recurring payment based on named plan and return
	 * hidden elements for posting to URL.
	 *
	 * @param array $transaction_data
	 * @param array $billing_information
	 * @param string $plan_name
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	public function new_recurring_payment($transaction_data, $billing_information, $plan_name, $return_url, $cancel_url) {
		return '';
	}

	/**
	 * Cancel existing recurring payment.
	 *
	 * @param object $transaction
	 * @return boolean
	 */
	public function cancel_recurring_payment($transaction) {
		return false;
	}

	/**
	 * Charge delayed transaction.
	 *
	 * @param object $transaction
	 * @return boolean
	 */
	public function charge_transaction($transaction) {
		$result = false;

		// make sure transaction has token associated
		if ($transaction->payment_token == 0)
			return $result;

		// get token
		$token_manager = Modules\Shop\TokenManager::getInstance();
		$token = $token_manager->getSingleItem(
			$token_manager->getFieldNames(),
			array('id' => $transaction->payment_token)
		);

		// make sure token is valid
		if (!is_object($token))
			return $result;

		// prepare expiration date
		$expiration_month = (string) $token->expiration_month;
		$expiration_year = substr((string) $token->expiration_year, -2);
		$expiration_date = str_pad($expiration_month.$expiration_year, 4, '0', STR_PAD_LEFT);

		// prepare currency
		$currency_manager = ShopCurrenciesManager::getInstance();
		$currency = $currency_manager->getSingleItem(
			$currency_manager->getFieldNames(),
			array('id' => $transaction->currency)
		);

		if (!is_object($currency))
			return $result;

		$currency_code = -1;
		if (array_key_exists($currency->currency, $this->currency))
			$currency_code = $this->currency[$currency];

		// prepare parameters
		$params = array(
			'supplier'		=> 'balishuk',
			'sum'			=> $transaction->total + $transaction->shipping + $transaction->handling,
			'currency'		=> $currency_code,
			'TranzilaPW'	=> $terminal_password,
			'expdate'		=> $expiration_date,
			'tranmode'		=> 'A',
			'TranzilaTK'	=> $token->value
		);

		$query = http_build_query($params);
		$url = $this->token_url.'?'.$query;

		// get response from server
		$response_data = file_get_contents($url);

		if (!empty($response_data)) {
			$response = array();
			parse_str($response_data, $response);

			$result = $response['Response'] == '000';
		}

		// update transaction status
		$shop = shop::getInstance();

		if ($result)
			$shop->setTransactionStatus($transaction->uid, TransactionStatus::COMPLETED); else
				$shop->setTransactionStatus($transaction->uid, TransactionStatus::DENIED);

		return $result;
	}

	/**
	 * Handle callback from Tranzila about confirmed payment.
	 */
	public function handle_confirm_payment() {
		trigger_error(json_encode(array_keys($_REQUEST)), E_USER_NOTICE);
	}

	/**
	 * Handle callback from Tranzila about canceled payment.
	 */
	public function handle_cancel_payment() {
	}
}

?>
