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
					'username'		=> isset($_REQUEST['username']) ? fix_chars($_REQUEST['username']) : '',
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
		$username = fix_chars($_REQUEST['username']);
		$password = fix_chars($_REQUEST['password']);
		$captcha = isset($_REQUEST['captcha']) ? fix_chars($_REQUEST['captcha']) : '';
		$lasting_session = isset($_REQUEST['lasting']) && ($_REQUEST['lasting'] == 'on' || $_REQUEST['lasting'] == '1') ? true : false;

		// get managers
		$manager = UserManager::getInstance();
		$retry_manager = LoginRetryManager::getInstance();

		// prepare hashed password
		$test_user = $manager->getSingleItem(
									array('salt'),
									array('username' => $username)
								);

		if (is_object($test_user) && !empty($test_user->salt)) {
			// hash password using stored salt
			$hashed_password = hash_hmac('sha256', $password, $test_user->salt);

		} else {
			// old salting method
			$hashed_password = hash_hmac('sha256', $password, UserManager::SALT);
		}

		// get user based with password
		$user = $manager->getSingleItem(
									$manager->getFieldNames(),
									array(
										'username'	=> $username,
										'password'	=> array($password, $hashed_password)
									));

		$retry_count = $retry_manager->getRetryCount();

		// check captcha
		if ($retry_count > 3) {
			// on purpose we make a separate condition, if captcha
			// module is not loaded, block IP address for one day
			if (class_exists('captcha')) {
				$captcha_module = captcha::getInstance();

				$captcha_ok = $captcha_module->isCaptchaValid($captcha);
				$captcha_module->resetCaptcha();
			}
		} else {
			$captcha_ok = true;
		}

		// check user data
		if (is_object($user) && $captcha_ok) {
			// remove login retries
			$retry_manager->deleteData(array('address' => $_SERVER['REMOTE_ADDR']));

			// reset session
			if ($lasting_session)
				Session::change_type(Session::TYPE_EXTENDED);

			// set session variables
			$_SESSION['uid'] = $user->id;
			$_SESSION['logged'] = true;
			$_SESSION['level'] = $user->level;
			$_SESSION['username'] = $user->username;
			$_SESSION['fullname'] = $user->fullname;

			// check if we need to make redirect URL
			if (isset($_SESSION['redirect_url']))
				$url = url_SetRefresh($_SESSION['redirect_url'], 2); else
				$url = url_SetRefresh(url_Make('', $this->parent->name), 2);

			// get message
			$message = $this->parent->getLanguageConstant('message_login_ok');

			// create template and show login form
			$template = new TemplateHandler('session_message.xml', $this->parent->path.'templates/');
			$template->setMappedModule($this->parent->name);

			$params = array(
						'message'		=> $message,
						'redirect_url'	=> $url
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

		// kill session variables
		unset($_SESSION['uid']);
		unset($_SESSION['logged']);
		unset($_SESSION['level']);
		unset($_SESSION['username']);
		unset($_SESSION['fullname']);

		// get message
		$message = $this->parent->getLanguageConstant('message_logout_ok');

		// get url
		$url = url_SetRefresh(url_Make('', $this->parent->name), 2);

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
		$username = fix_chars($_REQUEST['username']);
		$password = fix_chars($_REQUEST['password']);
		$captcha = isset($_REQUEST['captcha']) ? fix_chars($_REQUEST['captcha']) : '';
		$lasting_session = isset($_REQUEST['lasting']) && ($_REQUEST['lasting'] == 'on' || $_REQUEST['lasting'] == '1') ? true : false;

		$result = array(
				'logged_in'		=> false,
				'show_captcha'	=> false,
				'message'		=> ''
			);

		$manager = UserManager::getInstance();
		$retry_manager = LoginRetryManager::getInstance();

		// prepare hashed password
		$test_user = $manager->getSingleItem(
									array('salt'),
									array('username' => $username)
								);

		if (is_object($test_user) && !empty($test_user->salt)) {
			// hash password using stored salt
			$hashed_password = hash_hmac('sha256', $password, $test_user->salt);

		} else {
			// old salting method
			$hashed_password = hash_hmac('sha256', $password, UserManager::SALT);
		}

		// get user based with password
		$user = $manager->getSingleItem(
									$manager->getFieldNames(),
									array(
										'username'	=> $username,
										'password'	=> array($password, $hashed_password)
									));

		$retry_count = $retry_manager->getRetryCount();

		// check captcha
		if ($retry_count > 3) {
			// on purpose we make a separate condition, if captcha
			// module is not loaded, block IP address for one day
			if (class_exists('captcha')) {
				$captcha_module = captcha::getInstance();

				$captcha_ok = $captcha_module->isCaptchaValid($captcha);
				$captcha_module->resetCaptcha();
			}
		} else {
			$captcha_ok = true;
		}

		// check if account is verified and verification is required
		if (is_object($user)) {
			$required = $this->parent->settings['require_verified'];
			$verified = ($required == 1 && $user->verified) || $required == 0;
		}

		// check user data
		if (is_object($user) && $captcha_ok && $verified) {
			// remove login retries
			$retry_manager->clearAddress();

			// change session type
			if ($lasting_session)
				Session::change_type(Session::TYPE_EXTENDED);

			// set session variables
			$_SESSION['uid'] = $user->id;
			$_SESSION['logged'] = true;
			$_SESSION['level'] = $user->level;
			$_SESSION['username'] = $user->username;
			$_SESSION['fullname'] = $user->fullname;

			$result['logged_in'] = true;

		} elseif (is_object($user) && $captcha_ok && !$verified) {
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
		unset($_SESSION['uid']);
		unset($_SESSION['logged']);
		unset($_SESSION['level']);
		unset($_SESSION['username']);
		unset($_SESSION['fullname']);

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
