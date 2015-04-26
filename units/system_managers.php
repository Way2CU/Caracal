<?php

/**
 * System Managers
 *
 * Author: Mladen Mijatov
 */


class UserExistsError extends Exception {}
class InvalidUserError extends Exception {}


class ModuleManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_modules');

		$this->addProperty('id', 'int');
		$this->addProperty('order', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('preload', 'int');
		$this->addProperty('active', 'int');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}


final class UserManager extends ItemManager {
	private static $_instance;
	const SALT = '5sWeaGqp53loh7hYFDEjBi6VHMYDznrx5ITUF9Bzni7WXU9IJOBmr/80u2vjklSfhK+lvPBel/T9';

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_access');

		$this->addProperty('id', 'int');
		$this->addProperty('username', 'varchar');
		$this->addProperty('password', 'varchar');
		// TODO: Get rid of `fullname` sometime.
		$this->addProperty('fullname', 'varchar');
		$this->addProperty('first_name', 'varchar');
		$this->addProperty('last_name', 'varchar');
		$this->addProperty('email', 'varchar');
		$this->addProperty('level', 'int');
		$this->addProperty('verified', 'boolean');
		$this->addProperty('agreed', 'boolean');
		$this->addProperty('salt', 'char');
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
	 * Change specified user's password.
	 *
	 * @param string $username
	 * @param string $new_password
	 * @return boolean
	 * @throws InvalidUserError
	 */
	public function change_password($username, $new_password) {
		$result = false;

		// get user
		$user = $this->getSingleItem(array('id'), array('username' => $username));
		if (!is_object($user))
			throw new InvalidUserError('Unable to change password!');

		// prepare password
		$salt = hash('sha256', self::SALT.strval(time()));
		$hashed_password = hash_hmac('sha256', $password, $salt);

		// update password
		$this->updateData(
				array(
					'password'	=> $hashed_password,
					'salt'		=> $salt
				),
				array('id' => $user->id)
			);
		$result = true;

		return $result;
	}

	/**
	 * Check if specified user credentials are valid.
	 *
	 * @param string $username
	 * @param string $password
	 * @return boolean
	 */
	public function check_credentials($username, $password) {
		$result = false;

		// get salt for user
		$test_user = $this->getSingleItem(array('salt'), array('username' => $username));

		// check credentials
		if (is_object($test_user)) {
			$hashed_password = hash_hmac('sha256', $password, $test_user->salt);
			$user = $this->getSingleItem(
						array('id'),
						array(
							'username'	=> $username,
							'password'	=> $hashed_password
						)
					);

			$result = is_object($user);
		}

		return $result;
	}

	/**
	 * Log user in by checking credentials, and storing information
	 * in current session container.
	 *
	 * @param string $username
	 * @return boolean
	 */
	public function login_user($username) {
		$result = false;

		// get user from the database
		$user = $this->getSingleItem($this->getFieldNames(), array('username' => $username));

		// set session variables
		if (is_object($user)) {
			$_SESSION['uid'] = $user->id;
			$_SESSION['logged'] = true;
			$_SESSION['level'] = $user->level;
			$_SESSION['username'] = $user->username;
			$_SESSION['fullname'] = $user->fullname;
			$result = true;
		}

		return $result;
	}

	/**
	 * Log currently logged user out and clear session variables.
	 *
	 * @return boolean
	 */
	public function logout_user() {
		$result = false;

		if (!$_SESSION['logged'])
			return $result;

		// kill session variables
		unset($_SESSION['uid']);
		unset($_SESSION['username']);
		unset($_SESSION['fullname']);
		$_SESSION['level'] = 0;
		$_SESSION['logged'] = false;
		$result = true;

		return $result;
	}

	/**
	 * Set user as verified.
	 *
	 * @param string $username
	 * @param boolean $verified
	 * @return boolean
	 */
	public function verify_user($username, $verified=true) {
		$result = false;

		// get user object to play with
		$user = $this->getSingleItem(array('id', 'verified'), array('username' => $username));

		// set user as verified
		if (is_object($user) && !$user->verified) {
			$this->updateData(
				array('verified' => $verified ? 1 : 0),
				array('id' => $user->id)
			);

			$result = true;
		}

		return $result;
	}

	/**
	 * Check if specified user is verified.
	 *
	 * @param string $username
	 * @return boolean
	 */
	public function is_user_verified($username) {
		$user = $this->getSingleItem(array('verified'), array('username' => $username));
		$result = is_object($user) && $user->verified;

		return $result;
	}
}


final class UserDataManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_user_data');

		$this->addProperty('user', 'int');
		$this->addProperty('namespace', 'varchar');
		$this->addProperty('key', 'varchar');
		$this->addProperty('value', 'varchar');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}


final class UserVerificationManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_access_verification');

		$this->addProperty('user', 'int');
		$this->addProperty('timestamp', 'timestamp');
		$this->addProperty('code', 'varchar');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}


final class LoginRetryManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_retries');

		$this->addProperty('id', 'int');
		$this->addProperty('day', 'int');
		$this->addProperty('address', 'varchar');
		$this->addProperty('count', 'int');
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
	 * Purge outdated entries.
	 */
	private function purgeOutdated() {
		$this->deleteData(array(
						'day' => array(
							'operator'	=> '!=',
							'value'		=> date('j')
						)));
	}

	/**
	 * Get number of retries for current or specified address.
	 *
	 * @param string $address
	 * @return integer
	 */
	public function getRetryCount($address=null) {
		if (is_null($address))
			$address = $_SERVER['REMOTE_ADDR'];

		// purge outdated entries
		$this->purgeOutdated();

		// try to get existing entry
		$result = 0;
		$entry = $this->getSingleItem(array('count'), array('address' => $address));

		if (is_object($entry))
			$result = $entry->count;

		return $result;
	}

	/**
	 * Increase number of retries for current or specified address.
	 *
	 * @param string $address
	 * @return integer
	 */
	public function increaseCount($address=null) {
		if (is_null($address))
			$address = $_SERVER['REMOTE_ADDR'];

		// get existing entry if it exists
		$entry = $this->getSingleItem($this->getFieldNames(), array('address' => $address));

		if (is_object($entry)) {
			// don't allow counter to go over 10
			$count = ($entry->count < 10) ? $entry->count+1 : 10;
			$this->updateData(
							array('count'	=> $count),
							array('id'		=> $entry->id)
						);
			$result = $count;

		} else {
			// there's no existing entry so we create one
			$this->insertData(array(
								'day'		=> date('j'),
								'address'	=> $_SERVER['REMOTE_ADDR'],
								'count'		=> 1
							));
			$result = 1;
		}

		return $result;
	}

	/**
	 * Clear number of retries for current or specified address.
	 *
	 * @param string $address
	 */
	public function clearAddress($address=null) {
		if (is_null($address))
			$address = $_SERVER['REMOTE_ADDR'];

		$this->deleteData(array('address' => $address));
	}
}


class SettingsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_settings');

		$this->addProperty('id', 'int');
		$this->addProperty('module', 'varchar');
		$this->addProperty('variable', 'varchar');
		$this->addProperty('value', 'text');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}

?>
