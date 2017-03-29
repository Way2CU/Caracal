<?php


class SessionType {
	const TYPE_NORMAL = 0;
	const TYPE_BROWSER = 1;
	const TYPE_EXTENDED = 2;
}


/**
 * Session Handling Class
 */
class Session {
	const COOKIE_ID = 'Caracal_SessionID';
	const COOKIE_TYPE = 'Caracal_SessionType';

	const DEFAULT_DURATION = 15;
	const EXTENDED_DURATION = 43200;  // 30 days

	private static $path;

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
	 * @note: When session is set to SessionType::NORMAL some
	 * versions of IE will create new session on each page
	 * load. This is due to bug in IE which accepts
	 * cookies in GMT but checks for their validity in
	 * local time zone. Since our cookies are set to
	 * expire in 15 minutes, they are expired before they
	 * are stored. Using SessionType::BROWSER solves this issue.
	 */
	public static function start() {
		global $session_type;

		$type = $session_type;
		$duration = 0;

		// get current session type
		if (isset($_COOKIE[self::COOKIE_TYPE]))
			$type = fix_id($_COOKIE[self::COOKIE_TYPE]); else
			setcookie(self::COOKIE_TYPE, $type, 0, self::get_path());

		// configure default duration
		switch ($type) {
		case SessionType::BROWSER:
				session_set_cookie_params(0, self::get_path());
				break;

		case SessionType::EXTENDED:
				$duration = self::EXTENDED_DURATION * 60;
				session_set_cookie_params($duration, self::get_path());
				break;

		case SessionType::NORMAL:
			default:
				$duration = self::DEFAULT_DURATION * 60;
				session_set_cookie_params($duration, self::get_path());
				break;
		}

		// start session
		session_name(self::COOKIE_ID);
		session_start();

		// extend expiration for all types other than browser
		if ($type == SessionType::NORMAL || $type == SessionType::EXTENDED) {
			setcookie(self::COOKIE_ID, session_id(), time() + $duration, self::get_path());
			setcookie(self::COOKIE_TYPE, $type, time() + $duration, self::get_path());
		}
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
		case SessionType::EXTENDED:
				if (is_null($duration))
					$duration = 30 * 24 * 60; else
					$duration = self::EXTENDED_DURATION;

				$timestamp = time() + ($duration * 60);
				break;

		case SessionType::BROWSER:
				$timestamp = 0;
				break;

		case SessionType::NORMAL:
			default:
				$timestamp = time() + (self::DEFAULT_DURATION * 60);
				break;
		}

		// modify cookies
		setcookie(self::COOKIE_ID, session_id(), $timestamp, self::get_path());
		setcookie(self::COOKIE_TYPE, $type, $timestamp, self::get_path());
	}
}

?>
