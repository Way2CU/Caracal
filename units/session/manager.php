<?php
/**
 * Shared session management functions.
 */
namespace Core\Session;
use \Exception;

require_once('mechanism.php');
require_once('mechanisms/system.php');


class MechanismException extends Exception{};


class Type {
	const NORMAL = 0;
	const BROWSER = 1;
	const EXTENDED = 2;
}


/**
 * Session Handling Class
 */
final class Manager {
	const COOKIE_ID = 'Caracal_SessionID';
	const COOKIE_TYPE = 'Caracal_Type';

	const DEFAULT_DURATION = 15;
	const EXTENDED_DURATION = 43200;  // 30 days

	private static $path;
	private static $login_mechanisms = array();

	/**
	 * Get relative site path. This path is used for
	 * properly setting cookies.
	 *
	 * @note: Although not specified in documentation cookie path
	 * must be URL encoded. Otherwise you will run into mysterious
	 * problems of cookies not being set. Additionally only ASCII
	 * characters are allowed.
	 */
	public static function get_path() {
		if (!isset(self::$path)) {
			$path = dirname($_SERVER['PHP_SELF']);
			self::$path = join('/', array_map('rawurlencode', explode('/', $path)));
		}

		return self::$path;
	}

	/**
	 * Start a new session. This function is called
	 * once by main initialization script and should not
	 * be used in other parts of the system.
	 *
	 * @note: When session is set to Type::NORMAL some
	 * versions of IE will create new session on each page
	 * load. This is due to bug in IE which accepts
	 * cookies in GMT but checks for their validity in
	 * local time zone. Since our cookies are set to
	 * expire in 15 minutes, they have expired before storage.
	 * Using Type::BROWSER solves this issue.
	 */
	public static function start() {
		global $session_type;

		$type = $session_type;
		$duration = 0;

		// get current session type
		if (isset($_COOKIE[self::COOKIE_TYPE]))
			$type = fix_id($_COOKIE[self::COOKIE_TYPE]); else
			setcookie(self::COOKIE_TYPE, $type, 0, self::get_path(), '', false, true);

		// configure default duration
		switch ($type) {
			case Type::BROWSER:
					session_set_cookie_params(0, self::get_path(), '', false, true);
					break;

			case Type::EXTENDED:
					$duration = self::EXTENDED_DURATION * 60;
					session_set_cookie_params($duration, self::get_path(), '', false, true);
					break;

			case Type::NORMAL:
				default:
					$duration = self::DEFAULT_DURATION * 60;
					session_set_cookie_params($duration, self::get_path(), '', false, true);
					break;
		}

		// start session
		session_name(self::COOKIE_ID);
		session_start();

		// extend expiration for all types other than browser
		if ($type == Type::NORMAL || $type == Type::EXTENDED) {
			setcookie(self::COOKIE_ID, session_id(), time() + $duration, self::get_path(), '', false, true);
			setcookie(self::COOKIE_TYPE, $type, time() + $duration, self::get_path(), '', false, true);
		}

		// make sure session variables are properly set
		if (!isset($_SESSION['level']) || empty($_SESSION['level'])) $_SESSION['level'] = 0;
		if (!isset($_SESSION['logged']) || empty($_SESSION['logged'])) $_SESSION['logged'] = false;
	}

	/**
	 * Change session type.
	 *
	 * @param integer $type	Default if not otherwise specified
	 * @param integer $duration	In minutes, required only for extended
	 */
	public static function change_type($type=null, $duration=null) {
		global $session_type;

		if (is_null($type))
			$type = $session_type;

		// calculate duration based on type
		switch ($type) {
			case Type::EXTENDED:
					if (is_null($duration))
						$duration = 30 * 24 * 60; else
						$duration = self::EXTENDED_DURATION;

					$timestamp = time() + ($duration * 60);
					break;

			case Type::BROWSER:
					$timestamp = 0;
					break;

			case Type::NORMAL:
				default:
					$timestamp = time() + (self::DEFAULT_DURATION * 60);
					break;
		}

		// modify cookies
		setcookie(self::COOKIE_ID, session_id(), $timestamp, self::get_path());
		setcookie(self::COOKIE_TYPE, $type, $timestamp, self::get_path());
	}

	/**
	 * Register additional login mechanism to be used by the system.
	 *
	 * @param string $name
	 * @param object $mechanism
	 */
	public static function register_login_mechanism($name, $mechanism) {
		// make sure mechanism doesn't already exist under same name
		if (array_key_exists($name, self::$login_mechanisms))
			throw new MechanismException(
				"Mechanism with specified name ('{$name}') already exists."
			);

		// make sure mechanism implements required methods
		if (!is_subclass_of($mechanism, Mechanism))
			throw new MechanismException(
				"Mechanism with specified name ('{$name}') doesn't implement".
				"`Core\Session\Mechanism` class."
			);

		// store mechanism for later use
		self::$login_mechanisms[$name] = $mechanism;
	}

	/**
	 * Perform authentication and login process for matched mechanism. Return
	 * value is the mechanism name which completed authentication successfully
	 * or `null`.
	 *
	 * @return string
	 */
	public static function login($params=null) {
		$result = null;

		foreach (self::$login_mechanisms as $name => $mechanism) {
			// perform authentication
			if (!$mechanism->login($params))
				continue;

			// retrieve data and store session variables
			$data = $mechanism->get_data();
			$_SESSION['uid'] = $data['uid'];
			$_SESSION['logged'] = true;
			$_SESSION['login_mechanism'] = $name;
			$_SESSION['level'] = $data['level'];
			$_SESSION['username'] = $data['username'];
			$_SESSION['fullname'] = "{$data['first_name']} {$data['last_name']}";
			$_SESSION['first_name'] = $data['first_name'];
			$_SESSION['last_name'] = $data['last_name'];

			// update result and leave
			$result = $name;
			break;
		}

		return $result;
	}

	/**
	 * Perform logout process with mechanism which was used for successful
	 * login. Return value denotes success of logout operation.
	 *
	 * @return boolean
	 */
	public static function logout() {
		$result = null;
		$name = $_SESSION['login_mechanism'];

		// make sure mechanism still exists
		if (!array_key_exists($name, $mechanism))
			throw MechanismException("Unknown mechanism '{$name}'. Unable to perform logout.");

		// perform logout
		$mechanism = self::$login_mechanisms[$name];
		$result = $mechanism->logout();

		// clear session data
		if ($result) {
			$_SESSION['uid'] = 0;
			$_SESSION['logged'] = false;
			$_SESSION['level'] = 0;
			$_SESSION['username'] = '';
			$_SESSION['fullname'] = '';
			$_SESSION['first_name'] = '';
			$_SESSION['last_name'] = '';
		}

		return $result;
	}
}

?>
