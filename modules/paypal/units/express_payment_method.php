<?php

/**
 * PayPal Payment Method Integration
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */


class PayPal_Express extends PaymentMethod {
	private static $_instance;

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
		shop::getInstance()->connectEvent('before-checkout', 'beforeCheckout', $this);
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
		return url_GetFromFilePath($this->parent->path.'images/icon.png');
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
					'start_time'		=> $item->start_time,
					'end_time'			=> 0
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
	}

	/**
	 * Make new recurring payment based on named plan.
	 *
	 * @param string $plan_name
	 * @param array $billing_information
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	public function new_recurring_payment($plan_name, $billing_information, $return_url, $cancel_url) {
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
	 * Before checking out redirect user.
	 *
	 * @param string $method
	 * @param string $return_url
	 * @param string $cancel_url
	 */
	public function beforeCheckout($method, $return_url, $cancel_url) {
		global $language, $section, $action;

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
			$shop = shop::getInstance();
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
				$fields["PAYMENTREQUEST_{$request_id}_INVNUM"] = '';  // transaction id
				$fields["PAYMENTREQUEST_{$request_id}_PAYMENTACTION"] = 'Authorization';
				$fields['L_BILLINGTYPE'.$request_id] = 'RecurringPayments';
				$fields['L_BILLINGAGREEMENTDESCRIPTION'.$request_id] = $shop->formatRecurring($params);

				// add one time payment
				if ($plan->setup_price > 0) {
					$request_id++;
					$fields["PAYMENTREQUEST_{$request_id}_AMT"] = $plan->setup_price;
					$fields["PAYMENTREQUEST_{$request_id}_CURRENCYCODE"] = $shop->getDefaultCurrency();
					$fields["PAYMENTREQUEST_{$request_id}_DESC"] = $this->parent->getLanguageConstant('api_setup_fee');
					$fields["PAYMENTREQUEST_{$request_id}_INVNUM"] = '';  // transaction id
					$fields["PAYMENTREQUEST_{$request_id}_PAYMENTACTION"] = 'Sale';
				}
			}
		}

		// TODO: Add other shop items.

		// store return URL in session
		$return_url = url_Make(
							$action,
							$section,
							array('stage', 'return'),
							array('method', $this->name)
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
		$token = escape_chars($_REQUEST['token']);
		$payer_id = escape_chars($_REQUEST['payer_id']);
		$return_url = fix_chars($_REQUEST['return_url']);
		$recurring = isset($_REQUEST['type']) && $_REQUEST['type'] == 'recurring';

		if ($recurring) {
			$fields = array('TOKEN' => $token);
			$response = PayPal_Helper::callAPI(PayPal_Helper::METHOD_GetExpressCheckoutDetails, $fields);
		}
	}
}
