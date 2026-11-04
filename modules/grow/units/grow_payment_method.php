<?php

/**
 * Grow Payment Method
 *
 * Payment is made on page hosted by Grow. Checkout form posts back to this
 * module which creates payment process through API and redirects buyer to
 * returned URL. Once payment is done Grow notifies server (notify URL) and
 * sends buyer back to confirmation URL. Delayed payments are authorized as
 * suspended charge (J4/J5) and settled once transaction is marked as processed.
 */

use Modules\Shop\Transaction;
use Modules\Shop\UnknownTransactionError;
use Modules\Shop\Item\Manager as ItemManager;
use Modules\Shop\Transaction\ItemManager as TransactionItemManager;


class Grow_PaymentMethod extends PaymentMethod {
	private static $_instance;

	const MAX_DESCRIPTION_LENGTH = 100;
	const MAX_PAYMENTS = 12;

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		parent::__construct($parent);

		// register payment method
		$this->name = 'grow';
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
		return false;
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
	 * which then creates payment process and redirects buyer to Grow.
	 *
	 * @return string
	 */
	public function get_url() {
		return URL::make_query($this->name, 'payment-start');
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
		return URL::from_file_path($this->parent->path.'images/image.svg');
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
	 * Charge delayed transaction by settling previously suspended charge.
	 *
	 * @param object $transaction
	 * @return boolean
	 */
	public function charge_transaction($transaction) {
		$result = false;
		$shop = shop::get_instance();
		$manager = Grow_TransactionManager::get_instance();
		$record = $this->get_record($transaction->uid);

		if (is_null($record) || empty($record->transaction_id) || empty($record->transaction_token)) {
			trigger_error('Grow: Transaction was never authorized. Unable to charge!', E_USER_NOTICE);

		} else {
			$params = array(
					'userId'           => $this->parent->settings['user_id'],
					'transactionId'    => $record->transaction_id,
					'transactionToken' => $record->transaction_token,
					'sum'              => $this->format_sum($this->get_total($transaction))
				);

			try {
				Grow_Api::call(Grow_Api::METHOD_SETTLE_SUSPENDED_TRANSACTION, $params);
				$result = true;

			} catch (Grow_ApiError $error) {
				trigger_error($error->getMessage(), E_USER_WARNING);
			}

			// update local record
			$manager->update_items(
					array('status' => $result ? Grow_TransactionStatus::SETTLED : Grow_TransactionStatus::FAILED),
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
	 * Create payment process for transaction posted from checkout form and
	 * redirect buyer to payment page.
	 */
	public function handle_start_payment() {
		$manager = Grow_TransactionManager::get_instance();
		$uid = isset($_REQUEST['transaction']) ? fix_chars($_REQUEST['transaction']) : '';
		$redirect_url = URL::make_query('shop', 'checkout-canceled', array('payment_method', $this->name));

		try {
			$transaction = Transaction::get($uid);
			$current = Transaction::get_current();

			// buyer can only pay for transaction they are currently working on
			if (is_null($current) || $current->id != $transaction->id)
				throw new UnknownTransactionError('Transaction does not belong to current session.');

			if ($transaction->status != TransactionStatus::PENDING)
				throw new UnknownTransactionError('Transaction is not pending payment.');

			// delayed transactions are only authorized at this point
			if ($transaction->type == TransactionType::DELAYED)
				$charge_type = Grow_Api::CHARGE_SUSPENDED; else
				$charge_type = Grow_Api::CHARGE_REGULAR;

			// create payment process
			$params = $this->prepare_process_params($transaction, $charge_type);
			$data = Grow_Api::call(Grow_Api::METHOD_CREATE_PAYMENT_PROCESS, $params);

			if (empty($data['url']) || empty($data['processId']) || empty($data['processToken']))
				throw new Grow_ApiError('Grow API: Payment process data is missing from response.');

			if (!Grow_Api::is_payment_page_url($data['url']))
				throw new Grow_ApiError('Grow API: Refusing to redirect to unknown payment page.');

			// store process so notifications can be verified later
			$manager->insert_item(array(
					'transaction'   => $transaction->uid,
					'process_id'    => fix_chars($data['processId']),
					'process_token' => fix_chars($data['processToken']),
					'status'        => Grow_TransactionStatus::CREATED
				));

			$redirect_url = $data['url'];

		} catch (UnknownTransactionError $error) {
			trigger_error('Grow: '.$error->getMessage(), E_USER_WARNING);

		} catch (Grow_ApiError $error) {
			trigger_error($error->getMessage(), E_USER_WARNING);
		}

		header('Location: '.$redirect_url, true, 302);
	}

	/**
	 * Handle server to server notification about finished payment. Grow
	 * repeats notification until it's approved through API.
	 */
	public function handle_notification() {
		$manager = Grow_TransactionManager::get_instance();
		$response_code = 200;
		$response_text = 'OK';

		// grow sends fields either at top level or nested under `data`
		if (isset($_POST['data']) && is_array($_POST['data']))
			$data = $_POST['data']; else
			$data = $_POST;

		$status = isset($_POST['status']) ? (int) $_POST['status'] : 1;
		$process_id = isset($data['processId']) ? fix_chars($data['processId']) : '';
		$process_token = isset($data['processToken']) ? fix_chars($data['processToken']) : '';

		// find payment process notification refers to
		$record = null;
		if (!empty($process_id))
			$record = $manager->get_single_item(
					$manager->get_field_names(),
					array('process_id' => $process_id)
				);

		if (!is_object($record) || !hash_equals($record->process_token, $process_token)) {
			trigger_error('Grow: Notification received for unknown payment process.', E_USER_WARNING);
			$response_code = 404;
			$response_text = 'UNKNOWN PROCESS';

		} else if ($status != 1) {
			trigger_error('Grow: Notification reports failed payment for process '.$process_id, E_USER_NOTICE);
			$response_text = 'IGNORED';

		} else {
			try {
				$transaction = Transaction::get($record->transaction);

				// confirm receipt only when payment was accepted, otherwise
				// grow will repeat notification and we get to log it again
				if ($this->accept_payment($record, $transaction, $data)) {
					$this->approve_transaction($data);

				} else {
					$response_code = 409;
					$response_text = 'REJECTED';
				}

			} catch (UnknownTransactionError $error) {
				trigger_error('Grow: '.$error->getMessage(), E_USER_WARNING);
				$response_code = 404;
				$response_text = 'UNKNOWN TRANSACTION';
			}
		}

		http_response_code($response_code);
		print $response_text;
	}

	/**
	 * Handle buyer returning from payment page after successful payment.
	 * Server notification is authoritative, but in case it didn't arrive yet
	 * process status is checked through API.
	 */
	public function handle_confirm_payment() {
		$uid = isset($_REQUEST['transaction']) ? fix_chars($_REQUEST['transaction']) : '';
		$redirect_url = URL::make_query('shop', 'checkout-canceled', array('payment_method', $this->name));

		try {
			$transaction = Transaction::get($uid);
			$record = $this->get_record($transaction->uid);

			if (is_null($record))
				throw new UnknownTransactionError('No payment process found for transaction.');

			// notification might not have arrived yet
			if ($record->status == Grow_TransactionStatus::CREATED) {
				$this->verify_process($record, $transaction);
				$record = $this->get_record($transaction->uid);
			}

			// transaction status is changed outside of buyer's session so
			// we need to clean up here
			$accepted_statuses = array(Grow_TransactionStatus::PAID, Grow_TransactionStatus::AUTHORIZED);
			if (in_array($record->status, $accepted_statuses))
				unset($_SESSION['transaction']);

			$redirect_url = URL::make_query('shop', 'checkout-completed', array('payment_method', $this->name));

		} catch (UnknownTransactionError $error) {
			trigger_error('Grow: '.$error->getMessage(), E_USER_WARNING);
		}

		header('Location: '.$redirect_url, true, 302);
	}

	/**
	 * Handle buyer canceling payment on payment page.
	 */
	public function handle_cancel_payment() {
		$shop = shop::get_instance();
		$manager = Grow_TransactionManager::get_instance();
		$uid = isset($_REQUEST['transaction']) ? fix_chars($_REQUEST['transaction']) : '';
		$redirect_url = URL::make_query('shop', 'checkout-canceled', array('payment_method', $this->name));

		try {
			$transaction = Transaction::get($uid);
			$current = Transaction::get_current();

			// only buyer who started the payment can cancel it
			$owns_transaction = !is_null($current) && $current->id == $transaction->id;

			if ($owns_transaction && $transaction->status == TransactionStatus::PENDING) {
				$manager->update_items(
						array('status' => Grow_TransactionStatus::CANCELED),
						array(
							'transaction' => $transaction->uid,
							'status'      => Grow_TransactionStatus::CREATED
						)
					);
				$shop->setTransactionStatus($transaction->uid, TransactionStatus::CANCELED);
			}

		} catch (UnknownTransactionError $error) {
			trigger_error('Grow: '.$error->getMessage(), E_USER_NOTICE);
		}

		header('Location: '.$redirect_url, true, 302);
	}

	/**
	 * Return absolute, secure URL for specified module action. Grow refuses
	 * plain HTTP addresses.
	 *
	 * @param string $action
	 * @param string $transaction_uid
	 * @return string
	 */
	public function get_callback_url($action, $transaction_uid=null) {
		if (is_null($transaction_uid))
			$url = URL::make_query($this->name, $action); else
			$url = URL::make_query($this->name, $action, array('transaction', $transaction_uid));

		$result = preg_replace('/^http:/i', 'https:', $url);

		return $result;
	}

	/**
	 * Generate hidden fields for checkout form.
	 *
	 * @param array $data
	 * @return string
	 */
	private function make_checkout_fields($data) {
		$uid = escape_chars($data['uid']);
		$result = '<input type="hidden" name="transaction" value="'.$uid.'">';

		return $result;
	}

	/**
	 * Prepare parameters for new payment process.
	 *
	 * @param object $transaction
	 * @param integer $charge_type
	 * @return array
	 */
	private function prepare_process_params($transaction, $charge_type) {
		$settings = $this->parent->settings;
		$items = $this->get_transaction_items($transaction);
		$buyer = Transaction::get_buyer($transaction);

		$params = array(
				'pageCode'    => $settings['page_code'],
				'userId'      => $settings['user_id'],
				'chargeType'  => $charge_type,
				'sum'         => $this->format_sum($this->get_total($transaction)),
				'description' => $this->make_description($items),
				'successUrl'  => $this->get_callback_url('payment-confirm', $transaction->uid),
				'cancelUrl'   => $this->get_callback_url('payment-cancel', $transaction->uid),
				'notifyUrl'   => $this->get_callback_url('payment-notify'),
				'cField1'     => $transaction->uid,
				'paymentNum'  => 1
			);

		// allow buyer to split payment in installments
		$max_payments = isset($settings['max_payments']) ? (int) $settings['max_payments'] : 1;
		if ($max_payments > 1)
			$params['maxPaymentNum'] = min($max_payments, self::MAX_PAYMENTS);

		// buyer details are used to fill in payment page
		if (is_object($buyer)) {
			$full_name = trim($buyer->first_name.' '.$buyer->last_name);
			$name_count = count(preg_split('/\s+/u', $full_name, -1, PREG_SPLIT_NO_EMPTY));
			$fields = array(
					'fullName' => $name_count >= 2 ? $full_name : '',
					'phone'    => preg_replace('/[^0-9+]/', '', $buyer->phone),
					'email'    => $buyer->email
				);

			foreach ($fields as $name => $value)
				if (!empty($value))
					$params['pageField'][$name] = $value;
		}

		// item list for invoice
		$params['productData'] = array();

		foreach ($items as $item)
			$params['productData'][] = array(
					'catalogNumber'   => $item['id'],
					'quantity'        => $item['count'],
					'price'           => $this->format_sum($item['price']),
					'itemDescription' => $this->sanitize_text($item['name'], self::MAX_DESCRIPTION_LENGTH)
				);

		if ($transaction->shipping > 0)
			$params['productData'][] = array(
					'quantity'        => 1,
					'price'           => $this->format_sum($transaction->shipping),
					'itemDescription' => $this->parent->get_language_constant('invoice_shipping')
				);

		if ($transaction->handling > 0)
			$params['productData'][] = array(
					'quantity'        => 1,
					'price'           => $this->format_sum($transaction->handling),
					'itemDescription' => $this->parent->get_language_constant('invoice_handling')
				);

		return $params;
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
						'id'    => $item->item,
						'name'  => isset($names[$item->item]) ? $names[$item->item] : '',
						'price' => round($item->price * (1 + ($item->tax / 100)), 2),
						'count' => $item->amount
					);
		}

		return $result;
	}

	/**
	 * Make short transaction description from item names.
	 *
	 * @param array $items
	 * @return string
	 */
	private function make_description($items) {
		$names = array();

		foreach (array_slice($items, 0, 5) as $item)
			if (!empty($item['name']))
				$names[] = $item['name'];

		$result = implode(', ', $names);

		if (count($items) > 5)
			$result .= ', ...';

		if (empty($result))
			$result = $this->parent->get_language_constant('invoice_default_description');

		return $this->sanitize_text($result, self::MAX_DESCRIPTION_LENGTH);
	}

	/**
	 * Remove characters Grow doesn't accept and limit length of the text.
	 *
	 * @param string $text
	 * @param integer $limit
	 * @return string
	 */
	private function sanitize_text($text, $limit) {
		$result = preg_replace('/[^\p{L}\p{N}\s,.\-]/u', '', $text);
		$result = trim(preg_replace('/\s+/', ' ', $result));

		if (mb_strlen($result, 'UTF-8') > $limit)
			$result = trim(mb_substr($result, 0, $limit, 'UTF-8'));

		return $result;
	}

	/**
	 * Accept payment reported either by notification or process info and
	 * update transaction accordingly.
	 *
	 * @param object $record
	 * @param object $transaction
	 * @param array $data
	 * @return boolean
	 */
	private function accept_payment($record, $transaction, $data) {
		$result = false;
		$shop = shop::get_instance();
		$manager = Grow_TransactionManager::get_instance();

		$expected_sum = $this->get_total($transaction);
		$received_sum = isset($data['sum']) ? round((float) $data['sum'], 2) : 0;
		$details = $this->extract_details($data);

		$accepted_statuses = array(
				Grow_TransactionStatus::PAID,
				Grow_TransactionStatus::AUTHORIZED,
				Grow_TransactionStatus::SETTLED
			);

		if (in_array($record->status, $accepted_statuses)) {
			// repeated notification, just fill in details we might be missing
			if (count($details) > 0)
				$manager->update_items($details, array('id' => $record->id));

			$result = true;

		} else if (abs($expected_sum - $received_sum) >= 0.01) {
			trigger_error(
				"Grow: Paid amount ({$received_sum}) differs from expected ({$expected_sum}) ".
				"for transaction {$transaction->uid}.",
				E_USER_WARNING
			);
			$manager->update_items(
					array('status' => Grow_TransactionStatus::FAILED),
					array('id' => $record->id)
				);

		} else {
			$delayed = $transaction->type == TransactionType::DELAYED;

			// store transaction details for later use
			$details['status'] = $delayed ? Grow_TransactionStatus::AUTHORIZED : Grow_TransactionStatus::PAID;
			$manager->update_items($details, array('id' => $record->id));

			if (isset($details['transaction_id']))
				Transaction::set_remote_id($transaction, $details['transaction_id']);

			// delayed transactions remain pending until they are settled
			if (!$delayed)
				$shop->setTransactionStatus($transaction->uid, TransactionStatus::COMPLETED);

			$result = true;
		}

		return $result;
	}

	/**
	 * Extract transaction details from data received from Grow.
	 *
	 * @param array $data
	 * @return array
	 */
	private function extract_details($data) {
		$result = array();
		$fields = array(
				'transactionId'    => 'transaction_id',
				'transactionToken' => 'transaction_token',
				'asmachta'         => 'asmachta',
				'cardSuffix'       => 'card_suffix'
			);

		foreach ($fields as $name => $column)
			if (isset($data[$name]) && is_scalar($data[$name]) && $data[$name] !== '')
				$result[$column] = fix_chars((string) $data[$name]);

		return $result;
	}

	/**
	 * Confirm receipt of notification by sending received data back.
	 *
	 * @param array $data
	 */
	private function approve_transaction($data) {
		$params = array('pageCode' => $this->parent->settings['page_code']);

		// grow expects all notification fields to be sent back
		foreach ($data as $name => $value)
			if (is_scalar($value))
				$params[$name] = $value;

		// notification uses different capitalization for this field
		if (isset($data['TransactionTypeId']) && !isset($data['transactionTypeId']))
			$params['transactionTypeId'] = $data['TransactionTypeId'];

		try {
			Grow_Api::call(Grow_Api::METHOD_APPROVE_TRANSACTION, $params);

		} catch (Grow_ApiError $error) {
			trigger_error($error->getMessage(), E_USER_NOTICE);
		}
	}

	/**
	 * Ask Grow about payment process and accept payment if it was clearly
	 * completed. Response layout is not documented so only certain cases
	 * are accepted, everything else is left for notification to handle.
	 *
	 * @param object $record
	 * @param object $transaction
	 */
	private function verify_process($record, $transaction) {
		$params = array(
				'pageCode'     => $this->parent->settings['page_code'],
				'processId'    => $record->process_id,
				'processToken' => $record->process_token
			);

		try {
			$info = Grow_Api::call(Grow_Api::METHOD_GET_PAYMENT_PROCESS_INFO, $params);

			if (isset($info['statusCode']))
				$paid = (int) $info['statusCode'] == 2; else
				$paid = !empty($info['transactionId']);

			if ($paid)
				$this->accept_payment($record, $transaction, $info);

		} catch (Grow_ApiError $error) {
			trigger_error($error->getMessage(), E_USER_NOTICE);
		}
	}

	/**
	 * Get most recent payment process record for transaction.
	 *
	 * @param string $transaction_uid
	 * @return object
	 */
	private function get_record($transaction_uid) {
		$manager = Grow_TransactionManager::get_instance();
		$result = $manager->get_single_item(
				$manager->get_field_names(),
				array('transaction' => $transaction_uid),
				array('id'),
				false
			);

		return $result;
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

	/**
	 * Format amount the way API expects it.
	 *
	 * @param float $value
	 * @return string
	 */
	private function format_sum($value) {
		return number_format($value, 2, '.', '');
	}
}

?>
