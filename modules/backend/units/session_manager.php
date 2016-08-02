<?php

/**
 * Backend Session Manager
 */

class SessionManager {
	private static $_instance;

	private $parent;

	/**
	 * Constructor
	 */
	protected function __construct() {
		$this->parent = backend::getInstance();
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
	 * Transfer control to this object
	 */
	public function transferControl() {
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
		$manager = LoginRetryManager::getInstance();
		$show_captcha = false;

		// check if user has more than 3 failed atempts
		$show_captcha = $manager->getRetryCount() > 3;

		// create template and show login form
		$template = new TemplateHandler('session_login.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
					'show_captcha'	=> $show_captcha,
					'username'		=> isset($_REQUEST['username']) ? escape_chars($_REQUEST['username']) : '',
					'image'			=> url_GetFromFilePath($this->parent->path.'images/icons/login.png'),
					'message'		=> $message
				);

		$template->restoreXML();
		$template->setLocalParams($params);
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

		// get managers
		$manager = UserManager::getInstance();
		$retry_manager = LoginRetryManager::getInstance();

		// get retry count for client IP address
		$retry_count = $retry_manager->getRetryCount();

		// check captcha
		if ($retry_count > 3) {
			// on purpose we make a separate condition, if captcha
			// module is not loaded, block IP address for one day
			if (ModuleHandler::is_loaded('captcha')) {
				$captcha_module = captcha::getInstance();

				$captcha_ok = $captcha_module->isCaptchaValid($captcha);
				$captcha_module->resetCaptcha();
			}
		} else {
			$captcha_ok = true;
		}

		// check user data
		if ($manager->check_credentials($username, $password) && $captcha_ok) {
			// remove login retries
			$retry_manager->clearAddress();

			// reset session
			if ($lasting_session)
				Session::change_type(Session::TYPE_EXTENDED);

			// set session variables
			$manager->login_user($username);

			// check if we need to make redirect URL
			if (isset($_SESSION['redirect_url']))
				$url = $_SESSION['redirect_url']; else
				$url = url_Make('', $this->parent->name);
			url_SetRefresh($url, 2);

			// get message
			$message = $this->parent->getLanguageConstant('message_login_ok');

			// create template and show login form
			$template = new TemplateHandler('session_message.xml', $this->parent->path.'templates/');
			$template->setMappedModule($this->parent->name);

			$params = array(
						'message'		=> $message
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();

		} else {
			// user is not logged in properly, increase fail
			// counter and present login window with message
			$message = $this->parent->getLanguageConstant('message_login_error');
			$this->login($message);
			$retry_manager->increaseCount();
		}
	}

	/**
	 * Present confirmation dialog before logout
	 */
	private function logout() {
		$template = new TemplateHandler('confirmation.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
					'message'		=> $this->parent->getLanguageConstant('message_logout'),
					'name'			=> '',
					'yes_text'		=> $this->parent->getLanguageConstant('logout'),
					'no_text'		=> $this->parent->getLanguageConstant('cancel'),
					'yes_action'	=> window_LoadContent(
											'logout_window',
											backend_UrlMake($this->parent->name, 'logout_commit')
										),
					'no_action'		=> window_Close('logout_window')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Perform logout procedure
	 */
	private function logout_commit() {
		// change session type to default
		Session::change_type();

		// log user out
		UserManager::getInstance()->logout_user();

		// get message
		$message = $this->parent->getLanguageConstant('message_logout_ok');

		// get url
		$url = url_Make('', $this->parent->name);
		url_SetRefresh($url, 2);

		// load template and show the message
		$template = new TemplateHandler('session_message.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
					'message'		=> $message,
					'redirect_url'	=> $url
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Returns true if captcha needs to be shown
	 * @return boolean
	 */
	public function shouldShowCaptcha() {
		$manager = LoginRetryManager::getInstance();
		return $manager->getRetryCount() > 3;
	}

	/**
	 * Perform AJAX login
	 */
	private function json_Login() {
		$captcha_ok = false;
		$username = escape_chars($_REQUEST['username']);
		$password = escape_chars($_REQUEST['password']);
		$captcha = isset($_REQUEST['captcha']) ? escape_chars($_REQUEST['captcha']) : '';
		$lasting_session = $this->parent->get_boolean_field('lasting');

		$result = array(
				'logged_in'		=> false,
				'show_captcha'	=> false,
				'message'		=> ''
			);

		$manager = UserManager::getInstance();
		$retry_manager = LoginRetryManager::getInstance();

		// get number of retries for client IP address
		$retry_count = $retry_manager->getRetryCount();

		// check captcha
		if ($retry_count > 3) {
			// on purpose we make a separate condition, if captcha
			// module is not loaded, block IP address for one day
			if (ModuleHandler::is_loaded('captcha')) {
				$captcha_module = captcha::getInstance();

				$captcha_ok = $captcha_module->isCaptchaValid($captcha);
				$captcha_module->resetCaptcha();
			}
		} else {
			$captcha_ok = true;
		}

		// check if account is verified and verification is required
		$required = $this->parent->settings['require_verified'];
		$verified = ($required == 1 && $manager->is_user_verified($username)) || $required == 0;

		// check user credentials
		$credentials_ok = $manager->check_credentials($username, $password);

		// check user data
		if ($verified && $credentials_ok && $captcha_ok) {
			// remove login retries
			$retry_manager->clearAddress();

			// change session type
			if ($lasting_session)
				Session::change_type(Session::TYPE_EXTENDED);

			// set session variables
			$manager->login_user($username);

			// return message
			$result['message'] = $this->parent->getLanguageConstant('message_login_ok');
			$result['logged_in'] = true;

		} elseif ($credentials_ok && $captcha_ok && !$verified) {
			// user is logged but account is not verified
			$result['message'] = $this->parent->getLanguageConstant('message_users_account_not_verified');

		} else {
			// user is not logged in properly, increase fail
			// counter and present login window with message
			$count = $retry_manager->increaseCount();

			$result['message'] = $this->parent->getLanguageConstant('message_login_error');
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
		UserManager::getInstance()->logout_user();

		// get message
		$message = $this->parent->getLanguageConstant('message_logout_ok');
		$result = array(
			'logged_in'	=> false,
			'message'	=> $message
		);

		print json_encode($result);
	}
}

?>
