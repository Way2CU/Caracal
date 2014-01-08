<?php

/**
 * Session Handling Class
 */
class Session {
	const COOKIE_ID = 'Caracal_SessionID';
	const COOKIE_TYPE = 'Caracal_SessionType';

	const TYPE_NORMAL = 0;
	const TYPE_BROWSER = 1;
	const TYPE_EXTENDED = 2;

	const DEFAULT_DURATION = 15;

	private static $path;

	/**
	 * Get relative site path. This path is used for
	 * properly setting cookies.
	 */
	public static function get_path() {
		if (!isset(self::$path)) 
			self::$path = dirname($_SERVER['PHP_SELF']);

		return self::$path;
	}

	/**
	 * Start a new session. This function is called
	 * once by main initialization script and should not
	 * be used in other parts of the system.
	 */
	public static function start() {
		global $session_type;

		$type = $session_type;
		$normal_duration = null;

		// get current session type
		if (isset($_COOKIE[Session::COOKIE_TYPE]))
			$type = fix_id($_COOKIE[Session::COOKIE_TYPE]);

		// configure default duration
		switch ($type) {
			case Session::TYPE_BROWSER:
				session_set_cookie_params(0, Session::get_path());
				break;
			
			case Session::TYPE_NORMAL:
			default:
				$normal_duration = Session::DEFAULT_DURATION * 60;
				session_set_cookie_params($normal_duration, Session::get_path());
				break;
		}

		// start session
		session_name(Session::COOKIE_ID);
		session_start();

		// extend expiration for normal type
		if ($type == Session::TYPE_NORMAL) {
			setcookie(Session::COOKIE_ID, session_id(), time() + $normal_duration, Session::get_path());
			setcookie(Session::COOKIE_TYPE, Session::TYPE_NORMAL, time() + $normal_duration, Session::get_path());
		}
	}

	/**
	 * Change session type.
	 *
	 * @param integer $type		Default if not otherwise specified
	 * @param integer $duration	In minutes, required only for extended
	 */
	public static function change_type($type=null, $duration=null) {
		global $session_type;

		if (is_null($type))
			$type = $session_type;

		// calculate duration based on type
		switch ($type) {
			case Session::TYPE_EXTENDED:
				if (is_null($duration))
					$duration = 30 * 24 * 60;

				$timestamp = time() + ($duration * 60);
				break;
			
			case Session::TYPE_BROWSER:
				$timestamp = 0;
				break;
			
			case Session::TYPE_NORMAL:
			default:
				$timestamp = time() + (Session::DEFAULT_DURATION * 60);
				break;
		}

		// modify cookies
		setcookie(Session::COOKIE_ID, session_id(), $timestamp, Session::get_path());
		setcookie(Session::COOKIE_TYPE, $type, $timestamp, Session::get_path());
	}
}

?>
