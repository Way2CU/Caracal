<?php

/**
 * Callbox
 *
 * Support for Callbox service.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;


class callbox extends Module {
	private static $_instance;

	const URL_API = 'ssl://api.calltrackingmetrics.com';
	const URL_FORM_REACTOR = '/api/v1/formreactor/{reactor_id}';

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// register backend
		if (ModuleHandler::is_loaded('backend')) {
			$backend = backend::get_instance();

			$callbox_menu = new backend_MenuItem(
					$this->get_language_constant('menu_callbox'),
					URL::from_file_path($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);

			$callbox_menu->addChild('', new backend_MenuItem(
								$this->get_language_constant('menu_settings'),
								URL::from_file_path($this->path.'images/settings.svg'),

								window_Open( // on click open window
											'callbox_settings',
											400,
											$this->get_language_constant('title_settings'),
											true, true,
											backend_UrlMake($this->name, 'settings')
										),
								$level=5
							));

			$backend->addMenu($this->name, $callbox_menu);
		}

		// include scripts
		if (ModuleHandler::is_loaded('head_tag') && $section != 'backend' && $this->settings['include_code']) {
			$head_tag = head_tag::get_instance();

			$url = str_replace('{id}', $this->settings['account_id'], 'https://{id}.tctm.co/t.js');
			$head_tag->addTag('script',
						array(
							'src'	=> $url,
							'type'	=> 'text/javascript',
							'async'	=> ''
						));
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
				case 'json_formreactor':
					$this->json_FormReactor();
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
		$this->save_setting('account_id', '');
		$this->save_setting('account_key', '');
		$this->save_setting('account_secret', '');
		$this->save_setting('include_code', 0);
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
						'cancel_action'	=> window_Close('callbox_settings')
					);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save settings.
	 */
	private function save_settings() {
		// grab parameters
		$account_id = fix_chars($_REQUEST['account_id']);
		$account_key = fix_chars($_REQUEST['account_key']);
		$account_secret = fix_chars($_REQUEST['account_secret']);
		$include_code = $this->get_boolean_field('include_code') ? 1 : 0;

		$this->save_setting('account_id', $account_id);
		$this->save_setting('account_key', $account_key);
		$this->save_setting('account_secret', $account_secret);
		$this->save_setting('include_code', $include_code);

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('callbox_settings')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Submit form reactor data to the server.
	 */
	private function json_FormReactor() {
		$result = false;
		$reactor_id = fix_chars($_REQUEST['reactor_id']);
		$visitor_sid = fix_chars($_REQUEST['visitor_sid']);
		$account_key = $this->settings['account_key'];
		$account_secret = $this->settings['account_secret'];

		// exit if we are missing data
		if (empty($account_key) || empty($account_secret)) {
			trigger_error('Account key and/or secret are not properly configured!', E_USER_ERROR);
			print json_encode($result);
			return;
		}

		// prepare content
		$params = array(
				'phone_number',
				'country_code',
				'caller_name'
			);
		$data = array();
		$strip_slashes = get_magic_quotes_gpc();

		foreach($params as $param) {
			$value = $_REQUEST[$param];

			if ($strip_slashes)
				$value = stripslashes($value);

			$data[] = $param.'='.rawurlencode($value);
		}

		// add visitor session id
		$data['visitor_sid'] = $visitor_sid;

		// make query string
		$content = implode('&', $data);

		// prepare headers
		$api_path = str_replace('{reactor_id}', $reactor_id, callbox::URL_FORM_REACTOR);
		$header = "POST ".$api_path." HTTP/1.0\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\n";
		$header .= "Content-Length: ".strlen($content)."\n";
		$header .= "Authorization: Basic ".base64_encode($account_key.':'.$account_secret)."\n";
		$header .= "Connection: close\n\n";

		// connect to server and send data
		$socket = fsockopen(callbox::URL_API, 443, $error_number, $error_string, 30);

		if ($socket) {
			fputs($socket, $header.$content);
			$response = fgets($socket);
			$result = strpos($response, '200 OK') != false;
		}

		fclose($socket);

		print json_encode($result);
	}
}
