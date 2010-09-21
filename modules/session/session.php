<?php

/**
 * Session Module
 *
 * @author MeanEYE.rcf
 * @todo Merge with backend module
 */

class session extends Module {
	private static $_instance;
	
	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);
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
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($level, $params = array(), $children=array()) {
		switch ($params['action']) {
			case 'print_login':
				$template = new TemplateHandler('login.xml', $this->path.'templates/');
				$template->setMappedModule($this->name);
				$template->parse($level);
				break;

			case 'login_perform':
				$this->performLogin();
				break;

			case 'logout':
                $this->performLogout();
				break;
		}
	}

	/**
	 * Perfrom credetials check for user
	 */
	private function performLogin() {
		global $language;

		$username = fix_chars($_REQUEST['username']);
		$password = fix_chars($_REQUEST['password']);
		$captchaValue = fix_chars($_REQUEST['captcha']);
		$manager = AdministratorManager::getInstance();

		$captchaPass = false;
		if (class_exists('captcha')) {
			$captcha = captcha::getInstance();
			$captchaPass = $captcha->isCaptchaValid($captchaValue);
		}

		$user = $manager->getSingleItem(
								$manager->getFieldNames(),
								array(
									'username'	=> $username,
									'password'	=> $password
								)
							);

		if (is_object($user) && $captchaPass) {
			// user logged, publish data and redirect
			$_SESSION['uid'] = $user->id;
			$_SESSION['logged'] = true;
			$_SESSION['level'] = $user->level;
			$_SESSION['username'] = $user->username;
			$_SESSION['fullname'] = $user->fullname;
			$captcha->resetCaptcha();

			echo '<img src="modules/backend/images/icons/success.png" alt=""/>';
			echo $this->language->getText('message_login_ok', $language);

			url_SetRefresh(isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : url_Make('', 'backend'));
			if (isset($_SESSION['redirect_url'])) unset($_SESSION['redirect_url']);

		} else {
			// user not logged, print error message, ensure data is correct
			$_SESSION['logged'] = false;
			$_SESSION['level'] = 0;
			$captcha->resetCaptcha();

			echo '<img src="modules/backend/images/icons/error.png" alt=""/>';
			echo $this->language->getText('message_login_error', $language);
		}
	}

    /**
     * Loggs out current user
     */
    private function performLogout() {
        global $language;

        unset($_SESSION['username']);
        unset($_SESSION['fullname']);
        $_SESSION['level'] = 0;
        $_SESSION['logged'] = false;

        echo '<img src="modules/backend/images/icons/success.png" alt=""/>';
        echo $this->language->getText('message_logout', $language);
        url_SetRefresh(url_Make('', ''), 2);
    }
}

?>
