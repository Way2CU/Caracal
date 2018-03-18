<?php

/**
 * Backend Session Manager
 */

use Core\Session\Manager as Session;
use Core\Session\Type as SessionType;


class SessionManager {
	private static $_instance;

	private $parent;

	/**
	 * Constructor
	 */
	protected function __construct() {
		$this->parent = backend::get_instance();
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
	 * Transfer control to this object
	 */
	public function transfer_control() {
		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;

		if (!is_null($action) && $action == 'transfer_control')
			$action = $_REQUEST['backend_action'];

		switch($action) {
			case 'login_commit':
				$this->login_commit();
				break;

			case 'logout':
				$this->logout();
				break;

			case 'logout_commit':
				$this->logout_commit();
				break;

			case 'json_login':
				$this->json_Login();
				break;

			case 'json_logout':
				$this->json_Logout();
				break;

			default:
				$this->login();
				break;
		}
	}

	/**
	 * Show login form
	 *
	 * @param string $message
	 */
	private function login($message='') {
		$manager = LoginRetryManager::get_instance();
		$show_captcha = false;

		// check if user has more than 3 failed atempts
		$show_captcha = $manager->getRetryCount() > 3;

		if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'login') {
			// create template and show login form
			$template = new TemplateHandler('session_login.xml', $this->parent->path.'templates/');
			$params = array(
						'show_captcha'	=> $show_captcha,
						'username'		=> isset($_REQUEST['username']) ? escape_chars($_REQUEST['username']) : '',
						'image'			=> URL::from_file_path($this->parent->path.'images/icons/login.png'),
						'message'		=> $message
					);

		} else {
			// we were previously logged in, show new login window
			$template = new TemplateHandler('session_show_login.xml', $this->parent->path.'templates/');
			$template->set_mapped_module($this->parent->name);
			$params = array();
		}

		$template->set_mapped_module($this->parent->name);
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Perform login
	 */
	private function login_commit() {
		$captcha_ok = false;
		$username = escape_chars($_REQUEST['username']);
		$password = escape_chars($_REQUEST['password']);
		$captcha = isset($_REQUEST['captcha']) ? escape_chars($_REQUEST['captcha']) : '';
		$lasting_session = $this->parent->get_boolean_field('lasting');
		$login_params = array('username' => $username, 'password' => $password);

		// get managers
		$manager = UserManager::get_instance();
		$retry_manager = LoginRetryManager::get_instance();

		// get retry count for client IP address
		$retry_count = $retry_manager->getRetryCount();

		// check captcha
		if ($retry_count > 3) {
			// on purpose we make a separate condition, if captcha
			// module is not loaded, block IP address for one day
			if (ModuleHandler::is_loaded('captcha')) {
				$captcha_module = captcha::get_instance();

				$captcha_ok = $captcha_module->isCaptchaValid($captcha);
				$captcha_module->resetCaptcha();
			}
		} else {
			$captcha_ok = true;
		}

		// check user data
		if ($captcha_ok && Session::login($login_params) != null) {
			// remove login retries
			$retry_manager->clearAddress();

			// reset session and set new type
			if ($lasting_session)
				Session::change_type(SessionType::EXTENDED);

			// check if we need to make redirect URL
			if (isset($_SESSION['redirect_url']))
				$url = $_SESSION['redirect_url']; else
				$url = URL::make_query($this->parent->name, '');
			URL::set_refresh($url, 2);

			// get message
			$message = $this->parent->get_language_constant('message_login_ok');

			// create template and show login form
			$template = new TemplateHandler('session_message.xml', $this->parent->path.'templates/');
			$template->set_mapped_module($this->parent->name);

			$params = array(
						'message' => $message
					);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();

		} else {
			// user is not logged in properly, increase fail
			// counter and present login window with message
			$message = $this->parent->get_language_constant('message_login_error');
			$this->login($message);
			$retry_manager->increaseCount();
		}
	}

	/**
	 * Present confirmation dialog before logout
	 */
	private function logout() {
		$template = new TemplateHandler('confirmation.xml', $this->parent->path.'templates/');
		$template->set_mapped_module($this->parent->name);

		$params = array(
					'message'		=> $this->parent->get_language_constant('message_logout'),
					'name'			=> '',
					'yes_text'		=> $this->parent->get_language_constant('logout'),
					'no_text'		=> $this->parent->get_language_constant('cancel'),
					'yes_action'	=> window_LoadContent(
											'logout_window',
											backend_UrlMake($this->parent->name, 'logout_commit')
										),
					'no_action'		=> window_Close('logout_window')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Perform logout procedure
	 */
	private function logout_commit() {
		// change session type to default
		Session::change_type();

		// log user out
		UserManager::get_instance()->logout_user();

		// get message
		$message = $this->parent->get_language_constant('message_logout_ok');

		// get url
		$url = URL::make_query($this->parent->name, '');
		URL::set_refresh($url, 2);

		// load template and show the message
		$template = new TemplateHandler('session_message.xml', $this->parent->path.'templates/');
		$template->set_mapped_module($this->parent->name);

		$params = array(
					'message'		=> $message,
					'redirect_url'	=> $url
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Returns true if captcha needs to be shown
	 * @return boolean
	 */
	public function shouldShowCaptcha() {
		$manager = LoginRetryManager::get_instance();
		return $manager->getRetryCount() > 3;
	}

	/**
	 * Perform AJAX login
	 */
	private function json_Login() {
		$captcha_ok = false;
		$username = escape_chars($_REQUEST['username']);
		$password = escape_chars($_REQUEST['password']);
		$login_params = array('username' => $username, 'password' => $password);
		$captcha = isset($_REQUEST['captcha']) ? escape_chars($_REQUEST['captcha']) : '';
		$lasting_session = $this->parent->get_boolean_field('lasting');

		$result = array(
				'logged_in'		=> false,
				'show_captcha'	=> false,
				'message'		=> ''
			);

		$manager = UserManager::get_instance();
		$retry_manager = LoginRetryManager::get_instance();

		// get number of retries for client IP address
		$retry_count = $retry_manager->getRetryCount();

		// check captcha
		if ($retry_count > 3) {
			// on purpose we make a separate condition, if captcha
			// module is not loaded, block IP address for one day
			if (ModuleHandler::is_loaded('captcha')) {
				$captcha_module = captcha::get_instance();

				$captcha_ok = $captcha_module->isCaptchaValid($captcha);
				$captcha_module->resetCaptcha();
			}
		} else {
			$captcha_ok = true;
		}

		// check if account is verified and verification is required
		$required = $this->parent->settings['require_verified'];
		$verified = ($required == 1 && $manager->is_user_verified($username)) || $required == 0;

		// try to log user in
		$credentials_ok = Session::login($login_params) != null;

		// check user data
		if ($verified && $credentials_ok && $captcha_ok) {
			// remove login retries
			$retry_manager->clearAddress();

			// change session type
			if ($lasting_session)
				Session::change_type(SessionType::EXTENDED);

			// return message
			$result['message'] = $this->parent->get_language_constant('message_login_ok');
			$result['logged_in'] = true;

		} elseif ($credentials_ok && $captcha_ok && !$verified) {
			// user is logged but account is not verified
			$result['message'] = $this->parent->get_language_constant('message_users_account_not_verified');

		} else {
			// user is not logged in properly, increase fail
			// counter and present login window with message
			$count = $retry_manager->increaseCount();

			$result['message'] = $this->parent->get_language_constant('message_login_error');
			$result['show_captcha'] = $count > 3;
		}

		print json_encode($result);
	}

	/**
	 * Perform AJAX logout
	 */
	private function json_Logout() {
		// change session type to default
		Session::change_type();

		// kill session variables
		UserManager::get_instance()->logout_user();

		// get message
		$message = $this->parent->get_language_constant('message_logout_ok');
		$result = array(
			'logged_in'	=> false,
			'message'	=> $message
		);

		print json_encode($result);
	}
}

?>
