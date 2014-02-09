<?php

/**
 * PayPal Payment Implementation
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */

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
		if (class_exists('backend') && class_exists('shop')) {
			$backend = backend::getInstance();
			$method_menu = $backend->getMenu('shop_payment_methods');
			$recurring_menu = $backend->getMenu('shop_recurring_plans');

			// add menu entry for payment methods
			if (!is_null($method_menu)) 
				$method_menu->addChild('', new backend_MenuItem(
									$this->getLanguageConstant('menu_paypal'),
									url_GetFromFilePath($this->path.'images/icon.png'),
									window_Open( // on click open window
												'paypal',
												400,
												$this->getLanguageConstant('title_settings'),
												true, true,
												backend_UrlMake($this->name, 'settings')
											),
									$level=5
								));

			if (!is_null($recurring_menu))
				$recurring_menu->addChild('', new backend_MenuItem(
									$this->getLanguageConstant('menu_paypal'),
									url_GetFromFilePath($this->path.'images/icon.png'),
									window_Open( // on click open window
												'paypal_recurring_plans',
												400,
												$this->getLanguageConstant('title_recurring_plans'),
												true, true,
												backend_UrlMake($this->name, 'recurring_plans')
											),
									$level=5
								));
		}

		// register payment method
		if (class_exists('shop')) {
			require_once('units/express_payment_method.php');
			require_once('units/direct_payment_method.php');

			// set helped in debug mode if specified
			PayPal_Helper::setSandbox(shop::getInstance()->isDebug());
			PayPal_Helper::setCredentials(
								$this->settings['api_username'],
								$this->settings['api_password'],
								$this->settings['api_signature']
							);

			// create payment methods
			$this->express_method = PayPal_Express::getInstance($this); 		
			$this->direct_method = PayPal_Direct::getInstance($this); 		
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
	public function transferControl($params, $children) {
		// global control action
		if (isset($params['action']))
			switch ($params['action']) {
				case 'express-checkout':
					$this->completeExpressCheckout();
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
					$this->saveSettings();
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
	public function onInit() {
		global $db;

		// get list of languages
		$list = MainLanguageHandler::getInstance()->getLanguages(false);

		// store global settings
		$this->saveSetting('api_username', '');
		$this->saveSetting('api_password', '');
		$this->saveSetting('api_signature', '');

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
				PRIMARY KEY (`id`),
				INDEX (`text_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";

		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array('paypal_recurring_plans');

		$db->drop_tables($tables);
	}

	/**
	 * Show PayPal settings form
	 */
	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
						'form_action'	=> backend_UrlMake($this->name, 'settings_save'),
						'cancel_action'	=> window_Close('paypal')
					);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}
	
	/**
	 * Save settings
	 */
	private function saveSettings() {
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$this->saveSetting('api_username', escape_chars($_REQUEST['api_username']));
		$this->saveSetting('api_password', escape_chars($_REQUEST['api_password']));
		$this->saveSetting('api_signature', escape_chars($_REQUEST['api_signature']));

		$params = array(
					'message'	=> $this->getLanguageConstant('message_settings_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('paypal')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show recurring plans form.
	 */
	private function recurringPaymentPlans() {
		$template = new TemplateHandler('plans_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$template->registerTagHandler('cms:list', $this, 'tag_PlanList');

		$params = array(
					'link_new' => window_OpenHyperlink(
										$this->getLanguageConstant('new'),
										'paypal_recurring_plans_new', 405,
										$this->getLanguageConstant('title_recurring_plans_new'),
										true, false,
										$this->name,
										'recurring_plans_new'
									)
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for adding a new plan.
	 */
	private function addPlan() {
		$shop = shop::getInstance();
		$template = new TemplateHandler('plans_add.xml', $this->path.'templates/');
		$template->registerTagHandler('cms:cycle_unit', $shop, 'tag_CycleUnit');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'recurring_plans_save'),
					'cancel_action'	=> window_Close('paypal_recurring_plans_new')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for changing an existing plan.
	 */
	private function changePlan() {
		$id = fix_id($_REQUEST['id']);
		$shop = shop::getInstance();
		$manager = PayPal_PlansManager::getInstance();

		$plan = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($plan)) {
			$template = new TemplateHandler('plans_change.xml', $this->path.'templates/');
			$template->registerTagHandler('cms:cycle_unit', $shop, 'tag_CycleUnit');
			$template->setMappedModule($this->name);

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
						'form_action'	=> backend_UrlMake($this->name, 'recurring_plans_save'),
						'cancel_action'	=> window_Close('paypal_recurring_plans_change')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save new or changed plan.
	 */
	private function savePlan() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = PayPal_PlansManager::getInstance();

		$data = array(
				'text_id'			=> escape_chars($_REQUEST['text_id']),
				'name'				=> $this->getMultilanguageField('name'),
				'trial'				=> fix_id($_REQUEST['trial_unit']),
				'trial_count'		=> fix_id($_REQUEST['trial_count']),
				'interval'			=> fix_id($_REQUEST['interval_unit']),
				'interval_count'	=> fix_id($_REQUEST['interval_count']),
				'price'				=> fix_chars($_REQUEST['interval_price']),
				'setup_price'		=> fix_chars($_REQUEST['setup_price']),
				'start_time'		=> fix_chars($_REQUEST['start_time'])
			);


		if (is_null($id)) {
			$window = 'paypal_recurring_plans_new';
			$manager->insertData($data);

		} else {
			$window = 'paypal_recurring_plans_change';
			$manager->updateData($data,	array('id' => $id));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_plan_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).';'.window_ReloadContent('paypal_recurring_plans'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation form for removing a plan.
	 */
	private function deletePlan() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = PayPal_PlansManager::getInstance();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant('message_plan_delete'),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->getLanguageConstant('delete'),
					'no_text'		=> $this->getLanguageConstant('cancel'),
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

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Mark plan as deleted.
	 */
	private function deletePlan_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = PayPal_PlansManager::getInstance();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_plan_deleted'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('paypal_recurring_plans_delete').';'.
								window_ReloadContent('paypal_recurring_plans')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Handle IPN.
	 */
	private function handleIPN() {
	}

	/**
	 * Complete express checkout.
	 */
	private function completeExpressCheckout() {
		$this->express_method->completeCheckout();
	}

	/**
	 * Tag handler for drawing a single plan.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Plan($tag_params, $children) {
		$manager = PayPal_PlansManager::getInstance();
		$conditions = array();

		$template = $this->loadTemplate($tag_params, 'plan.xml');

		$item = $manager->getSingleItem($manager->getFieldNames(), $conditions);

		if (is_object($item)) {
			$params = array(
				);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Tag handler for drawing multiple plans.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_PlanList($tag_params, $children) {
		$manager = PayPal_PlansManager::getInstance();
		$conditions = array();

		$template = $this->loadTemplate($tag_params, 'plans_list_item.xml');

		$items = $manager->getItems($manager->getFieldNames(), $conditions);

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
					'item_change'	=> url_MakeHyperlink(
											$this->getLanguageConstant('change'),
											window_Open(
												'paypal_recurring_plans_change', 	// window id
												405,				// width
												$this->getLanguageConstant('title_recurring_plans_change'), // title
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
											$this->getLanguageConstant('delete'),
											window_Open(
												'paypal_recurring_plans_delete', 	// window id
												400,				// width
												$this->getLanguageConstant('title_recurring_plans_delete'), // title
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

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}
}
