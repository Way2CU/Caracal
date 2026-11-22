<?php

/**
 * Stripe Payment Method
 *
 * Payments are made on Stripe hosted checkout page. Checkout form posts
 * back to this module which creates checkout session and redirects buyer
 * to it. Outcome is reported through webhook and verified again when buyer
 * returns. Delayed payments are authorized with manual capture and captured
 * once transaction is marked as processed. Subscriptions use recurring
 * prices defined in Stripe.
 */

use Modules\Shop\Transaction;
use Modules\Shop\UnknownTransactionError;
use Modules\Shop\Item\Manager as ItemManager;
use Modules\Shop\Currency\Manager as CurrencyManager;
use Modules\Shop\Transaction\ItemManager as TransactionItemManager;
use Modules\Shop\Transaction\PlanManager as TransactionPlanManager;


class Stripe_PaymentMethod extends PaymentMethod {
	private static $_instance;

	const MAX_NAME_LENGTH = 100;

	private $currency_aliases = array(
			'₪' => 'ILS',
			'$' => 'USD',
			'£' => 'GBP',
			'¥' => 'JPY',
			'€' => 'EUR'
		);

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		parent::__construct($parent);

		// register payment method
		$this->name = 'stripe';
		$this->registerPaymentMethod();
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance($parent) {
		if (!isset(self::$_instance))
			self::$_instance = new self($parent);

		return self::$_instance;
	}

	/**
	 * Whether this payment method requires system to ask user for credit
	 * card information.
	 *
	 * @return boolean
	 */
	public function needs_credit_card_information() {
		return false;
	}

	/**
	 * If recurring payments are supported by this payment method.
	 *
	 * @return boolean
	 */
	public function supports_recurring() {
		return true;
	}

	/**
	 * If delayed payments are supported by this payment method.
	 *
	 * @return boolean
	 */
	public function supports_delayed() {
		return true;
	}

	/**
	 * Return URL for checkout form. Form is posted back to this module
	 * which then creates checkout session and redirects buyer to Stripe.
	 *
	 * @return string
	 */
	public function get_url() {
		return URL::make_query($this->parent->name, 'checkout-start');
	}

	/**
	 * Get display name of payment method
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->parent->get_language_constant('payment_method_title');
	}

	/**
	 * Get icon URL for payment method
	 *
	 * @return string
	 */
	public function get_icon_url() {
		return URL::from_file_path($this->parent->path.'images/icon.svg');
	}

	/**
	 * Get image URL for payment method
	 *
	 * @return string
	 */
	public function get_image_url() {
		return URL::from_file_path($this->parent->path.'images/image.png');
	}

	/**
	 * Get list of plans for recurring payments.
	 *
	 * @return array
	 */
	public function get_recurring_plans() {
		$result = array();
		$language_list = Language::get_languages(false);
		$manager = Stripe_PlansManager::get_instance();

		$items = $manager->get_items($manager->get_field_names(), array());

		if (count($items) > 0)
			foreach ($items as $item) {
				// stripe product names are not multi-language
				$name = array();
				foreach ($language_list as $language)
					$name[$language] = $item->name;

				$result[$item->text_id] = array(
						'name'           => $name,
						'trial'          => RecurringPayment::DAY,
						'trial_count'    => $item->trial_days,
						'interval'       => $item->interval,
						'interval_count' => $item->interval_count,
						'price'          => $item->price,
						'setup_price'    => 0,
						'start_time'     => time(),
						'end_time'       => 0,
						'group'          => ''
					);
			}

		return $result;
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
	 * @param array $data
	 * @param array $billing_information
	 * @param array $items
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	public function new_payment($data, $billing_information, $items, $return_url, $cancel_url) {
		return $this->make_checkout_fields($data);
	}

	/**
	 * Make new delayed payment form with specified items and return
	 * hidden elements for posting to URL.
	 *
	 * @param array $data
	 * @param array $billing_information
	 * @param array $items
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	public function new_delayed_payment($data, $billing_information, $items, $return_url, $cancel_url) {
		return $this->make_checkout_fields($data);
	}

	/**
	 * Make new recurring payment based on named plan and return
	 * hidden elements for posting to URL.
	 *
	 * @param array $data
	 * @param array $billing_information
	 * @param string $plan_name
	 * @param string $return_url
	 * @param string $cancel_url
	 * @return string
	 */
	public function new_recurring_payment($data, $billing_information, $plan_name, $return_url, $cancel_url) {
		return $this->make_checkout_fields($data, $plan_name);
	}

	/**
	 * Cancel existing recurring payment.
	 *
	 * @param object $transaction
	 * @return boolean
	 */
	public function cancel_recurring_payment($transaction) {
		$result = false;
		$shop = shop::get_instance();
		$manager = Stripe_TransactionManager::get_instance();
		$record = $this->get_record($transaction->uid);

		if (is_null($record) || empty($record->subscription)) {
			trigger_error('Stripe: No subscription associated with transaction. Unable to cancel!', E_USER_NOTICE);

		} else {
			try {
				Stripe_Api::call('DELETE', 'subscriptions/'.$record->subscription);
				$result = true;

			} catch (Stripe_ApiError $error) {
				trigger_error($error->getMessage(), E_USER_WARNING);
			}

			if ($result) {
				$manager->update_items(
						array('status' => Stripe_TransactionStatus::CANCELED),
						array('id' => $record->id)
					);
				$shop->setTransactionStatus($transaction->uid, TransactionStatus::CANCELED);
			}
		}

		return $result;
	}

	/**
	 * Charge delayed transaction by capturing authorized payment.
	 *
	 * @param object $transaction
	 * @return boolean
	 */
	public function charge_transaction($transaction) {
		$result = false;
		$shop = shop::get_instance();
		$manager = Stripe_TransactionManager::get_instance();
		$record = $this->get_record($transaction->uid);

		if (is_null($record) || empty($record->payment_intent)) {
			trigger_error('Stripe: Transaction was never authorized. Unable to charge!', E_USER_NOTICE);

		} else {
			$currency = $this->get_currency($transaction);
			$params = array(
					'amount_to_capture' => Stripe_Api::to_minor_units($this->get_total($transaction), $currency)
				);

			try {
				$intent = Stripe_Api::call('POST', 'payment_intents/'.$record->payment_intent.'/capture', $params);
				$result = isset($intent['status']) && $intent['status'] == 'succeeded';

			} catch (Stripe_ApiError $error) {
				trigger_error($error->getMessage(), E_USER_WARNING);
			}

			// update local record
			$manager->update_items(
					array('status' => $result ? Stripe_TransactionStatus::CAPTURED : Stripe_TransactionStatus::FAILED),
					array('id' => $record->id)
				);

			// update transaction status
			if ($result)
				$shop->setTransactionStatus($transaction->uid, TransactionStatus::COMPLETED); else
				$shop->setTransactionStatus($transaction->uid, TransactionStatus::DENIED);
		}

		return $result;
	}

	/**
	 * Create checkout session for transaction posted from checkout form
	 * and redirect buyer to Stripe.
	 */
	public function handle_start_checkout() {
		$manager = Stripe_TransactionManager::get_instance();
		$uid = isset($_REQUEST['transaction']) ? fix_chars($_REQUEST['transaction']) : '';
		$plan_name = isset($_REQUEST['plan']) ? fix_chars($_REQUEST['plan']) : '';
		$redirect_url = URL::make_query('shop', 'checkout-canceled', array('payment_method', $this->name));

		try {
			$transaction = Transaction::get($uid);
			$current = Transaction::get_current();

			// buyer can only pay for transaction they are currently working on
			if (is_null($current) || $current->id != $transaction->id)
				throw new UnknownTransactionError('Transaction does not belong to current session.');

			if ($transaction->status != TransactionStatus::PENDING)
				throw new UnknownTransactionError('Transaction is not pending payment.');

			// prepare session parameters based on transaction type
			switch ($transaction->type) {
				case TransactionType::SUBSCRIPTION:
					$params = $this->prepare_subscription_session($transaction, $plan_name);
					break;

				case TransactionType::DELAYED:
					$params = $this->prepare_payment_session($transaction, true);
					break;

				case TransactionType::REGULAR:
				default:
					$params = $this->prepare_payment_session($transaction, false);
					break;
			}

			// create checkout session
			$session = Stripe_Api::call('POST', 'checkout/sessions', $params, $transaction->uid.'-'.time());

			if (empty($session['id']) || empty($session['url']))
				throw new Stripe_ApiError('Stripe API: Checkout session data is missing from response.');

			if (!Stripe_Api::is_checkout_url($session['url']))
				throw new Stripe_ApiError('Stripe API: Refusing to redirect to unknown checkout page.');

			// store session so webhook events can be matched later
			$manager->insert_item(array(
					'transaction' => $transaction->uid,
					'session_id'  => fix_chars($session['id']),
					'status'      => Stripe_TransactionStatus::CREATED
				));

			$redirect_url = $session['url'];

		} catch (UnknownTransactionError $error) {
			trigger_error('Stripe: '.$error->getMessage(), E_USER_WARNING);

		} catch (Stripe_ApiError $error) {
			trigger_error($error->getMessage(), E_USER_WARNING);
		}

		header('Location: '.$redirect_url, true, 302);
	}

	/**
	 * Handle buyer returning from checkout page. Webhook is authoritative
	 * but session is checked through API in case event didn't arrive yet.
	 */
	public function handle_confirm_checkout() {
		$uid = isset($_REQUEST['transaction']) ? fix_chars($_REQUEST['transaction']) : '';
		$redirect_url = URL::make_query('shop', 'checkout-canceled', array('payment_method', $this->name));

		try {
			$transaction = Transaction::get($uid);
			$record = $this->get_record($transaction->uid);

			if (is_null($record))
				throw new UnknownTransactionError('No checkout session found for transaction.');

			// webhook might not have arrived yet
			if ($record->status == Stripe_TransactionStatus::CREATED) {
				try {
					$session = Stripe_Api::call('GET', 'checkout/sessions/'.$record->session_id);
					$this->accept_session($record, $transaction, $session);

				} catch (Stripe_ApiError $error) {
					trigger_error($error->getMessage(), E_USER_NOTICE);
				}

				$record = $this->get_record($transaction->uid);
			}

			// transaction status is changed outside of buyer's session so
			// we need to clean up here
			$accepted_statuses = array(
					Stripe_TransactionStatus::PAID,
					Stripe_TransactionStatus::AUTHORIZED,
					Stripe_TransactionStatus::SUBSCRIBED
				);

			if (in_array($record->status, $accepted_statuses))
				unset($_SESSION['transaction']);

			$redirect_url = URL::make_query('shop', 'checkout-completed', array('payment_method', $this->name));

		} catch (UnknownTransactionError $error) {
			trigger_error('Stripe: '.$error->getMessage(), E_USER_WARNING);
		}

		header('Location: '.$redirect_url, true, 302);
	}

	/**
	 * Handle buyer canceling payment on checkout page.
	 */
	public function handle_cancel_checkout() {
		$shop = shop::get_instance();
		$manager = Stripe_TransactionManager::get_instance();
		$uid = isset($_REQUEST['transaction']) ? fix_chars($_REQUEST['transaction']) : '';
		$redirect_url = URL::make_query('shop', 'checkout-canceled', array('payment_method', $this->name));

		try {
			$transaction = Transaction::get($uid);
			$current = Transaction::get_current();
			$record = $this->get_record($transaction->uid);

			// only buyer who started the payment can cancel it
			$owns_transaction = !is_null($current) && $current->id == $transaction->id;
			$can_cancel = $owns_transaction && !is_null($record) &&
					$record->status == Stripe_TransactionStatus::CREATED &&
					$transaction->status == TransactionStatus::PENDING;

			if ($can_cancel) {
				$manager->update_items(
						array('status' => Stripe_TransactionStatus::CANCELED),
						array('id' => $record->id)
					);
				$shop->setTransactionStatus($transaction->uid, TransactionStatus::CANCELED);

				// make sure session can't be completed later
				try {
					Stripe_Api::call('POST', 'checkout/sessions/'.$record->session_id.'/expire');

				} catch (Stripe_ApiError $error) {
					trigger_error($error->getMessage(), E_USER_NOTICE);
				}
			}

		} catch (UnknownTransactionError $error) {
			trigger_error('Stripe: '.$error->getMessage(), E_USER_NOTICE);
		}

		header('Location: '.$redirect_url, true, 302);
	}

	/**
	 * Handle webhook event sent by Stripe.
	 */
	public function handle_webhook() {
		$manager = Stripe_TransactionManager::get_instance();
		$secret = $this->parent->settings['webhook_secret'];
		$payload = file_get_contents('php://input');
		$header = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
		$response_code = 200;
		$response_text = 'OK';

		$event = json_decode($payload, true);
		$object = isset($event['data']['object']) && is_array($event['data']['object']) ? $event['data']['object'] : array();
		$type = isset($event['type']) ? $event['type'] : '';

		if (!Stripe_Api::verify_signature($payload, $header, $secret)) {
			trigger_error('Stripe: Webhook event with invalid signature received.', E_USER_WARNING);
			$response_code = 400;
			$response_text = 'INVALID SIGNATURE';

		} else if (!is_array($event) || count($object) == 0) {
			$response_code = 400;
			$response_text = 'INVALID PAYLOAD';

		} else {
			try {
				switch ($type) {
					case 'checkout.session.completed':
					case 'checkout.session.async_payment_succeeded':
						$record = $this->get_record_by('session_id', $object['id']);
						$transaction = Transaction::get($record->transaction);

						if (!$this->accept_session($record, $transaction, $object)) {
							$response_code = 409;
							$response_text = 'REJECTED';
						}
						break;

					case 'checkout.session.async_payment_failed':
						$record = $this->get_record_by('session_id', $object['id']);
						$this->fail_record($record, Stripe_TransactionStatus::FAILED, TransactionStatus::DENIED);
						break;

					case 'checkout.session.expired':
						$record = $this->get_record_by('session_id', $object['id']);
						if ($record->status == Stripe_TransactionStatus::CREATED)
							$manager->update_items(
									array('status' => Stripe_TransactionStatus::EXPIRED),
									array('id' => $record->id)
								);
						break;

					case 'invoice.paid':
					case 'invoice.payment_failed':
						$this->handle_invoice_event($type, $object);
						break;

					case 'customer.subscription.deleted':
						$record = $this->get_record_by('subscription', $object['id']);
						$this->add_recurring_payment($record, 0, RecurringPayment::CANCELED);
						$manager->update_items(
								array('status' => Stripe_TransactionStatus::CANCELED),
								array('id' => $record->id)
							);
						break;

					default:
						$response_text = 'IGNORED';
						break;
				}

			} catch (UnknownTransactionError $error) {
				// events for sessions we didn't create are not an error, just
				// acknowledge them so stripe stops retrying
				trigger_error('Stripe: '.$error->getMessage(), E_USER_NOTICE);
				$response_text = 'UNKNOWN';
			}
		}

		http_response_code($response_code);
		print $response_text;
	}

	/**
	 * Return absolute URL for specified module action.
	 *
	 * @param string $action
	 * @param string $transaction_uid
	 * @return string
	 */
	public function get_callback_url($action, $transaction_uid=null) {
		if (is_null($transaction_uid))
			$result = URL::make_query($this->parent->name, $action); else
			$result = URL::make_query($this->parent->name, $action, array('transaction', $transaction_uid));

		return $result;
	}

	/**
	 * Generate hidden fields for checkout form.
	 *
	 * @param array $data
	 * @param string $plan_name
	 * @return string
	 */
	private function make_checkout_fields($data, $plan_name=null) {
		$uid = escape_chars($data['uid']);
		$result = '<input type="hidden" name="transaction" value="'.$uid.'">';

		if (!is_null($plan_name))
			$result .= '<input type="hidden" name="plan" value="'.escape_chars($plan_name).'">';

		return $result;
	}

	/**
	 * Prepare parameters shared by all checkout sessions.
	 *
	 * @param object $transaction
	 * @return array
	 */
	private function prepare_common_params($transaction) {
		$buyer = Transaction::get_buyer($transaction);
		$customer_manager = Stripe_CustomerManager::get_instance();

		$params = array(
				'success_url'         => $this->get_callback_url('checkout-confirm', $transaction->uid),
				'cancel_url'          => $this->get_callback_url('checkout-cancel', $transaction->uid),
				'client_reference_id' => $transaction->uid,
				'metadata'            => array('transaction' => $transaction->uid)
			);

		if (is_object($buyer)) {
			// reuse existing customer when we have one
			$customer = $customer_manager->get_single_item(array('text_id'), array('buyer' => $buyer->id));

			if (is_object($customer))
				$params['customer'] = $customer->text_id; else if (!empty($buyer->email))
				$params['customer_email'] = $buyer->email;
		}

		return $params;
	}

	/**
	 * Prepare parameters for one time payment session.
	 *
	 * @param object $transaction
	 * @param boolean $delayed
	 * @return array
	 */
	private function prepare_payment_session($transaction, $delayed) {
		$currency = $this->get_currency($transaction);
		$items = $this->get_transaction_items($transaction);

		$params = $this->prepare_common_params($transaction);
		$params['mode'] = 'payment';
		$params['payment_intent_data'] = array(
				'metadata' => array('transaction' => $transaction->uid)
			);
		$params['line_items'] = array();

		// delayed payments are only authorized now and captured later
		if ($delayed)
			$params['payment_intent_data']['capture_method'] = 'manual';

		foreach ($items as $item)
			$params['line_items'][] = $this->make_line_item($item['name'], $item['price'], $item['count'], $currency);

		if ($transaction->shipping > 0)
			$params['line_items'][] = $this->make_line_item(
					$this->parent->get_language_constant('invoice_shipping'),
					$transaction->shipping, 1, $currency
				);

		if ($transaction->handling > 0)
			$params['line_items'][] = $this->make_line_item(
					$this->parent->get_language_constant('invoice_handling'),
					$transaction->handling, 1, $currency
				);

		// stripe refuses sessions without items
		if (count($params['line_items']) == 0)
			$params['line_items'][] = $this->make_line_item(
					$this->parent->get_language_constant('invoice_default_description'),
					$this->get_total($transaction), 1, $currency
				);

		return $params;
	}

	/**
	 * Prepare parameters for subscription session.
	 *
	 * @param object $transaction
	 * @param string $plan_name
	 * @return array
	 * @throws UnknownTransactionError
	 */
	private function prepare_subscription_session($transaction, $plan_name) {
		$plan_manager = Stripe_PlansManager::get_instance();
		$plan = $plan_manager->get_single_item($plan_manager->get_field_names(), array('text_id' => $plan_name));

		if (!is_object($plan))
			throw new UnknownTransactionError("Unknown recurring plan '{$plan_name}'.");

		$params = $this->prepare_common_params($transaction);
		$params['mode'] = 'subscription';
		$params['line_items'] = array(
				array(
					'price'    => $plan->text_id,
					'quantity' => 1
				)
			);
		$params['subscription_data'] = array(
				'metadata' => array('transaction' => $transaction->uid)
			);

		if ($plan->trial_days > 0)
			$params['subscription_data']['trial_period_days'] = $plan->trial_days;

		return $params;
	}

	/**
	 * Make line item definition with inline price.
	 *
	 * @param string $name
	 * @param float $price
	 * @param integer $quantity
	 * @param string $currency
	 * @return array
	 */
	private function make_line_item($name, $price, $quantity, $currency) {
		$name = trim(preg_replace('/\s+/', ' ', strip_tags($name)));

		if (mb_strlen($name, 'UTF-8') > self::MAX_NAME_LENGTH)
			$name = trim(mb_substr($name, 0, self::MAX_NAME_LENGTH, 'UTF-8'));

		if (empty($name))
			$name = $this->parent->get_language_constant('invoice_default_description');

		$result = array(
				'quantity'   => $quantity,
				'price_data' => array(
					'currency'     => strtolower($currency),
					'unit_amount'  => Stripe_Api::to_minor_units($price, $currency),
					'product_data' => array('name' => $name)
				)
			);

		return $result;
	}

	/**
	 * Get items associated with transaction along with their names.
	 *
	 * @param object $transaction
	 * @return array
	 */
	private function get_transaction_items($transaction) {
		global $language, $default_language;

		$result = array();
		$manager = TransactionItemManager::get_instance();
		$item_manager = ItemManager::get_instance();

		$items = $manager->get_items($manager->get_field_names(), array('transaction' => $transaction->id));

		if (count($items) > 0) {
			// collect item names
			$ids = array();
			foreach ($items as $item)
				$ids[] = $item->item;

			$names = array();
			$shop_items = $item_manager->get_items(array('id', 'name'), array('id' => $ids));

			foreach ($shop_items as $shop_item) {
				$name = $shop_item->name;

				if (is_array($name))
					$name = !empty($name[$language]) ? $name[$language] : $name[$default_language];

				$names[$shop_item->id] = $name;
			}

			// prepare result
			foreach ($items as $item)
				$result[] = array(
						'name'  => isset($names[$item->item]) ? $names[$item->item] : '',
						'price' => round($item->price * (1 + ($item->tax / 100)), 2),
						'count' => $item->amount
					);
		}

		return $result;
	}

	/**
	 * Accept completed checkout session and update transaction accordingly.
	 * Used both for webhook events and buyer returning from checkout page.
	 *
	 * @param object $record
	 * @param object $transaction
	 * @param array $session
	 * @return boolean
	 */
	private function accept_session($record, $transaction, $session) {
		$result = false;
		$shop = shop::get_instance();
		$manager = Stripe_TransactionManager::get_instance();
		$details = $this->extract_details($session);

		$accepted_statuses = array(
				Stripe_TransactionStatus::PAID,
				Stripe_TransactionStatus::AUTHORIZED,
				Stripe_TransactionStatus::CAPTURED,
				Stripe_TransactionStatus::SUBSCRIBED
			);

		$session_status = isset($session['status']) ? $session['status'] : '';
		$payment_status = isset($session['payment_status']) ? $session['payment_status'] : '';
		$mode = isset($session['mode']) ? $session['mode'] : '';

		if (in_array($record->status, $accepted_statuses)) {
			// repeated event, just fill in details we might be missing
			if (count($details) > 0)
				$manager->update_items($details, array('id' => $record->id));

			$result = true;

		} else if ($session_status != 'complete') {
			trigger_error("Stripe: Session for transaction {$transaction->uid} is not complete.", E_USER_NOTICE);

		} else if ($mode == 'subscription') {
			// subscription was created, first invoice is paid unless there's a trial
			$details['status'] = Stripe_TransactionStatus::SUBSCRIBED;
			$manager->update_items($details, array('id' => $record->id));

			if (isset($details['subscription']))
				Transaction::set_remote_id($transaction, $details['subscription']);

			$this->remember_customer($transaction, $details);
			$shop->setTransactionStatus($transaction->uid, TransactionStatus::COMPLETED);

			$status = $payment_status == 'paid' ? RecurringPayment::ACTIVE : RecurringPayment::PENDING;
			$this->add_recurring_payment($record, $this->get_total($transaction), $status);
			$result = true;

		} else if (!$this->amount_matches($transaction, $session)) {
			trigger_error("Stripe: Paid amount differs from expected for transaction {$transaction->uid}.", E_USER_WARNING);
			$manager->update_items(
					array('status' => Stripe_TransactionStatus::FAILED),
					array('id' => $record->id)
				);

		} else if ($payment_status == 'paid') {
			// regular payment, money was collected
			$details['status'] = Stripe_TransactionStatus::PAID;
			$manager->update_items($details, array('id' => $record->id));

			if (isset($details['payment_intent']))
				Transaction::set_remote_id($transaction, $details['payment_intent']);

			$this->remember_customer($transaction, $details);
			$shop->setTransactionStatus($transaction->uid, TransactionStatus::COMPLETED);
			$result = true;

		} else if ($transaction->type == TransactionType::DELAYED && $payment_status == 'unpaid') {
			// delayed payment, money is only authorized and transaction
			// stays pending until it's captured
			$details['status'] = Stripe_TransactionStatus::AUTHORIZED;
			$manager->update_items($details, array('id' => $record->id));

			if (isset($details['payment_intent']))
				Transaction::set_remote_id($transaction, $details['payment_intent']);

			$this->remember_customer($transaction, $details);
			$result = true;

		} else {
			// asynchronous payment method still processing, wait for event
			if (count($details) > 0)
				$manager->update_items($details, array('id' => $record->id));

			$result = true;
		}

		return $result;
	}

	/**
	 * Check if session total matches transaction total. Line item amounts
	 * are rounded individually so difference of one minor unit per item
	 * is tolerated.
	 *
	 * @param object $transaction
	 * @param array $session
	 * @return boolean
	 */
	private function amount_matches($transaction, $session) {
		$currency = $this->get_currency($transaction);
		$expected = Stripe_Api::to_minor_units($this->get_total($transaction), $currency);
		$received = isset($session['amount_total']) ? (int) $session['amount_total'] : 0;
		$session_currency = isset($session['currency']) ? strtolower($session['currency']) : '';

		$item_manager = TransactionItemManager::get_instance();
		$items = $item_manager->get_items(array('id'), array('transaction' => $transaction->id));
		$tolerance = count($items) + 2;

		$result = $session_currency == strtolower($currency) && abs($expected - $received) <= $tolerance;

		return $result;
	}

	/**
	 * Extract identifiers from session object.
	 *
	 * @param array $session
	 * @return array
	 */
	private function extract_details($session) {
		$result = array();
		$fields = array(
				'payment_intent' => 'payment_intent',
				'subscription'   => 'subscription',
				'customer'       => 'customer'
			);

		foreach ($fields as $name => $column) {
			if (!isset($session[$name]))
				continue;

			// expanded objects carry their id inside
			$value = is_array($session[$name]) && isset($session[$name]['id']) ? $session[$name]['id'] : $session[$name];

			if (is_string($value) && $value !== '')
				$result[$column] = fix_chars($value);
		}

		return $result;
	}

	/**
	 * Associate stripe customer with buyer for future sessions.
	 *
	 * @param object $transaction
	 * @param array $details
	 */
	private function remember_customer($transaction, $details) {
		if (!isset($details['customer']) || $transaction->buyer == 0)
			return;

		$manager = Stripe_CustomerManager::get_instance();
		$customer = $manager->get_single_item(array('id'), array('buyer' => $transaction->buyer));

		if (is_object($customer))
			$manager->update_items(
					array('text_id' => $details['customer']),
					array('id' => $customer->id)
				); else
			$manager->insert_item(array(
					'buyer'   => $transaction->buyer,
					'text_id' => $details['customer']
				));
	}

	/**
	 * Handle invoice events for subscriptions.
	 *
	 * @param string $type
	 * @param array $invoice
	 * @throws UnknownTransactionError
	 */
	private function handle_invoice_event($type, $invoice) {
		// subscription id location differs between api versions
		if (isset($invoice['subscription']))
			$subscription = $invoice['subscription']; else if (isset($invoice['parent']['subscription_details']['subscription']))
			$subscription = $invoice['parent']['subscription_details']['subscription']; else
			$subscription = '';

		if (is_array($subscription) && isset($subscription['id']))
			$subscription = $subscription['id'];

		if (empty($subscription))
			throw new UnknownTransactionError('Invoice event without subscription.');

		$record = $this->get_record_by('subscription', $subscription);
		$reason = isset($invoice['billing_reason']) ? $invoice['billing_reason'] : '';
		$currency = isset($invoice['currency']) ? $invoice['currency'] : 'usd';

		if ($type == 'invoice.paid') {
			// first invoice is recorded when session is accepted
			if ($reason != 'subscription_create') {
				$amount = Stripe_Api::from_minor_units(isset($invoice['amount_paid']) ? $invoice['amount_paid'] : 0, $currency);
				$this->add_recurring_payment($record, $amount, RecurringPayment::ACTIVE);
			}

		} else {
			$amount = Stripe_Api::from_minor_units(isset($invoice['amount_due']) ? $invoice['amount_due'] : 0, $currency);
			$this->add_recurring_payment($record, $amount, RecurringPayment::FAILED);
		}
	}

	/**
	 * Record recurring payment for plan associated with transaction.
	 *
	 * @param object $record
	 * @param float $amount
	 * @param integer $status
	 */
	private function add_recurring_payment($record, $amount, $status) {
		$shop = shop::get_instance();
		$plan_manager = TransactionPlanManager::get_instance();

		try {
			$transaction = Transaction::get($record->transaction);
			$plan = $plan_manager->get_single_item(array('id'), array('transaction' => $transaction->id));

			if (is_object($plan))
				$shop->addRecurringPayment($plan->id, $amount, $status); else
				trigger_error("Stripe: No plan associated with transaction {$transaction->uid}.", E_USER_WARNING);

		} catch (UnknownTransactionError $error) {
			trigger_error('Stripe: '.$error->getMessage(), E_USER_WARNING);
		}
	}

	/**
	 * Mark record and its transaction as failed.
	 *
	 * @param object $record
	 * @param integer $record_status
	 * @param integer $transaction_status
	 */
	private function fail_record($record, $record_status, $transaction_status) {
		$shop = shop::get_instance();
		$manager = Stripe_TransactionManager::get_instance();

		$manager->update_items(array('status' => $record_status), array('id' => $record->id));
		$shop->setTransactionStatus($record->transaction, $transaction_status);
	}

	/**
	 * Get most recent session record for transaction.
	 *
	 * @param string $transaction_uid
	 * @return object
	 */
	private function get_record($transaction_uid) {
		$manager = Stripe_TransactionManager::get_instance();
		$result = $manager->get_single_item(
				$manager->get_field_names(),
				array('transaction' => $transaction_uid),
				array('id'),
				false
			);

		return $result;
	}

	/**
	 * Get session record by one of stripe identifiers.
	 *
	 * @param string $column
	 * @param string $value
	 * @return object
	 * @throws UnknownTransactionError
	 */
	private function get_record_by($column, $value) {
		$manager = Stripe_TransactionManager::get_instance();
		$result = null;

		if (is_string($value) && $value !== '')
			$result = $manager->get_single_item($manager->get_field_names(), array($column => $value));

		if (!is_object($result))
			throw new UnknownTransactionError("No record with {$column} '{$value}'.");

		return $result;
	}

	/**
	 * Get ISO currency code for transaction.
	 *
	 * @param object $transaction
	 * @return string
	 */
	private function get_currency($transaction) {
		$manager = CurrencyManager::get_instance();
		$currency = $manager->get_single_item(array('currency'), array('id' => $transaction->currency));

		if (is_object($currency))
			$result = $currency->currency; else
			$result = shop::getDefaultCurrency();

		if (array_key_exists($result, $this->currency_aliases))
			$result = $this->currency_aliases[$result];

		return strtoupper($result);
	}

	/**
	 * Get amount to be charged for transaction.
	 *
	 * @param object $transaction
	 * @return float
	 */
	private function get_total($transaction) {
		return round($transaction->total + $transaction->shipping + $transaction->handling, 2);
	}
}

?>
