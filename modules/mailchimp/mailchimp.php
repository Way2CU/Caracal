<?php

/**
 * MailChimp
 *
 * Support for mailing campaigns through MailChimp API.
 *
 * Author: Mladen Mijatov
 */

require_once(_LIBPATH.'mailchimp/mailchimp.php');

use Core\Module;
use Library\Mailchimp\Mailchimp as API;


class mailchimp extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// register backend
		if ($section == 'backend' && ModuleHandler::is_loaded('backend')) {
			$backend = backend::get_instance();

			$mailchimp_menu = new backend_MenuItem(
					$this->get_language_constant('menu_mailchimp'),
					URL::from_file_path($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);

			$mailchimp_menu->addChild('', new backend_MenuItem(
								$this->get_language_constant('menu_lists'),
								URL::from_file_path($this->path.'images/lists.svg'),
								window_Open( // on click open window
											'mailchimp_lists',
											450,
											$this->get_language_constant('title_lists'),
											true, true,
											backend_UrlMake($this->name, 'lists')
										),
								$level=5
							));
			$mailchimp_menu->addSeparator(5);

			$mailchimp_menu->addChild('', new backend_MenuItem(
								$this->get_language_constant('menu_settings'),
								URL::from_file_path($this->path.'images/settings.svg'),
								window_Open( // on click open window
											'mailchimp_settings',
											450,
											$this->get_language_constant('title_settings'),
											true, true,
											backend_UrlMake($this->name, 'settings')
										),
								$level=5
							));

			$backend->addMenu($this->name, $mailchimp_menu);
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
				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'lists':
					$this->showLists();
					break;

				case 'settings':
					$this->showSettings();
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

		$this->save_setting('api_key', '');
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
		global $db;
	}

	/**
	 * Show configuration form.
	 */
	private function showSettings() {
	}

	/**
	 * Save new configuration.
	 */
	private function save_settings() {
	}

	/**
	 * Update local list cache with data from MailChimp.
	 */
	private function updateLists() {
		$api_key = $this->settings['api_key'];

		// make sure API key is configured
		if (empty($api_key)) {
			trigger_error('MailChimp: Unable to update! Empty API key.', E_USER_WARNING);
			return;
		}

		// create object for communication
		$api = new API($api_key);
	}

	/**
	 * Show mailing lists window.
	 */
	private function showLists() {
		// update list
		$this->updateLists();

		// create template
		$template = new TemplateHandler('lists.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		// create menu links

		// create template params
		$params = array(
		);

		// register tag handlers
		$template->register_tag_handler('cms:items', $this, 'tag_ListItems');

		// show template
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}
}
