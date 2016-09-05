<?php

/**
 * Module Template
 *
 * This module is a template to make process of starting a development of new module
 * fast and painless. This code reflects the state of system in general and should be
 * kept up-to-date with remainder of the system.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;

require_once('units/mailer.php');
require_once(_LIBPATH.'mandrill/mandrill.php');


class mandrill extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// register mailer
		if (ModuleHandler::is_loaded('contact_form')) {
			$mailer = new Mandrill_Mailer($this->language, $this->settings['api_key']);

			$contact_form = contact_form::getInstance();
			$contact_form->registerMailer('mandrill', $mailer);
		}

		// register backend
		if (ModuleHandler::is_loaded('backend') && $section == 'backend') {
			$backend = backend::getInstance();

			$mandrill_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_mandrill'),
					url_GetFromFilePath($this->path.'images/icon.svg'),
					window_Open( // on click open window
								'mandrill_settings',
								370,
								$this->getLanguageConstant('title_settings'),
								true, true,
								backend_UrlMake($this->name, 'settings')
							),
					$level=6
				);

			$backend->addMenu($this->name, $mandrill_menu);
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
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'settings':
					$this->showSettings();
					break;

				case 'settings_save':
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
		$this->saveSetting('api_key', '');
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
	}

	/**
	 * Show settings form.
	 */
	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
						'form_action'	=> backend_UrlMake($this->name, 'settings_save'),
						'cancel_action'	=> window_Close('mandrill_settings')
					);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save new settings.
	 */
	private function saveSettings() {
		// save setting
		$this->saveSetting('api_key', trim(fix_chars($_REQUEST['api_key'])));

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('mandrill_settings')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}
}
