<?php


class Tranzila_PaymentMethod extends PaymentMethod {
	private static $_instance;

	private $url = 'https://direct.tranzila.com/%terminal%/';

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
	 * @param array $data
	 * @param array $items
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	public function new_payment($data, $items, $return_url, $cancel_url) {
		global $language;

		$description = $this->parent->getLanguageConstant('message_product_description');
		$description = str_replace('%count%', count($items), $description);
		$description = str_replace('%site%', MainLanguageHandler::getInstance()->getText('site_title'), $description);

		// prepare basic parameters
		$params = array(
				'currency'		=> $currency,
				'TranzilaToken'	=> $data['uid'],
				'sum'			=> $data['total'],
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
	 * Verify origin of data and status of
	 * payment is complete.
	 * 
	 * @return boolean
	 */
	public function verify_payment_complete() {
		$response_code = isset($_REQUEST['Response']) ? $_REQUEST['Response'] : null;
		return $response_code == '000';
	}

	/**
	 * Verify origin of data and status of
	 * payment is canceled.
	 * 
	 * @return boolean
	 */
	public function verify_payment_canceled() {
		$response_code = isset($_REQUEST['Response']) ? $_REQUEST['Response'] : null;
		return $response_code == '800';
	}
	
	/**
	 * Get buyer information from data
	 * 
	 * @return array
	 */
	public function get_buyer_info() {
	}
	
	/**
	 * Get transaction id from data
	 * @return string
	 */
	public function get_transaction_id() {
		$result = null;

		if (isset($_REQUEST['TranzilaToken']))
			$result = fix_chars($_REQUEST['TranzilaToken']);

		return $result;
	}
	
}
