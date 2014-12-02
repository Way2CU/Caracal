<?php


class Tranzila_PaymentMethod extends PaymentMethod {
	private static $_instance;

	private $url = 'https://direct.tranzila.com/%terminal%/';

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
		return false;
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
				'TranzilaToken'	=> $data['uid'],
				'sum'			=> $data['total'] + $data['shipping'] + $data['handling'],
				'cred_type'		=> 1,
				'pdesc'			=> $description
			);

		// prepare items for checkout
		foreach ($items as $item) {
			$item = array_shift($items);
		}

		// create HTML form
		$result = '';

		foreach ($params as $key => $value)
			$result .= "<input type=\"hidden\" name=\"{$key}\" value=\"{$value}\">";

		return $result;
	}

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
	public function new_recurring_payment($transaction_data, $billing_information, $plan_name, $return_url, $cancel_url) {
	}

	/**
	 * Cancel existing recurring payment.
	 *
	 * @param object $transaction
	 * @return boolean
	 */
	public function cancel_recurring_payment($transaction) {
	}
}
