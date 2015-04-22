<?php

/**
 * Stripe Payment Implementation
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */

require_once(_LIBPATH.'/stripe/init.php');
require_once('units/stripe_payment_method.php');
require_once('units/plans_manager.php');
require_once('units/customer_manager.php');

use Core\Module;


class stripe_payment extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// prepare API wrapper
		Stripe::setApiKey($this->getPrivateKey());

		// register backend
		if (class_exists('backend') && class_exists('shop')) {
			$backend = backend::getInstance();
			$method_menu = $backend->getMenu('shop_payment_methods');
			$plans_menu = $backend->getMenu('shop_recurring_plans');

			if (!is_null($method_menu))
				$method_menu->addChild('', new backend_MenuItem(
									$this->getLanguageConstant('menu_stripe'),
									url_GetFromFilePath($this->path.'images/icon.svg'),

									window_Open( // on click open window
												'stripe',
												350,
												$this->getLanguageConstant('title_settings'),
												true, true,
												backend_UrlMake($this->name, 'show_settings')
											),
									$level=5
								));

			if (!is_null($plans_menu))
				$plans_menu->addChild('', new backend_MenuItem(
									$this->getLanguageConstant('menu_stripe'),
									url_GetFromFilePath($this->path.'images/icon.svg'),

									window_Open( // on click open window
												'stripe_recurring_plans',
												460,
												$this->getLanguageConstant('title_recurring_plans'),
												true, true,
												backend_UrlMake($this->name, 'recurring_plans')
											),
									$level=5
								));
		}

		// register payment method
		if (class_exists('shop')) {
			require_once("units/stripe_payment_method.php");
			Stripe_PaymentMethod::getInstance($this);
		}
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'charge':
					$this->charge();
					break;

				case 'subscribe':
					$this->subscribe();
					break;

				case 'show_plan_list':
					$this->tag_PlanList($params, $children);
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'show_settings':
					$this->showSettings();
					break;

				case 'save_settings':
					$this->saveSettings();
					break;

				case 'recurring_plans':
					$this->showRecurringPlans();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db;

		$this->saveSetting('secret_key', '');
		$this->saveSetting('public_key', '');

		// create tables
		$sql = "
			CREATE TABLE `stripe_recurring_plans` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`text_id` VARCHAR (32) NULL,
				`name` VARCHAR (255) NULL,
				`trial_days` INT NOT NULL DEFAULT '0',
				`interval` INT NOT NULL DEFAULT '0',
				`interval_count` INT NOT NULL DEFAULT '0',
				`price` DECIMAL(8,2) NOT NULL,
				`currency` VARCHAR (3) NOT NULL,
				PRIMARY KEY (`id`),
				INDEX (`text_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		$sql = "
			CREATE TABLE `stripe_customers` (
				`system_user` INT NOT NULL,
				`buyer` INT NOT NULL,
				`text_id` VARCHAR (64) NULL,
				INDEX (`system_user`),
				INDEX (`buyer`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;";
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array('stripe_recurring_plans', 'stripe_customers');

		$db->drop_tables($tables);
	}

	/**
	 * Show Stripe settings form
	 */
	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$params = array(
						'form_action'	=> backend_UrlMake($this->name, 'save_settings'),
						'cancel_action'	=> window_Close('stripe')
					);

		$template->setLocalParams($params);

		$template->restoreXML();
		$template->parse();
	}

	/**
	 * Save settings
	 */
	private function saveSettings() {
		$secret_key = fix_chars($_REQUEST['secret_key']);
		$public_key = fix_chars($_REQUEST['public_key']);

		$this->saveSetting('secret_key', $secret_key);
		$this->saveSetting('public_key', $public_key);

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_settings_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('stripe')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Function that checks if API keys were entered properly.
	 *
	 * @return boolean
	 */
	private function checkKeys() {
		$result = !empty($this->settings['secret_key']) && !empty($this->settings['public_key']);

		// show message if API keys are not set
		if (!$result) {
			$template = new TemplateHandler('window_message.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
						'message'	=> $this->getLanguageConstant('message_missing_api_keys'),
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}

		return $result;
	}

	/**
	 * Update list of recurring plans throuhg API.
	 */
	private function updateRecurringPlans() {
		$manager= Stripe_PlansManager::getInstance();
		$response = Stripe_Plan::all();
		$uids = array();
		$processed_plans = array();
		$missing_plans = array();
		$interval_map = array(
				'day'	=> RecurringPayment::DAY,
				'week'	=> RecurringPayment::WEEK,
				'month'	=> RecurringPayment::MONTH,
				'year'	=> RecurringPayment::YEAR
			);

		// load all plan unique ids
		$plans = $manager->getItems(array('text_id'), array());

		if (count($plans) > 0)
			foreach ($plans as $plan)
				$uids[] = $plan->text_id;

		// synchronize database with result from API call
		foreach ($response['data'] as $plan) {
			// add uid to list of processed plans
			$processed_plans[] = $plan['id'];

			if (in_array($plan['id'], $uids)) {
				// plan exists, update data
				$data = array('name' => $plan['name']);
				$manager->updateData($data, array('text_id' => $plan['id']));

			} else {
				// this plan is not present in database, add it
				$data = array(
						'text_id'			=> $plan['id'],
						'name'				=> $plan['name'],
						'trial_days'		=> !is_null($plan['trial_period_days']) ? $plan['trial_period_days'] : 0,
						'interval'			=> $interval_map[$plan['interval']],
						'interval_count'	=> $plan['interval_count'],
						'price'				=> $plan['amount'] / 100,
						'currency'			=> $plan['currency']
					);
				$manager->insertData($data);
			}
		}

		// prepare list of plans to remove
		$remove_plans = array();
		foreach ($plans as $plan) {
			if (!in_array($plan->text_id, $processed_plans))
				$remove_plans[] = $plan->text_id;
		}

		// remove plans
		$manager->deleteData(array('text_id' => $remove_plans));
	}

	/**
 	 * Show list of recurring plans.
	 */
	private function showRecurringPlans() {
		if (!$this->checkKeys())
			return;

		$this->updateRecurringPlans();

		$template = new TemplateHandler('plans_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$template->registerTagHandler('cms:list', $this, 'tag_PlanList');

		$params = array();

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Return Stripe publishable key
	 *
	 * @return string
	 */
	public function getPublicKey() {
		return $this->settings['public_key'];
	}

	/**
	 * Return Stripe private key
	 *
	 * @return string
	 */
	private function getPrivateKey() {
		return $this->settings['secret_key'];
	}

	/**
	 * Get Stripe customer from transaction UID.
	 *
	 * @param string $transaction_uid
	 * @return object
	 */
	public static function getCustomer($transaction_uid) {
		$transaction_manager = ShopTransactionsManager::getInstance();
		$customer_manager = Stripe_CustomerManager::getInstance();
		$conditions = array();

		// get transaction first
		$transaction = $transaction_manager->getSingleItem(
								array('buyer', 'system_user'),
								array('uid' => $transaction_uid)
							);

		// transaction doesn't exist
		if (!is_object($transaction))
			return null;

		// prepare conditions
		if ($transaction->system_user != 0)
			$conditions['system_user'] = $transaction->system_user;

		if ($transaction->buyer != 0)
			$conditions['buyer'] = $transaction->buyer;

		// make sure we have at least one condition
		if (count($conditions) == 0)
			return null;

		// try to get customer from database
		$customer = $customer_manager->getSingleItem(
							$customer_manager->getFieldNames(),
							$conditions
						);

		return $customer;
	}

	/**
	 * Charge normal payment in checkout process.
	 */
	private function charge() {
		$redirect_url = fix_chars($_REQUEST['redirect_url']);
		$transaction_uid = fix_chars($_REQUEST['transaction_uid']);
		$shop = shop::getInstance();
		$transaction_manager = ShopTransactionsManager::getInstance();
		$currency_manager = ShopCurrenciesManager::getInstance();

		// get card details
		$card = array(
				'name'		=> fix_chars($_REQUEST['name']),
				'number'	=> fix_chars($_REQUEST['credit_card']),
				'exp_month'	=> fix_id($_REQUEST['exp_month']),
				'exp_year'	=> fix_id($_REQUEST['exp_year']),
				'cvc'		=> fix_chars($_REQUEST['cvv']),
			);

		// get transaction
		$transaction = $transaction_manager->getSingleItem(
								$transaction_manager->getFieldNames(),
								array('uid' => $transaction_uid)
							);

		if (!is_object($transaction)) {
			trigger_error('Unable to charge unknown transaction!', E_USER_ERROR);
			return;
		}

		// get currency
		$currency = $currency_manager->getSingleItem(
								$currency_manager->getFieldNames(),
								array('id' => $transaction->currency)
							);

		// create charge
		try {
			$charge = Stripe_Charge::create(array(
							'amount'		=> $transaction->total * 100,
							'currency'		=> $currency->currency,
							'card'			=> $card,
							'description'	=> null
						));

		} catch (Stripe_CardError $error) {
			trigger_error(get_class($error).': '.$error->getMessage());
		}

		// update transaction status
		if (is_object($charge) && $charge->paid) {
			$shop->setTransactionToken($transaction_uid, $charge->id);
			$shop->setTransactionStatus($transaction_uid, TransactionStatus::COMPLETED);

		} else {
			// TODO: handle errors
		}

		// redirect to checkout
		header('Location: '.$return_url, true, 302);
	}

	/**
	 * Subscribe new or existing customer.
	 */
	private function subscribe() {
		$shop = shop::getInstance();
		$transaction_manager = ShopTransactionsManager::getInstance();
		$customer_manager = Stripe_CustomerManager::getInstance();
		$stripe_token = isset($_REQUEST['stripe_token']) ? fix_chars($_REQUEST['stripe_token']) : null;
		$transaction_uid = fix_chars($_REQUEST['transaction']);
		$plan_name = fix_chars($_REQUEST['plan_name']);

		// get transaction
		$transaction = $transaction_manager->getSingleItem(
								$transaction_manager->getFieldNames(),
								array('uid' => $transaction_uid)
							);

		if (!is_object($transaction)) {
			trigger_error('Unable to subscribe, unknown transaction!', E_USER_ERROR);
			return;
		}

		// get customer
		$customer = self::getCustomer($transaction->uid);

		if (is_null($customer)) {
			// get card details
			$card = array(
					'name'		=> fix_chars($_REQUEST['name']),
					'number'	=> fix_chars($_REQUEST['credit_card']),
					'exp_month'	=> fix_id($_REQUEST['exp_month']),
					'exp_year'	=> fix_id($_REQUEST['exp_year']),
					'cvc'		=> fix_chars($_REQUEST['cvv'])
				);

			// prepare customer data
			$customer_data = array(
					'card'	=> $card
				);
			$stripe_data = array();

			// get email from system user
			if ($transaction->system_user != 0) {
				$user_manager = UserManager::getInstance();
				$system_user = $user_manager->getSingleItem(
										array('email'),
										array('id' => $transaction->system_user)
									);

				if (is_object($system_user) && !empty($system_user->email)) {
					$customer_data['email'] = $system_user->email;
					$stripe_data['system_user'] = $transaction->system_user;
				}
			}

			// get email from buyer
			if ($transaction->buyer != 0 && !isset($customer_data['email'])) {
				$buyer_manager = ShopBuyersManager::getInstance();
				$buyer = $buyer_manager->getSingleItem(
										array('email'),
										array('id' => $transaction->buyer)
									);

				if (is_object($buyer) && !empty($buyer->email)) {
					$customer_data['email'] = $buyer->email;
					$stripe_data['buyer'] = $transaction->buyer;
				}
			}

			// create stripe customer
			try {
				$stripe_customer = Stripe_Customer::create($customer_data);
				$stripe_data['text_id'] = $stripe_customer->id;

			} catch (Stripe_Error $error) {
				trigger_error(get_class($error).': '.$error->getMessage(), E_USER_ERROR);
			}

			// store customer to local database
			$customer_manager->insertData($stripe_data);

			// make subscription
			try {
				$response = $stripe_customer->subscriptions->create(array('plan' => $plan_name));
				$shop->setTransactionToken($transaction->uid, $response->id);
				$shop->setTransactionStatus($transaction->uid, TransactionStatus::COMPLETED);

			} catch (Stripe_Error $error) {
				trigger_error(get_class($error).': '.$error->getMessage(), E_USER_ERROR);
			}

		} else {
			// subscribe existing customer
			$stripe_customer = Stripe_Customer::retrieve($customer->text_id);

			// make subscription
			try {
				$response = $stripe_customer->subscriptions->create(array('plan' => $plan_name));
				$shop->setTransactionToken($transaction->uid, $response->id);
				$shop->setTransactionStatus($transaction->uid, TransactionStatus::COMPLETED);

			} catch (Stripe_Error $error) {
				trigger_error(get_class($error).': '.$error->getMessage(), E_USER_ERROR);
			}
		}
	}

	/**
	 * Print list of plans.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_PlanList($tag_params, $children) {
		$manager = Stripe_PlansManager::getInstance();
		$conditions = array();
		$selected = isset($_SESSION['recurring_plan']) ? $_SESSION['recurring_plan'] : null;

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// load template
		$template = $this->loadTemplate($tag_params, 'plans_list_item.xml');

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
					'id'				=> $item->id,
					'text_id'			=> $item->text_id,
					'name'				=> $item->name,
					'trial_days'		=> $item->trial_days,
					'interval'			=> $item->interval,
					'interval_count'	=> $item->interval_count,
					'price'				=> $item->price,
					'currency'			=> $item->currency,
					'selected'			=> $selected == $item->name
				);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}
}
