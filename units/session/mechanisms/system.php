<?php

/**
 * Default login mechanism
 *
 * Mechanism used for logging users in on to the system through use of
 * username and password.
 */
namespace Core\Session;


class SystemMechanism extends Mechanism {
	/**
	 * Perform authentication and return boolean value denoting
	 * success of the action.
	 *
	 * @return boolean
	 */
	public function login($params=null) {
		if (is_array($params) && isset($params['username']) && isset($params['password'])) {
			$username = $params['username'];
			$password = $params['password'];

		} else {
			$username = $_REQUEST['username'];
			$password = $_REQUEST['password'];
		}

		return self::check_credentials($username, $password);
	}

	/**
	 * Perform logout operation and return boolean value denoting
	 * success of the action.
	 *
	 * @return boolean
	 */
	public function logout() {
		return true;  // we always allow logging out as we don't have to do any cleanup
	}

	/**
	 * Check if specified user credentials are valid.
	 *
	 * @param string $username
	 * @param string $password
	 * @return boolean
	 */
	public static function check_credentials($username, $password) {
		$result = false;
		$manager = UserManager::get_instance();

		// get salt for user
		$test_user = $manager->get_single_item(array('salt'), array('username' => $username));

		// check credentials
		if (is_object($test_user)) {
			$hashed_password = hash_hmac('sha256', $password, $test_user->salt);
			$user = $manager->get_single_item(
						array('id'),
						array(
							'username'	=> $username,
							'password'	=> $hashed_password
						));

			$result = is_object($user);
		}

		return $result;
	}

	/**
	 * Return associative array containing data for session variables.
	 * This function is called immediately after successful login.
	 *
	 * Example return structure:
	 *
	 * @return array
	 */
	public function get_data() {
		$result = array(
			'uid'       => 0,
			'level'     => 5,
			'username'  => 'joe',
			'fist_name' => 'Joe',
			'last_name' => 'Manning'
			);

		return $result;
	}
}

?>
