<?php

/**
 * SESSION MODULE
 *
 * @author MeanEYE
 * @copyright RCF Group,2008.
 */

if (!defined('_DOMAIN') || _DOMAIN !== 'RCF_WebEngine') die ('Direct access to this file is not allowed!');


class session extends Module {

	/**
	 * Constructor
	 *
	 * @return backend
	 */
	function session() {
		$this->file = __FILE__;
		parent::Module();
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param string $action
	 * @param integer $level
	 */
	function transferControl($level, $params = array(), $children=array()) {
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
	function performLogin() {
		global $db, $db_active, $ModuleHandler, $language;

		$username = fix_chars($_REQUEST['username']);
		$password = fix_chars($_REQUEST['password']);
		$captchaValue = fix_chars($_REQUEST['captcha']);

		$captchaPass = false;
		if ($ModuleHandler->moduleExists('captcha')) {
			$captcha = $ModuleHandler->getObjectFromName('captcha');
			$captchaPass = $captcha->isCaptchaValid($captchaValue);
		}

		$user = $db->get_row("SELECT `username`, `fullname`, `level` FROM `system_access` WHERE `username`='$username' AND `password`='$password' LIMIT 1");
		if ($db->num_rows > 0 && $captchaPass) {
			// user logged, publish data and redirect
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
	 * Event called upon module registration
	 */
	function onRegister() {
	}

    /**
     * Loggs out current user
     */
    function performLogout() {
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
