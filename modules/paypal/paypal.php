<?php

/**
 * PayPal Payment Implementation
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */
use Core\Module;

require_once('units/helper.php');
require_once('units/plans_manager.php');


class paypal extends Module {
	private static $_instance;

	private $express_method;
	private $direct_method;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// register backend
		if (ModuleHandler::is_loaded('backend') && ModuleHandler::is_loaded('shop')) {
			$backend = backend::get_instance();
			$method_menu = $backend->getMenu('shop_payment_methods');
			$recurring_menu = $backend->getMenu('shop_recurring_plans');

			// add menu entry for payment methods
			if (!is_null($method_menu))
				$method_menu->addChild('', new backend_MenuItem(
									$this->get_language_constant('menu_paypal'),
									url_GetFromFilePath($this->path.'images/icon.svg'),
									window_Open( // on click open window
												'paypal',
												400,
												$this->get_language_constant('title_settings'),
												true, true,
												backend_UrlMake($this->name, 'settings')
											),
									$level=5
								));

			if (!is_null($recurring_menu))
				$recurring_menu->addChild('', new backend_MenuItem(
									$this->get_language_constant('menu_paypal'),
									url_GetFromFilePath($this->path.'images/icon.svg'),
									window_Open( // on click open window
												'paypal_recurring_plans',
												400,
												$this->get_language_constant('title_recurring_plans'),
												true, true,
												backend_UrlMake($this->name, 'recurring_plans')
											),
									$level=5
								));
		}

		// register payment method
		if (ModuleHandler::is_loaded('shop')) {
			require_once('units/express_payment_method.php');
			require_once('units/direct_payment_method.php');

			// set helped in debug mode if specified
			PayPal_Helper::setSandbox(shop::get_instance()->isDebug());
			PayPal_Helper::setCredentials(
								$this->settings['api_username'],
								$this->settings['api_password'],
								$this->settings['api_signature']
							);

			// create payment methods
			if ($this->settings['express_enabled'] == 1)
				$this->express_method = PayPal_Express::get_instance($this);

			if ($this->settings['direct_enabled'] == 1)
				$this->direct_method = PayPal_Direct::get_instance($this);
		}
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
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
	public function transfer_control($params, $children) {
		// global control action
		if (isset($params['action']))
			switch ($params['action']) {
				case 'express-checkout':
					$this->completeExpressCheckout();
					break;

				case 'direct-checkout':
					$this->completeDirectCheckout();
					break;

				case 'ipn':
					$this->handleIPN();
					break;

				default:
					break;
			}

		// global control action
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'settings':
					$this->showSettings();
					break;

				case 'settings_save':
					$this->save_settings();
					break;

				case 'recurring_plans':
					$this->recurringPaymentPlans();
					break;

				case 'recurring_plans_new':
					$this->addPlan();
					break;

				case 'recurring_plans_change':
					$this->changePlan();
					break;

				case 'recurring_plans_save':
					$this->savePlan();
					break;

				case 'recurring_plans_delete':
					$this->deletePlan();
					break;

				case 'recurring_plans_delete_commit':
					$this->deletePlan_Commit();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function on_init() {
		global $db;

		// get list of languages
		$list = Language::get_languages(false);

		// store global settings
		$this->save_setting('api_username', '');
		$this->save_setting('api_password', '');
		$this->save_setting('api_signature', '');
		$this->save_setting('express_enabled', 1);
		$this->save_setting('direct_enabled', 1);

		// create tables
		$sql = "
			CREATE TABLE `paypal_recurring_plans` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`text_id` VARCHAR (32) NULL,";

		foreach($list as $language)
			$sql .= "`name_{$language}` VARCHAR(255) NOT NULL DEFAULT '',";

		$sql .= "
				`trial` INT NOT NULL DEFAULT '0',
				`trial_count` INT NOT NULL DEFAULT '0',
				`interval` INT NOT NULL DEFAULT '0',
				`interval_count` INT NOT NULL DEFAULT '0',
				`price` DECIMAL(8,2) NOT NULL,
				`setup_price` DECIMAL(8,2) NOT NULL,
				`start_time` TIMESTAMP NULL,
				`group_name` varchar(64) NOT NULL,
				PRIMARY KEY (`id`),
				INDEX (`text_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";

		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function on_disable() {
		global $db;

		$tables = array('paypal_recurring_plans');

		$db->drop_tables($tables);
	}

	/**
	 * Show PayPal settings form
	 */
	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
						'form_action'	=> backend_UrlMake($this->name, 'settings_save'),
						'cancel_action'	=> window_Close('paypal')
					);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save settings
	 */
	private function save_settings() {
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$this->save_setting('api_username', escape_chars($_REQUEST['api_username']));
		$this->save_setting('api_password', escape_chars($_REQUEST['api_password']));
		$this->save_setting('api_signature', escape_chars($_REQUEST['api_signature']));
		$this->save_setting('express_enabled', $this->get_boolean_field('express_enabled') ? 1 : 0);
		$this->save_setting('direct_enabled', $this->get_boolean_field('direct_enabled') ? 1 : 0);

		$params = array(
					'message'	=> $this->get_language_constant('message_settings_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('paypal')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show recurring plans form.
	 */
	private function recurringPaymentPlans() {
		$template = new TemplateHandler('plans_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$template->register_tag_handler('cms:list', $this, 'tag_PlanList');

		$params = array(
					'link_new' => window_OpenHyperlink(
										$this->get_language_constant('new'),
										'paypal_recurring_plans_new', 405,
										$this->get_language_constant('title_recurring_plans_new'),
										true, false,
										$this->name,
										'recurring_plans_new'
									)
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for adding a new plan.
	 */
	private function addPlan() {
		$shop = shop::get_instance();
		$template = new TemplateHandler('plans_add.xml', $this->path.'templates/');
		$template->register_tag_handler('cms:cycle_unit', $shop, 'tag_CycleUnit');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'recurring_plans_save'),
					'cancel_action'	=> window_Close('paypal_recurring_plans_new')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for changing an existing plan.
	 */
	private function changePlan() {
		$id = fix_id($_REQUEST['id']);
		$shop = shop::get_instance();
		$manager = PayPal_PlansManager::get_instance();

		$plan = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (is_object($plan)) {
			$template = new TemplateHandler('plans_change.xml', $this->path.'templates/');
			$template->register_tag_handler('cms:cycle_unit', $shop, 'tag_CycleUnit');
			$template->set_mapped_module($this->name);

			$params = array(
						'id'				=> $plan->id,
						'text_id'			=> $plan->text_id,
						'name'				=> $plan->name,
						'trial'				=> $plan->trial,
						'trial_count'		=> $plan->trial_count,
						'interval'			=> $plan->interval,
						'interval_count'	=> $plan->interval_count,
						'price'				=> $plan->price,
						'setup_price'		=> $plan->setup_price,
						'start_time'		=> $plan->start_time,
						'group_name'		=> $plan->group_name,
						'form_action'	=> backend_UrlMake($this->name, 'recurring_plans_save'),
						'cancel_action'	=> window_Close('paypal_recurring_plans_change')
					);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Save new or changed plan.
	 */
	private function savePlan() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = PayPal_PlansManager::get_instance();

		$data = array(
				'text_id'			=> escape_chars($_REQUEST['text_id']),
				'name'				=> $this->get_multilanguage_field('name'),
				'trial'				=> fix_id($_REQUEST['trial_unit']),
				'trial_count'		=> fix_id($_REQUEST['trial_count']),
				'interval'			=> fix_id($_REQUEST['interval_unit']),
				'interval_count'	=> fix_id($_REQUEST['interval_count']),
				'price'				=> fix_chars($_REQUEST['interval_price']),
				'setup_price'		=> fix_chars($_REQUEST['setup_price']),
				'start_time'		=> fix_chars($_REQUEST['start_time']),
				'group_name'		=> fix_chars($_REQUEST['group_name'])
			);


		if (is_null($id)) {
			$window = 'paypal_recurring_plans_new';
			$manager->insert_item($data);

		} else {
			$window = 'paypal_recurring_plans_change';
			$manager->update_items($data,	array('id' => $id));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_plan_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).';'.window_ReloadContent('paypal_recurring_plans'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show confirmation form for removing a plan.
	 */
	private function deletePlan() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = PayPal_PlansManager::get_instance();

		$item = $manager->get_single_item(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant('message_plan_delete'),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->get_language_constant('delete'),
					'no_text'		=> $this->get_language_constant('cancel'),
					'yes_action'	=> window_LoadContent(
											'paypal_recurring_plans_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'recurring_plans_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('paypal_recurring_plans_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Mark plan as deleted.
	 */
	private function deletePlan_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = PayPal_PlansManager::get_instance();

		$manager->delete_items(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_plan_deleted'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('paypal_recurring_plans_delete').';'.
								window_ReloadContent('paypal_recurring_plans')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Handle recurring payment IPN.
	 *
	 * @param object $transaction
	 * @param string $type
	 * @param float $amount
	 * @return boolean
	 */
	private function handleRecurringIPN($transaction, $type, $amount) {
		$result = false;
		$shop = shop::get_instance();
		$plan_manager = ShopTransactionPlansManager::get_instance();

		// get plan associated with this transaction
		$plan = $plan_manager->get_single_item(
									$plan_manager->get_field_names(),
									array('transaction' => $transaction->id)
								);

		if (!is_object($plan)) {
			trigger_error(
					'PayPal: Unable to handle IPN, unable to get plan for transaction: '.$transaction->id,
					E_USER_WARNING
				);
			return $result;
		}

		// notification type to status relation
		$status = array(
				'recurring_payment' => RecurringPayment::ACTIVE,
				'recurring_payment_expired' => RecurringPayment::EXPIRED,
				'recurring_payment_failed' => RecurringPayment::FAILED,
				'recurring_payment_profile_created' => RecurringPayment::PENDING,
				'recurring_payment_profile_cancel' => RecurringPayment::CANCELED,
				'recurring_payment_skipped' => RecurringPayment::SKIPPED,
				'recurring_payment_suspended' => RecurringPayment::SUSPENDED,
				'recurring_payment_suspended_due_to_max_failed_payment' => RecurringPayment::SUSPENDED
			);

		// add new recurring payment
		$result = $shop->addRecurringPayment($plan->id, $amount, $status[$type]);

		return $result;
	}

	/**
	 * Handle IPN.
	 */
	private function handleIPN() {
		if (!PayPal_Helper::validate_notification()) {
			trigger_error(
					'PayPal: Invalid notification received. '.json_encode($_POST),
					E_USER_WARNING
				);
			return;
		}

		// get objects
		$transaction_manager = ShopTransactionsManager::get_instance();

		// get data
		$handled = false;
		$type = escape_chars($_POST['txn_type']);
		$amount = escape_chars($_POST['amount']);

		// handle different notification types
		switch ($type) {
			case 'recurring_payment':
			case 'recurring_payment_expired':
			case 'recurring_payment_failed':
			case 'recurring_payment_profile_created':
			case 'recurring_payment_profile_cancel':
			case 'recurring_payment_skipped':
			case 'recurring_payment_suspended':
			case 'recurring_payment_suspended_due_to_max_failed_payment':
				$profile_id = escape_chars($_REQUEST['recurring_payment_id']);
				$transaction = $transaction_manager->get_single_item(
												$transaction_manager->get_field_names(),
												array('token' => $profile_id)
											);

				if (is_object($transaction))
					$handled = $this->handleRecurringIPN($transaction, $type, $amount); else
					trigger_error(
						"PayPal: Unable to handle IPN, unknown transaction {$profile_id}.",
						E_USER_WARNING
					);

				break;
		}

		// record unhandled notifications
		if (!$handled)
			trigger_error("PayPal: Unhandled notification '{$type}'.", E_USER_NOTICE);
	}

	/**
	 * Complete express checkout.
	 */
	private function completeExpressCheckout() {
		$this->express_method->completeCheckout();
	}

	private function completeDirectCheckout() {
		$this->direct_method->completeCheckout();
	}

	/**
	 * Tag handler for drawing multiple plans.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_PlanList($tag_params, $children) {
		$manager = PayPal_PlansManager::get_instance();
		$conditions = array();

		$template = $this->load_template($tag_params, 'plans_list_item.xml');
		$template->set_template_params_from_array($children);

		$items = $manager->get_items($manager->get_field_names(), $conditions);

		if (count($items) > 0)
			foreach($items as $item) {
				$params = array(
					'id'				=> $item->id,
					'text_id'			=> $item->text_id,
					'name'				=> $item->name,
					'trial'				=> $item->trial,
					'trial_count'		=> $item->trial_count,
					'interval'			=> $item->interval,
					'interval_count'	=> $item->interval_count,
					'price'				=> $item->price,
					'setup_price'		=> $item->setup_price,
					'start_time'		=> $item->start_time,
					'group_name'		=> $item->group_name,
					'item_change'	=> url_MakeHyperlink(
											$this->get_language_constant('change'),
											window_Open(
												'paypal_recurring_plans_change', 	// window id
												405,				// width
												$this->get_language_constant('title_recurring_plans_change'), // title
												false, false,
												url_Make(
													'transfer_control',
													'backend_module',
													array('module', $this->name),
													array('backend_action', 'recurring_plans_change'),
													array('id', $item->id)
												)
											)
										),
					'item_delete'	=> url_MakeHyperlink(
											$this->get_language_constant('delete'),
											window_Open(
												'paypal_recurring_plans_delete', 	// window id
												400,				// width
												$this->get_language_constant('title_recurring_plans_delete'), // title
												false, false,
												url_Make(
													'transfer_control',
													'backend_module',
													array('module', $this->name),
													array('backend_action', 'recurring_plans_delete'),
													array('id', $item->id)
												)
											)
										),
				);

				$template->restore_xml();
				$template->set_local_params($params);
				$template->parse();
			}
	}
}
