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
	 * If recurring payments are supported by this payment method.
	 * @return boolean
	 */
	public function supports_recurring() {
		return true;
	}

	/**
	 * If delayed payments are supported by this payment method.
	 * @return boolean
	 */
	public function supports_delayed() {
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
	 * Get display name of payment method
	 * @return string
	 */
	public function get_title() {
		$this->parent->getLanguageConstant('payment_method_title');
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
		return url_GetFromFilePath($this->parent->path.'images/image.png');
	}

	/**
	 * Get list of plans for recurring payments.
	 *
	 * Plan groups, if not empty, are used to group plans. When creating
	 * new recurring payment all plans from the same group will be canceled.
	 *
	 * $result = array(
	 * 			'text_id' => array(
	 * 				'name'				=> array(),
	 * 				'trial'				=> RecurringPayment::DAY,
	 * 				'trial_count'		=> 7,
	 * 				'interval'			=> RecurringPayment::MONTH,
	 * 				'interval_count'	=> 1,
	 * 				'price'				=> 13,
	 * 				'setup_price'		=> 0,
	 * 				'start_time'		=> time(),
	 * 				'end_time'			=> time() + (365 * 24 * 60 * 60),
	 * 				'group'				=> ''
	 * 			)
	 * 		);
	 *
	 * @return array
	 */
	public function get_recurring_plans() {
		$result = array();
		$language_list = Language::getLanguages(false);
		$manager = Stripe_PlansManager::getInstance();

		// get recurring payment plans from database
		$items = $manager->getItems($manager->getFieldNames(), array());

		// prepare result
		if (count($items) > 0)
			foreach ($items as $item) {
				$name = array();
				foreach ($language_list as $language)
					$name[$language] = $item->name;

				$plan = array(
						'name'				=> $name,
						'trial'				=> RecurringPayment::DAY,
						'trial_count'		=> $item->trial_days,
						'interval'			=> $item->interval,
						'interval_count'	=> $item->interval_count,
						'price'				=> $item->price,
						'setup_price'		=> 0,
						'start_time'		=> time(),
						'end_time'			=> null,
						'group'				=> ''
					);

				$result[$item->text_id] = $plan;
			}

		return $result;
	}

	/**
	 * Get billing information from payment method.
	 *
	 * $result = array(
	 * 			'first_name'	=> '',
	 * 			'last_name'		=> '',
	 * 			'email'			=> ''
	 * 		);
	 *
	 * @return array
	 */
	public function get_information() {
		return array();
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
		$shop = shop::getInstance();
		$currency = $shop->getDefaultCurrency();

		// charge url
		$this->url = url_Make('charge', $this->parent->name);

		// prepare params
		$params = array(
				'transaction'	=> $transaction_data['uid'],
				'return_url'	=> $return_url,
				'name'			=> $billing_information['billing_full_name'],
				'credit_card'	=> $billing_information['billing_credit_card'],
				'exp_month'		=> $billing_information['billing_expire_month'],
				'exp_year'		=> $billing_information['billing_expire_year'],
				'cvv'			=> $billing_information['billing_cvv']
			);

		// prepare elements
		$elements = array();
		foreach ($params as $key => $value)
			$elements[] = '<input type="hidden" name="'.$key.'" value="'.$value.'">';

		return implode('', $elements);
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
		$shop = shop::getInstance();
		$currency = $shop->getDefaultCurrency();

		// charge url
		$this->url = url_Make('subscribe', $this->parent->name);

		// get customer
		$customer = stripe_payment::getCustomer($transaction_data['uid']);

		// prepare params
		if (is_null($customer)) {
			$params = array(
					'transaction'	=> $transaction_data['uid'],
					'return_url'	=> $return_url,
					'name'			=> $billing_information['billing_full_name'],
					'credit_card'	=> $billing_information['billing_credit_card'],
					'exp_month'		=> $billing_information['billing_expire_month'],
					'exp_year'		=> $billing_information['billing_expire_year'],
					'cvv'			=> $billing_information['billing_cvv'],
					'plan_name'		=> $plan_name
				);

		} else {
			$params = array(
					'transaction'	=> $transaction_data['uid'],
					'return_url'	=> $return_url,
					'customer'		=> $customer->text_id,
					'plan_name'		=> $plan_name
				);
		}


		// prepare elements
		$elements = array();
		foreach ($params as $key => $value)
			$elements[] = '<input type="hidden" name="'.$key.'" value="'.$value.'">';

		return implode('', $elements);
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
	}
}

?>
