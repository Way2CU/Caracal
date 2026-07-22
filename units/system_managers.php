<?php

/**
 * System Managers
 *
 * Author: Mladen Mijatov
 */


class InvalidUserError extends Exception {}


class ModuleManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_modules');

		$this->add_property('id', 'int');
		$this->add_property('order', 'int');
		$this->add_property('name', 'varchar');
		$this->add_property('preload', 'int');
		$this->add_property('active', 'int');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}


final class UserManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_access');

		$this->add_property('id', 'int');
		$this->add_property('username', 'varchar');
		$this->add_property('password', 'varchar');
		// TODO: Get rid of `fullname` sometime.
		$this->add_property('fullname', 'varchar');
		$this->add_property('first_name', 'varchar');
		$this->add_property('last_name', 'varchar');
		$this->add_property('email', 'varchar');
		$this->add_property('level', 'int');
		$this->add_property('verified', 'boolean');
		$this->add_property('agreed', 'boolean');
		$this->add_property('salt', 'char');
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
		$user = $this->get_single_item(array('id'), array('username' => $username));
		if (!is_object($user))
			throw new InvalidUserError('Unable to change password!');

		// strong, per-record salted hash with a work factor
		$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

		// salt column is obsolete (salt is embedded in the hash); clear it so a
		// stale legacy salt can never be mistaken for a valid one
		$this->update_items(
				array(
					'password'	=> $hashed_password,
					'salt'		=> ''
				),
				array('id' => $user->id)
			);
		$result = true;

		return $result;
	}

	/**
	 * Verify specified user's password, transparently upgrading legacy or
	 * cost-outdated hashes on a successful check.
	 *
	 * @param string $username
	 * @param string $password
	 * @return boolean
	 */
	public function verify_password($username, $password) {
		$result = false;

		$user = $this->get_single_item(
				array('id', 'password', 'salt'),
				array('username' => $username)
			);

		if (is_object($user)) {
			$info = password_get_info($user->password);

			if ($info['algo']) {
				// modern hash, constant-time verification
				$result = password_verify($password, $user->password);

			} else {
				// legacy hash_hmac('sha256', pw, salt) scheme
				$result = hash_equals($user->password, hash_hmac('sha256', $password, $user->salt));
			}

			// upgrade legacy or cost-outdated hashes after a successful check
			if ($result && (!$info['algo'] || password_needs_rehash($user->password, PASSWORD_DEFAULT)))
				$this->update_items(
						array('password' => password_hash($password, PASSWORD_DEFAULT), 'salt' => ''),
						array('id' => $user->id)
					);
		}

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
		$user = $this->get_single_item(array('id', 'verified'), array('username' => $username));

		// set user as verified
		if (is_object($user) && !$user->verified) {
			$this->update_items(
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
		$user = $this->get_single_item(array('verified'), array('username' => $username));
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

		$this->add_property('user', 'int');
		$this->add_property('namespace', 'varchar');
		$this->add_property('key', 'varchar');
		$this->add_property('value', 'varchar');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
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

		$this->add_property('user', 'int');
		$this->add_property('timestamp', 'timestamp');
		$this->add_property('code', 'varchar');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
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

		$this->add_property('id', 'int');
		$this->add_property('day', 'int');
		$this->add_property('address', 'varchar');
		$this->add_property('count', 'int');
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
	 * Purge outdated entries.
	 */
	private function purgeOutdated() {
		$this->delete_items(array(
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
		$entry = $this->get_single_item(array('count'), array('address' => $address));

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
		$entry = $this->get_single_item($this->get_field_names(), array('address' => $address));

		if (is_object($entry)) {
			// don't allow counter to go over 10
			$count = ($entry->count < 10) ? $entry->count+1 : 10;
			$this->update_items(
							array('count'	=> $count),
							array('id'		=> $entry->id)
						);
			$result = $count;

		} else {
			// there's no existing entry so we create one
			$this->insert_item(array(
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

		$this->delete_items(array('address' => $address));
	}
}


class SettingsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_settings');

		$this->add_property('id', 'int');
		$this->add_property('module', 'varchar');
		$this->add_property('variable', 'varchar');
		$this->add_property('value', 'text');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}


class PrivacyConsentManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_privacy_consent');

		$this->add_property('uid', 'char');
		$this->add_property('timestamp', 'timestamp');
		$this->add_property('categories', 'varchar');
		$this->add_property('gpc', 'boolean');
		$this->add_property('gpp', 'varchar');
		$this->add_property('desktop_version', 'boolean');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}

?>
