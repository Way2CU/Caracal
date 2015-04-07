<?php

/**
 * PayPal Direct Payment Method
 *
 * This method provides ability to pay with credit card
 * using PayPal as provider.
 *
 * Author: Mladen Mijatov
 */

class PayPal_Direct extends PaymentMethod {
	private static $_instance;

	private $units = array(
		RecurringPayment::DAY	=> 'Day',
		RecurringPayment::WEEK	=> 'Week',
		RecurringPayment::MONTH	=> 'Month',
		RecurringPayment::YEAR	=> 'Year'
	);

	private $card_type = array(
			CardType::VISA => 'Visa',
			CardType::MASTERCARD => 'MasterCard',
			CardType::DISCOVER => 'Discover',
			CardType::AMERICAN_EXPRESS => 'Amex',
			CardType::MAESTRO => 'Maestro'
		);

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		global $section;

		parent::__construct($parent);

		// register payment method
		$this->name = 'paypal_direct';
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
	 * Return URL for checkout form
	 * @return string
	 */
	public function get_url() {
		return url_Make('direct-checkout', 'paypal');
	}

	/**
	 * Get display name of payment method
	 * @return string
	 */
	public function get_title() {
		return $this->parent->getLanguageConstant('direct_method_title');
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
		return url_GetFromFilePath($this->parent->path.'images/direct_image.png');
	}

	/**
	 * Get list of plans for recurring payments.
	 * @return array
	 */
	public function get_recurring_plans() {
		$method = PayPal_Express::getInstance($this->parent);
		return $method->get_recurring_plans();
	}

	/**
	 * Get billing information.
	 * @return array
	 */
	public function get_information() {
		return array();
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
		return '';
	}

	/**
	 * Make new recurring payment based on named plan.
	 *
	 * @param array $data
	 * @param array $billing_information
	 * @param string $plan_name
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	public function new_recurring_payment($data, $billing_information, $plan_name, $return_url, $cancel_url) {
		$result = '';
		$manager = PayPal_PlansManager::getInstance();
		$plan = $manager->getSingleItem($manager->getFieldNames(), array('text_id' => $plan_name));

		if (is_object($plan)) {
			$params = array(
					'plan_name'		=> $plan_name,
					'return_url'	=> $return_url,
					'type'			=> 'recurring'
				);

			foreach ($billing_information as $key => $value)
				$params[$key] = $value;

			foreach ($params as $name => $value)
				$result .= '<input type="hidden" name="'.$name.'" value="'.$value.'">';
		}

		return $result;
	}

	/**
	 * Cancel existing recurring payment.
	 *
	 * @param object $transaction
	 * @return boolean
	 */
	public function cancel_recurring_payment($transaction) {
		$result = false;

		return $result;
	}

	/**
	 * Complete checkout and charge money.
	 */
	public function completeCheckout() {
		global $language;

		$shop = shop::getInstance();
		$return_url = fix_chars($_REQUEST['return_url']);
		$recurring = isset($_REQUEST['type']) && $_REQUEST['type'] == 'recurring';
		$transaction_uid = $_SESSION['transaction']['uid'];

		// get billing information
		$billing = array();
		$fields = array(
			'billing_full_name', 'billing_card_type', 'billing_credit_card', 'billing_expire_month',
			'billing_expire_year', 'billing_cvv'
		);

		foreach($fields as $field)
			if (isset($_REQUEST[$field]))
				$billing[$field] = fix_chars($_REQUEST[$field]);

		// create recurring profile
		if ($recurring) {
			$request_id = 0;
			$plan_name = $_SESSION['recurring_plan'];

			$manager = PayPal_PlansManager::getInstance();
			$plan = $manager->getSingleItem(
									$manager->getFieldNames(),
									array('text_id' => $plan_name)
								);
			$current_plan = $shop->getRecurringPlan();

			// cancel existing recurring payment if exists
			if (!is_null($current_plan)) {
				$plans = $this->get_recurring_plans();
				$current_group = null;

				// get plan data
				if (isset($plans[$current_plan->plan_name]))
					$current_group = $plans[$current_plan->plan_name]['group'];

				// cancel current plan
				if (!is_null($current_group) && $current_group == $plan->group_name)
					$shop->cancelTransaction($current_plan->transaction);
			}

			// generate params for description
			$plan_params = array(
				'price'			=> $plan->price,
				'period'		=> $plan->interval_count,
				'unit'			=> $plan->interval,
				'setup'			=> $plan->setup_price,
				'trial_period'	=> $plan->trial_count,
				'trial_unit'	=> $plan->trial
			);

			// charge one time setup fee
			// TODO: Charge one time setup fee.

			// create recurring payments profile
			$recurring_fields = $fields;

			// set buyer information
			$name = explode(' ', $billing['billing_full_name']);
			$recurring_fields['CREDITCARDTYPE'] = $this->card_type[$billing['billing_card_type']];
			$recurring_fields['ACCT'] = $billing['billing_credit_card'];
			$recurring_fields['EXPDATE'] = $billing['billing_expire_month'].$billing['billing_expire_year'];
			$recurring_fields['FIRSTNAME'] = $name[0];
			$recurring_fields['LASTNAME'] = $name[1];

			// set starting date of the profile
			$start_timestamp = strtotime($plan->start_time);
			if ($start_timestamp < time())
				$start_timestamp = time();

			$recurring_fields['PROFILESTARTDATE'] = strftime('%Y-%m-%dT%T%z', $start_timestamp);

			// set description
			$recurring_fields['DESC'] = $shop->formatRecurring($plan_params);

			// set currency
			$recurring_fields['AMT'] = $plan->price;
			$recurring_fields['CURRENCYCODE'] = $shop->getDefaultCurrency();

			// billing period
			$recurring_fields['BILLINGPERIOD'] = $this->units[$plan->interval];
			$recurring_fields['BILLINGFREQUENCY'] = $plan->interval_count;

			// trial period
			if ($plan->trial_count > 0) {
				$recurring_fields['TRIALBILLINGPERIOD'] = $this->units[$plan->trial];
				$recurring_fields['TRIALBILLINGFREQUENCY'] = $plan->trial_count;
				$recurring_fields['TRIALTOTALBILLINGCYCLES'] = 1;
			}

			// make api call
			$response = PayPal_Helper::callAPI(
								PayPal_Helper::METHOD_CreateRecurringPaymentsProfile,
								$recurring_fields
							);

			if ($response['ACK'] == 'Success' || $response['ACK'] == 'SuccessWithWarning') {
				// update transaction token
				$shop->setTransactionToken($transaction_uid, fix_chars($response['PROFILEID']));

				// update transaction status
				if ($response['PROFILESTATUS'] == 'ActiveProfile')
					$shop->setTransactionStatus($transaction_uid, TransactionStatus::COMPLETED);

			} else {
				// report error
				$error_code = urldecode($response['L_ERRORCODE0']);
				$error_long = urldecode($response['L_LONGMESSAGE0']);

				trigger_error("PayPal_Express: ({$error_code}) - {$error_long}", E_USER_ERROR);
			}

			// redirect user
			header('Location: '.$return_url, true, 302);
		}
	}
}

?>
