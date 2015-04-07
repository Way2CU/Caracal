<?php

/**
 * PayPal Payment Method Integration
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */
use Core\Events;


class PayPal_Express extends PaymentMethod {
	private static $_instance;

	private $units = array(
		RecurringPayment::DAY	=> 'Day',
		RecurringPayment::WEEK	=> 'Week',
		RecurringPayment::MONTH	=> 'Month',
		RecurringPayment::YEAR	=> 'Year'
	);

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		global $section;

		parent::__construct($parent);

		// register payment method
		$this->name = 'paypal_express';
		$this->registerPaymentMethod();

		// connect signal handler
		Events::connect('shop', 'before-checkout', 'beforeCheckout', $this);
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
		return url_Make('express-checkout', 'paypal');
	}

	/**
	 * Get display name of payment method
	 * @return string
	 */
	public function get_title() {
		return $this->parent->getLanguageConstant('express_method_title');
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
		return url_GetFromFilePath($this->parent->path.'images/express_image.png');
	}

	/**
	 * Get list of plans for recurring payments.
	 * @return array
	 */
	public function get_recurring_plans() {
		$result = array();
		$conditions = array();
		$manager = PayPal_PlansManager::getInstance();

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// populate result array
		if (count($items) > 0)
			foreach($items as $item)
				$result[$item->text_id] = array(
					'name'				=> $item->name,
					'trial'				=> $item->trial,
					'trial_count'		=> $item->trial_count,
					'interval'			=> $item->interval,
					'interval_count'	=> $item->interval_count,
					'price'				=> $item->price,
					'setup_price'		=> $item->setup_price,
					'start_time'		=> strtotime($item->start_time),
					'end_time'			=> 0,
					'group'				=> $item->group_name
				);


		return $result;
	}

	/**
	 * Get buyer information.
	 * @return array
	 */
	public function get_information() {
		$result = array();

		return $result;
	}

	/**
	 * Make new payment form with specified items and return hidden fields for final
	 * checkout form. Receiving these values is PayPal module which charges specified token and payer.
	 *
	 * @param array $data
	 * @param array $billing_information
	 * @param array $items
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	public function new_payment($data, $billing_information, $items, $return_url, $cancel_url) {
		$result = '';

		$params = array(
				'token'			=> $_SESSION['paypal_token'],
				'payer_id'		=> fix_chars($_REQUEST['PayerID']),
				'return_url'	=> $return_url,
				'type'			=> 'regular'
			);

		foreach ($params as $name => $value)
			$result .= '<input type="hidden" name="'.$name.'" value="'.$value.'">';

		return $result;
	}

	/**
	 * This function prepares hidden fields for final checkout form for recurring plan.
	 * Receiving these values is PayPal module which theen charges specified token and payer.
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
					'token'			=> $_SESSION['paypal_token'],
					'payer_id'		=> fix_chars($_REQUEST['PayerID']),
					'plan_name'		=> $plan_name,
					'return_url'	=> $return_url,
					'type'			=> 'recurring'
				);

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

		// prepare parameters
		$params = array(
			'ACTION'	=> 'Cancel',
			'PROFILEID'	=> $transaction->token
		);

		// make the call
		$response = PayPal_Helper::callAPI(
							PayPal_Helper::METHOD_ManageRecurringPaymentsProfileStatus,
							$params
						);

		// handle response
		if ($response['ACK'] == 'Success' || $response['ACK'] == 'SuccessWithWarning') {
			$shop = shop::getInstance();
			$shop->setTransactionStatus($transaction->uid, TransactionStatus::CANCELED);

			$result = true;

		} else {
			// report error
			$error_code = urldecode($response['L_ERRORCODE0']);
			$error_long = urldecode($response['L_LONGMESSAGE0']);

			trigger_error("PayPal_Express: ({$error_code}) - {$error_long}", E_USER_ERROR);
		}

		return $result;
	}

	/**
	 * Before checking out redirect user.
	 *
	 * @param string $method
	 * @param string $return_url
	 * @param string $cancel_url
	 */
	public function beforeCheckout($method, $return_url, $cancel_url) {
		global $language, $section, $action;

		$shop = shop::getInstance();
		$result = false;
		$fields = array();
		$request_id = 0;
		$recurring_plan = isset($_SESSION['recurring_plan']) ? $_SESSION['recurring_plan'] : null;

		// only react in case right payment method is selected
		if ($method != $this->name)
			return $result;

		// add recurring payment plan
		if (!is_null($recurring_plan)) {
			$manager = PayPal_PlansManager::getInstance();
			$plan = $manager->getSingleItem($manager->getFieldNames(), array('text_id' => $recurring_plan));
			$params = array(
				'price'			=> $plan->price,
				'period'		=> $plan->interval_count,
				'unit'			=> $plan->interval,
				'setup'			=> $plan->setup_price,
				'trial_period'	=> $plan->trial_count,
				'trial_unit'	=> $plan->trial
			);

			if (is_object($plan)) {
				// prepare fields for initial negotiation
				$fields["PAYMENTREQUEST_{$request_id}_AMT"] = $plan->price;
				$fields["PAYMENTREQUEST_{$request_id}_CURRENCYCODE"] = $shop->getDefaultCurrency();
				$fields["PAYMENTREQUEST_{$request_id}_DESC"] = $plan->name[$language];
				$fields["PAYMENTREQUEST_{$request_id}_INVNUM"] = $_SESSION['transaction']['uid'];
				$fields["PAYMENTREQUEST_{$request_id}_PAYMENTACTION"] = 'Authorization';
				$fields['L_BILLINGTYPE'.$request_id] = 'RecurringPayments';
				$fields['L_BILLINGAGREEMENTDESCRIPTION'.$request_id] = $shop->formatRecurring($params);
				$request_id++;

				// add one time payment
				if ($plan->setup_price > 0) {
					$fields["PAYMENTREQUEST_{$request_id}_AMT"] = $plan->setup_price;
					$fields["PAYMENTREQUEST_{$request_id}_CURRENCYCODE"] = $shop->getDefaultCurrency();
					$fields["PAYMENTREQUEST_{$request_id}_DESC"] = $this->parent->getLanguageConstant('api_setup_fee');
					$fields["PAYMENTREQUEST_{$request_id}_INVNUM"] = $_SESSION['transaction']['uid'];
					$fields["PAYMENTREQUEST_{$request_id}_PAYMENTACTION"] = 'Sale';
					$request_id++;
				}
			}
		}

		// add regular items
		if (isset($_SESSION['transaction'])) {
			$transaction = $_SESSION['transaction'];

			$fields["PAYMENTREQUEST_{$request_id}_AMT"] = $transaction['total'];
			$fields["PAYMENTREQUEST_{$request_id}_CURRENCYCODE"] = $shop->getDefaultCurrency();
			$fields["PAYMENTREQUEST_{$request_id}_INVNUM"] = $transaction['uid'];
			$fields["PAYMENTREQUEST_{$request_id}_PAYMENTACTION"] = 'Sale';
			$fields["PAYMENTREQUEST_{$request_id}_HANDLINGAMT"] = $transaction['handling'];
			$fields["PAYMENTREQUEST_{$request_id}_SHIPPINGAMT"] = $transaction['shipping'];
			$request_id++;
		}

		$return_url = url_Make(
							$action,
							$section,
							array('stage', 'return'),
							array('payment_method', $this->name)
						);

		// add regular fields
		$fields['NOSHIPPING'] = 1;
		$fields['REQCONFIRMSHIPPING'] = 0;
		$fields['ALLOWNOTE'] = 0;
		$fields['RETURNURL'] = $return_url;
		$fields['CANCELURL'] = $cancel_url;

		// generate name-value pair string for sending
		$response = PayPal_Helper::callAPI(PayPal_Helper::METHOD_SetExpressCheckout, $fields);

		if (isset($response['ACK']) && $response['ACK'] == 'Success' || $response['ACK'] == 'SuccessWithWarning') {
			$result = true;
			$token = $response['TOKEN'];

			// store token for later use
			$_SESSION['paypal_token'] = $token;

			// redirect to paypal site
			PayPal_Helper::redirect(PayPal_Helper::COMMAND_ExpressCheckout, $token);

		} else if (!is_null($response['ACK'])) {
			// report error
			$error_code = urldecode($response['L_ERRORCODE0']);
			$error_long = urldecode($response['L_LONGMESSAGE0']);

			trigger_error("PayPal_Express: ({$error_code}) - {$error_long}", E_USER_ERROR);
		}

		return $result;
	}

	/**
	 * Complete checkout and charge money.
	 */
	public function completeCheckout() {
		global $language;

		// prepare data for new recurring profile
		$shop = shop::getInstance();
		$token = escape_chars($_REQUEST['token']);
		$payer_id = escape_chars($_REQUEST['payer_id']);
		$return_url = fix_chars($_REQUEST['return_url']);
		$recurring = isset($_REQUEST['type']) && $_REQUEST['type'] == 'recurring';
		$transaction_uid = $_SESSION['transaction']['uid'];
		$request_id = 0;

		// get buyer information
		$fields = array(
			'TOKEN'		=> $token,
			'PAYERID'	=> $payer_id
		);
		$response = PayPal_Helper::callAPI(PayPal_Helper::METHOD_GetExpressCheckoutDetails, $fields);

		// update transaction status and buyer
		if ($response['ACK'] == 'Success' || $response['ACK'] == 'SuccessWithWarning') {
			$buyer = array(
					'first_name'	=> $response['FIRSTNAME'],
					'last_name'		=> $response['LASTNAME'],
					'email'			=> $response['EMAIL'],
					'uid'			=> $response['PAYERID']
				);

			$shop->updateBuyerInformation($transaction_uid, $buyer);

		} else {
			// report error
			$error_code = urldecode($response['L_ERRORCODE0']);
			$error_long = urldecode($response['L_LONGMESSAGE0']);

			trigger_error("PayPal_Express: ({$error_code}) - {$error_long}", E_USER_ERROR);
		}

		// create recurring profile
		if ($recurring) {
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
			if (is_object($plan) && $plan->setup_price > 0) {
				$setup_fields = $fields;
				$setup_fields["PAYMENTREQUEST_{$request_id}_AMT"] = $plan->setup_price;
				$setup_fields["PAYMENTREQUEST_{$request_id}_CURRENCYCODE"] = $shop->getDefaultCurrency();
				$setup_fields["PAYMENTREQUEST_{$request_id}_DESC"] = $this->parent->getLanguageConstant('api_setup_fee');
				$setup_fields["PAYMENTREQUEST_{$request_id}_INVNUM"] = $_SESSION['transaction']['uid'];
				$setup_fields["PAYMENTREQUEST_{$request_id}_PAYMENTACTION"] = 'Sale';

				$response = PayPal_Helper::callAPI(PayPal_Helper::METHOD_DoExpressCheckoutPayment, $setup_fields);
			}

			// create recurring payments profile
			$recurring_fields = $fields;

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

		} else {
			// charge regular
			$payment_fields = $fields;
			$transaction = $_SESSION['transaction'];

			$payment_fields["PAYMENTREQUEST_{$request_id}_AMT"] = $transaction['total'];
			$payment_fields["PAYMENTREQUEST_{$request_id}_CURRENCYCODE"] = $shop->getDefaultCurrency();
			$payment_fields["PAYMENTREQUEST_{$request_id}_INVNUM"] = $transaction['uid'];
			$payment_fields["PAYMENTREQUEST_{$request_id}_PAYMENTACTION"] = 'Sale';
			$payment_fields["PAYMENTREQUEST_{$request_id}_HANDLINGAMT"] = $transaction['handling'];
			$payment_fields["PAYMENTREQUEST_{$request_id}_SHIPPINGAMT"] = $transaction['shipping'];

			$response = PayPal_Helper::callAPI(PayPal_Helper::METHOD_DoExpressCheckoutPayment, $payment_fields);

			if ($response['ACK'] == 'Success' || $response['ACK'] == 'SuccessWithWarning') {
				trigger_error(json_encode($response));
				// update transaction
				$shop->setTransactionToken($transaction_uid, fix_chars($response['PAYMENTINFO_0_TRANSACTIONID']));
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
