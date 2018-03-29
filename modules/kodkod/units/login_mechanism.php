<?php

/**
 * Kodkod Authentication Mechanism
 *
 * This mechanism relies on Kodkod storage service running on the same server
 * as Caracal. Users are logged in after credentials are verified with storage
 * service.
 */
namespace Modules\Kodkod;


class Mechanism extends \Core\Session\Mechanism {
	const ENDPOINT = 'http://127.0.0.1/storage/verification';

	/**
	 * Perform authentication and return boolean value denoting
	 * success of the action.
	 *
	 * @return mixed
	 */
	public function login($params=null) {
		// prepare data for sending
		$result = null;
		$data = array(
				'id'      => fix_chars($_REQUEST['id']),
				'token'   => fix_chars($_REQUEST['token']),
				'address' => $_SERVER['REMOTE_ADDR']
			);

		// get data from storage service
		$query = http_build_query($data);
		$raw_response = file_get_contents(self::ENDPOINT.'?'.$query);

		if ($raw_response !== NULL) {
			$response = json_decode($raw_response);
			if ($response !== NULL && $response->success)
				$result = array();
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
	 * Return associative array containing data for session variables.
	 * This function is called immediately after successful login.
	 *
	 * Example return structure:
	 *
	 * @param array $data
	 * @return array
	 */
	public function get_data($data) {
		return array(
				'uid'       => 0,
				'level'     => 10,
				'username'  => 'kodkod',
				'fist_name' => 'Web',
				'last_name' => 'Application'
			);
	}
}


?>
