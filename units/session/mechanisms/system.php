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
	 * If successfull return value is an array containing identification
	 * data to be passed to `get_data` method. In case authentication
	 * failed return value is null.
	 *
	 * @param array $params
	 * @return array or null
	 */
	public function login($params=null) {
		$result = null;

		// login traditional way with username and password
		if (is_array($params) && isset($params['username']) && isset($params['password'])) {
			if (self::check_credentials($params['username'], $params['password']))
				$result = array('username' => $params['username']);

		// login using account verification code
		} else if (is_array($params) && isset($params['username']) && isset($params['verification'])) {
			$user_manager = \UserManager::get_instance();
			$code_manager = \UserVerificationManager::get_instance();

			$user = $user_manager->get_single_item(
				array('id'),
				array('username' => $params['username'])
			);

			if (!is_object($user))
				return $result;

			$verification = $code_manager->get_single_item(
					$code_manager->get_field_names(),
					array('user' => $user->id)
				);

			if (is_object($verification) && hash_equals($verification->code, (string) $params['verification']))
				$result = array('username' => $params['username']);
		}

		return $result;
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
		$manager = \UserManager::get_instance();
		return $manager->verify_password($username, $password);
	}

	/**
	 * Return associative array containing data for session variables.
	 * This function is called immediately after successful login.
	 *
	 * Example return structure:
	 *
	 * @param array $data
	 * @return array
	 */
	public function get_data($data) {
		$manager = \UserManager::get_instance();
		$user = $manager->get_single_item(
				array('id', 'level', 'first_name', 'last_name'),
				array('username' => $data['username'])
			);

		$result = array(
				'uid'       => $user->id,
				'level'     => $user->level,
				'username'  => $data['username'],
				'fist_name' => $user->first_name,
				'last_name' => $user->last_name
			);

		return $result;
	}
}

?>
