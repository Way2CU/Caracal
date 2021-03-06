<?php

/**
 * Abstract login mechanism
 *
 * Mechanisms are used to provide different ways of authentication to the
 * system other than default one through username and password. Each of the
 * modules can register its own mechanism and provide a way for users to log
 * into system using different data.
 *
 * When building additional mechanism additional attention should be taken
 * to avoid accidental role escalation and access to protected parts of the
 * system.
 */
namespace Core\Session;


abstract class Mechanism {
	/**
	 * Perform authentication and return boolean value denoting
	 * success of the action. Normally this process is initiated
	 * by sending request to `?section=session`. If optional parameters
	 * are specified, authentication mechanism can be used manually.
	 *
	 * If successfull return value is an array containing identification
	 * data to be passed to `get_data` method. In case authentication
	 * failed return value is null
	 *
	 * @param array $params
	 * @return mixed
	 */
	public abstract function login($params=null);

	/**
	 * Perform logout operation and return boolean value denoting
	 * success of the action.
	 *
	 * @return boolean
	 */
	public abstract function logout();

	/**
	 * Return associative array containing data for session variables.
	 * This function is called immediately after successful login.
	 *
	 * Data provided is result from `login` call.
	 *
	 * Example return structure:
	 *	array(
	 *		'uid'       => 0,
	 *		'level'     => 5,
	 *		'username'  => 'joe',
	 *		'fist_name' => 'Joe',
	 *		'last_name' => 'Manning'
	 *	);
	 *
	 * @param array $data
	 * @return array
	 */
	public abstract function get_data($data);
}

?>
