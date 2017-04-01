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

require_once('units/mailer.php');


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
			$mailer = new \Modules\SendGrid\Mailer(
					$this->language,
					$this->settings['api_key']
				);

			$contact_form = contact_form::get_instance();
			$contact_form->registerMailer('sendgrid', $mailer);
		}

		// register backend
		if (ModuleHandler::is_loaded('backend') && $section == 'backend') {
			$backend = backend::get_instance();

			$sendgrid_menu = new backend_MenuItem(
					$this->get_language_constant('menu_sendgrid'),
					$this->path.'images/icon.svg',
					window_Open( // on click open window
								'sendgrid_settings',
								370,
								$this->get_language_constant('title_settings'),
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
		$this->save_setting('api_key', '');
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
	}

	/**
	 * Show settings form.
	 */
	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
						'form_action'	=> backend_UrlMake($this->name, 'settings_save'),
						'cancel_action'	=> window_Close('sendgrid_settings')
					);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save new settings.
	 */
	private function save_settings() {
		// save setting
		$this->save_setting('api_key', trim(fix_chars($_REQUEST['api_key'])));

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('sendgrid_settings')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}
}
