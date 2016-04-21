<?php

/**
 * SendGrid
 *
 * Integration of SendGrid email and template service into contact form. This module
 * relies on SendGrid library being present on the system.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;

/* require_once('units/mailer.php'); */


class sendgrid extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// register mailer
		if (ModuleHandler::is_loaded('contact_form')) {
			/* $mailer = new \Modules\SendGrid\Mailer( */
			/* 		$this->language, */
			/* 		$this->settings['api_key'] */
			/* 	); */

			/* $contact_form = contact_form::getInstance(); */
			/* $contact_form->registerMailer('sendgrid', $mailer); */
		}

		// register backend
		if (ModuleHandler::is_loaded('backend') && $section == 'backend') {
			$backend = backend::getInstance();

			$sendgrid_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_sendgrid'),
					url_GetFromFilePath($this->path.'images/icon.svg'),
					window_Open( // on click open window
								'sendgrid_settings',
								370,
								$this->getLanguageConstant('title_settings'),
								true, true,
								backend_UrlMake($this->name, 'settings')
							),
					$level=6
				);

			$backend->addMenu($this->name, $sendgrid_menu);
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
		$this->saveSetting('api_key', fix_chars($_REQUEST['api_key']));

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
