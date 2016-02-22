<?php

use Modules\Shop\Token;
use Modules\Shop\Transaction;
use Modules\Shop\UnknownTransactionError;
use Modules\Shop\UnknownTokenError;


class Tranzila_PaymentMethod extends PaymentMethod {
	private static $_instance;

	private $url = 'https://direct.tranzila.com/%terminal%/iframe.php';
	private $mobile_url = 'https://direct.tranzila.com/%terminal%/mobile.php';
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

	private $language_aliases = array(
		'he'	=> 'il'
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
		$url = defined('_DESKTOP') ? $this->url : $this->mobile_url;
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
		$currency = shop::getDefaultCurrency();

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
			'transaction_id' => $data['uid'],
			'nologo'		=> 1,
			'lang'			=> isset($this->language_aliases[$language]) ? $this->language_aliases[$language] : $language
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
		$currency = shop::getDefaultCurrency();

		if (array_key_exists($currency, $this->currency_aliases))
			$currency = $this->currency_aliases[$currency];

		$currency_code = -1;
		if (array_key_exists($currency, $this->currency))
			$currency_code = $this->currency[$currency];

		// prepare basic parameters
		$params = array(
			'currency'		=> $currency_code,
			'sum'			=> 1,
			'pdesc'			=> $description,
			'tranmode'		=> 'VK',
			'transaction_id' => $data['uid'],
			'hidesum'		=> 1,
			'nologo'		=> 1,
			'lang'			=> isset($this->language_aliases[$language]) ? $this->language_aliases[$language] : $language
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
		$terminal_password = $this->parent->settings['terminal_password'];

		// make sure transaction has token associated
		if ($transaction->payment_token == 0) {
			trigger_error('Invalid token. Can\'t charge transaction!', E_USER_NOTICE);
			return $result;
		}

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
			$currency_code = $this->currency[$currency->currency];

		// prepare parameters
		$params = array(
			'supplier'		=> $this->parent->settings['terminal2'],
			'sum'			=> $transaction->total + $transaction->shipping + $transaction->handling,
			'currency'		=> $currency_code,
			'TranzilaPW'	=> $terminal_password,
			'expdate'		=> $expiration_date,
			'tranmode'		=> 'A',
			'TranzilaTK'	=> $token->token
		);

		$query = http_build_query($params);
		$url = $this->token_url.'?'.$query;

		// get response from server
		$response_data = file_get_contents($url);

		if (!empty($response_data)) {
			$response = array();
			parse_str($response_data, $response);

			if (array_key_exists('Response', $response))
				$result = $response['Response'] == '000'; else
				$result = false;
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
		$id = escape_chars($_REQUEST['transaction_id']);
		$response = escape_chars($_REQUEST['Response']);
		$mode = escape_chars($_REQUEST['tranmode']);
		$shop = shop::getInstance();

		// get transaction
		try {
			$transaction = Transaction::get($id);

		} catch (UnknownTransactionError $error) {
			// redirect user to error page
			$return_url = url_Make('checkout_error', 'shop');
			header('Location: '.$return_url, true, 302);
			return;
		}

		// make sure response is good
		if ($response != '000') {
			// mark transaction as canceled
			$shop->setTransactionStatus($id, TransactionStatus::CANCELED);

			// redirect buyer
			$return_url = url_Make('checkout_error', 'shop');
			header('Location: '.$return_url, true, 302);
			return;
		}

		switch ($mode) {
			case 'VK':  // verified token
				$token = escape_chars($_REQUEST['TranzilaTK']);
				$token_name = '****-****-****-'.substr($token, -4);

				try {
					// try to get existing token
					$token = Token::get($this->name, $transaction->buyer, $token_name);

				} catch (UnknownTokenError $error) {
					if (isset($_REQUEST['expmonth']) && isset($_REQUEST['expyear'])) {
						$exp_month = is_numeric($_REQUEST['expmonth']) ? (int) $_REQUEST['expmonth'] : 1;
						$exp_year = is_numeric($_REQUEST['expyear']) ? 2000 + (int) $_REQUEST['expyear'] : 2000;

					} else if (isset($_REQUEST['expdate'])) {
						$exp_date = $_REQUEST['expdate'];
						$exp_month = is_numeric($exp_date) ? (int) substr($exp_date, 0, 2): 1;
						$exp_year = is_numeric($exp_date) ? 2000 + (int) substr($exp_date, -2) : 2000;

					} else {
						$exp_month = 1;
						$exp_year = 2000;
					}

					// save new token
					$token = Token::save(
						$this->name,
						$transaction->buyer,
						$token_name,
						$token,
						array($exp_month, $exp_year)
					);
				}

				// associate token with transaction
				Transaction::set_token($transaction, $token);
				$shop->setTransactionStatus($id, TransactionStatus::PENDING);
				break;

			case 'AK':  // regular charge
				// only regular payments require status change
				$shop->setTransactionStatus($id, TransactionStatus::COMPLETED);
				break;
		}

		// redirect browser
		$return_url = url_Make('checkout_completed', 'shop');
		header('Location: '.$return_url, true, 302);
	}

	/**
	 * Handle callback from Tranzila about canceled payment.
	 */
	public function handle_cancel_payment() {
		$id = escape_chars($_REQUEST['transaction_id']);
		$shop = shop::getInstance();

		// set transaction status
		$shop->setTransactionStatus($id, TransactionStatus::CANCELED);

		// redirect browser
		$return_url = url_Make('checkout_canceled', 'shop');
		header('Location: '.$return_url, true, 302);
	}
}

?>
