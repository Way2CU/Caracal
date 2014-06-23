<?php

/**
 * Stripe Payment Implementation
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */
use Core\Module;

require_once('library/Stripe.php');
require_once('units/stripe_payment_method.php');


class stripe_payment extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;
		
		parent::__construct(__FILE__);

		// load module style and scripts
		if (class_exists('head_tag')) {
			$head_tag = head_tag::getInstance();
			$head_tag->addTag('script', array('src' => 'https://js.stripe.com/v2/', 'type' => 'text/javascript'));
		}

		// register backend
		if (class_exists('backend') && class_exists('shop')) {
			$backend = backend::getInstance();
			$method_menu = $backend->getMenu('shop_payment_methods');

			if (!is_null($method_menu)) 
				$method_menu->addChild('', new backend_MenuItem(
									$this->getLanguageConstant('menu_stripe'),
									url_GetFromFilePath($this->path.'images/icon.png'),

									window_Open( // on click open window
												'stripe',
												350,
												$this->getLanguageConstant('title_settings'),
												true, true,
												backend_UrlMake($this->name, 'show_settings')
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
				case 'charge_token':
					$this->chargeToken();
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

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
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
	 * Charge specified amount with specified token and transaction.
	 */
	public function chargeToken() {
		$transaction_uid = fix_chars($_REQUEST['transaction_uid']);
		$stripe_token = fix_chars($_REQUEST['stripe_token']);
		$manager = ShopTransactionsManager::getInstance();
		$currency_manager = ShopCurrenciesManager::getInstance();
		$transaction = null;

		// make sure we are working on same transaction for current user
		if (isset($_SESSION['transaction']) && $_SESSION['transaction']['uid'] == $transaction_uid) 
			$transaction = $manager->getSingleItem($manager->getFieldNames(), array('uid' => $transaction_uid));

		if (is_object($transaction)) {
			$currency = $currency_manager->getSingleItem(array('currency'), array('id' => $transaction->currency));

			try {
				// create charge
				Stripe::setApiKey($this->getPrivateKey());
				$charge = Stripe_Charge::create(array(
								'amount'		=> $transaction->total * 100,
								'currency'		=> $currency->currency,
								'card'			=> $stripe_token,
								'description'	=> null
							));
			} catch (Stripe_CardError $error) {
			}

			// update transaction status
			if (is_object($charge) && $charge->paid) {
				$shop = shop::getInstance();
				$shop->setTransactionToken($transaction_uid, $charge->id);
				$shop->setTransactionStatus($transaction_uid, TransactionStatus::COMPLETED);
			}
		}
	}
}
