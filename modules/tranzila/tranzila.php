<?php

/**
 * Tranzila Payment Support
 *
 * Copyright (c) 2012. by Way2CU
 * Author: Mladen Mijatov
 */
use Core\Module;


class tranzila extends Module {
	private static $_instance;
	private $method = null;

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

			if (!is_null($method_menu))
				$method_menu->addChild('', new backend_MenuItem(
									$this->get_language_constant('menu_tranzila'),
									$this->path.'images/icon.svg',
									window_Open( // on click open window
												'tranzila',
												400,
												$this->get_language_constant('title_settings'),
												true, true,
												backend_UrlMake($this->name, 'settings')
											),
									$level=5
								));
		}

		// integrate tranzila in to shop
		if (ModuleHandler::is_loaded('shop')) {
			// register payment method
			require_once('units/tranzila_payment_method.php');
			$this->method = Tranzila_PaymentMethod::get_instance($this);

			// add tranzila scripts to checkout page
			if ($section != 'backend') {
				$shop = shop::get_instance();
				$collection = collection::get_instance();

				$collection->includeScript(collection::DIALOG);
				$shop->addCheckoutScript(URL::from_file_path($this->path.'include/checkout.js'));
				$shop->addCheckoutStyle(URL::from_file_path($this->path.'include/checkout.css'));
			}
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
	public function transfer_control($params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'payment-confirm':
					if (!is_null($this->method))
						$this->method->handle_confirm_payment();
					break;

				case 'payment-cancel':
					if (!is_null($this->method))
						$this->method->handle_cancel_payment();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'settings':
					$this->showSettings();
					break;

				case 'settings_save':
					$this->save_settings();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function initialize() {
		$this->save_setting('terminal', '');
		$this->save_setting('terminal2', '');
		$this->save_setting('terminal_password', '');
		$this->save_setting('custom_template', '0');
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
	}

	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
				'form_action'	=> backend_UrlMake($this->name, 'settings_save'),
				'confirm_url'	=> URL::make_query($this->name, 'payment-confirm'),
				'cancel_url'	=> URL::make_query($this->name, 'payment-cancel'),
			);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	private function save_settings() {
		$terminal_name = fix_chars($_REQUEST['terminal']);
		$terminal2_name = fix_chars($_REQUEST['terminal2']);
		$terminal_password = fix_chars($_REQUEST['terminal_password']);
		$custom_template = $this->get_boolean_field('custom_template') ? 1 : 0;
		$this->save_setting('terminal', $terminal_name);
		$this->save_setting('terminal2', $terminal2_name);
		$this->save_setting('terminal_password', $terminal_password);
		$this->save_setting('custom_template', $custom_template);

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_settings_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('tranzila')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}
}
