<?php

/**
 * FedEx Integration
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */
use Core\Module;


class fedex extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// register backend
		if (ModuleHandler::is_loaded('backend') && ModuleHandler::is_loaded('shop')) {
			$backend = backend::get_instance();
			$method_menu = $backend->getMenu('shop_delivery_methods');

			if (!is_null($method_menu))
				$method_menu->addChild('', new backend_MenuItem(
									$this->get_language_constant('menu_fedex'),
									$this->path.'images/icon.svg',
									window_Open( // on click open window
												'fedex',
												350,
												$this->get_language_constant('title_settings'),
												true, true,
												backend_UrlMake($this->name, 'settings')
											),
									$level=5
								));
		}

		// register delivery method
		if (ModuleHandler::is_loaded('shop')) {
			require_once('units/fedex_delivery_method.php');
			FedEx_DeliveryMethod::get_instance($this);
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
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'settings':
					$this->showSettings();
					break;

				case 'save_settings':
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
		global $db;

		$sql = "";

		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
		global $db;

		$sql = "";

		$db->query($sql);
	}

	/**
	 * Show settings edit form.
	 */
	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
						'form_action'	=> backend_UrlMake($this->name, 'save_settings')
					);

		$template->set_local_params($params);
		$template->restore_xml();
		$template->parse();
	}

	/**
	 * Save settings.
	 */
	private function save_settings() {
		$key = fix_chars($_REQUEST['key']);
		$password = fix_chars($_REQUEST['password']);
		$account = fix_chars($_REQUEST['account']);
		$meter = fix_chars($_REQUEST['meter']);

		$this->save_setting('fedex_key', $key);
		$this->save_setting('fedex_password', $password);
		$this->save_setting('fedex_account', $account);
		$this->save_setting('fedex_meter', $meter);

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_settings_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('fedex')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}
}
