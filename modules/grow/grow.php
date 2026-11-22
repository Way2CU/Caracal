<?php

/**
 * Grow Payment Support
 *
 * Integrates Grow (formerly Meshulam) hosted payment page with the shop
 * module. Buyer is redirected to the page created through Grow API and,
 * once payment is done, Grow notifies the server and sends buyer back.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;

require_once('units/api.php');
require_once('units/transaction_manager.php');


class grow extends Module {
	private static $_instance;
	private $method = null;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);

		// select api environment
		Grow_Api::set_sandbox(isset($this->settings['sandbox']) && $this->settings['sandbox'] == 1);

		// register backend
		if (ModuleHandler::is_loaded('backend') && ModuleHandler::is_loaded('shop')) {
			$backend = backend::get_instance();
			$method_menu = $backend->getMenu('shop_payment_methods');

			if (!is_null($method_menu))
				$method_menu->addChild('', new backend_MenuItem(
									$this->get_language_constant('menu_grow'),
									$this->path.'images/icon.svg',
									window_Open( // on click open window
												'grow',
												400,
												$this->get_language_constant('title_settings'),
												true, true,
												backend_UrlMake($this->name, 'settings')
											),
									$level=5
								));
		}

		// register payment method
		if (ModuleHandler::is_loaded('shop')) {
			require_once('units/grow_payment_method.php');
			$this->method = Grow_PaymentMethod::get_instance($this);
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
		if (isset($params['action']) && !is_null($this->method))
			switch ($params['action']) {
				case 'payment-start':
					$this->method->handle_start_payment();
					break;

				case 'payment-confirm':
					$this->method->handle_confirm_payment();
					break;

				case 'payment-cancel':
					$this->method->handle_cancel_payment();
					break;

				case 'payment-notify':
					$this->method->handle_notification();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'settings':
					$this->show_settings();
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
		global $db;

		$this->save_setting('user_id', '');
		$this->save_setting('page_code', '');
		$this->save_setting('sandbox', '1');
		$this->save_setting('max_payments', '1');

		// create tables
		$sql = Query::load_file('transactions.sql', $this);
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
		global $db;

		$db->drop_tables(array('grow_transactions'));
	}

	/**
	 * Show settings form.
	 */
	private function show_settings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
				'form_action' => backend_UrlMake($this->name, 'settings_save'),
				'notify_url'  => is_null($this->method) ? '' : $this->method->get_callback_url('payment-notify')
			);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save settings.
	 */
	private function save_settings() {
		$user_id = fix_chars($_REQUEST['user_id']);
		$page_code = fix_chars($_REQUEST['page_code']);
		$sandbox = $this->get_boolean_field('sandbox') ? 1 : 0;
		$max_payments = isset($_REQUEST['max_payments']) ? (int) fix_id($_REQUEST['max_payments']) : 1;
		$max_payments = max(1, min(Grow_PaymentMethod::MAX_PAYMENTS, $max_payments));

		$this->save_setting('user_id', $user_id);
		$this->save_setting('page_code', $page_code);
		$this->save_setting('sandbox', $sandbox);
		$this->save_setting('max_payments', $max_payments);

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message' => $this->get_language_constant('message_settings_saved'),
					'button'  => $this->get_language_constant('close'),
					'action'  => window_Close('grow')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}
}

?>
