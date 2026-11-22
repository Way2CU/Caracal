<?php

/**
 * Grow API Helper
 *
 * Thin wrapper around Grow (formerly Meshulam) "light" server API. Every
 * call is a form encoded HTTP POST which returns JSON object containing
 * `status`, `err` and `data` keys. Only `data` is returned to the caller,
 * everything else is turned into an exception.
 */

class Grow_ApiError extends Exception {}


final class Grow_Api {
	const SANDBOX_URL = 'https://sandbox.meshulam.co.il/api/light/server/1.0/';
	const LIVE_URL = 'https://secure.meshulam.co.il/api/light/server/1.0/';

	// api methods
	const METHOD_CREATE_PAYMENT_PROCESS = 'createPaymentProcess';
	const METHOD_GET_PAYMENT_PROCESS_INFO = 'getPaymentProcessInfo';
	const METHOD_GET_TRANSACTION_INFO = 'getTransactionInfo';
	const METHOD_APPROVE_TRANSACTION = 'approveTransaction';
	const METHOD_SETTLE_SUSPENDED_TRANSACTION = 'settleSuspendedTransaction';
	const METHOD_REFUND_TRANSACTION = 'refundTransaction';

	// charge types used when creating payment process
	const CHARGE_REGULAR = 1;
	const CHARGE_SUSPENDED = 2;
	const CHARGE_TOKEN_ONLY = 3;

	// domains payment page is allowed to be hosted on
	private static $allowed_hosts = array('meshulam.co.il', 'grow.link', 'grow.business');

	private static $sandbox = true;
	private static $timeout = 20;

	/**
	 * Select between sandbox and live API endpoints.
	 *
	 * @param boolean $sandbox
	 */
	public static function set_sandbox($sandbox) {
		self::$sandbox = (bool) $sandbox;
	}

	/**
	 * Make API call and return `data` part of the response.
	 *
	 * @param string $method
	 * @param array $params
	 * @return array
	 * @throws Grow_ApiError
	 */
	public static function call($method, $params) {
		$url = (self::$sandbox ? self::SANDBOX_URL : self::LIVE_URL).$method;

		// make the call
		$handle = curl_init($url);
		curl_setopt($handle, CURLOPT_POST, true);
		curl_setopt($handle, CURLOPT_HEADER, false);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($params));
		curl_setopt($handle, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
		curl_setopt($handle, CURLOPT_USERAGENT, 'Caracal '._VERSION);
		curl_setopt($handle, CURLOPT_TIMEOUT, self::$timeout);
		$raw_response = curl_exec($handle);
		$error = curl_error($handle);
		curl_close($handle);

		if ($raw_response === false)
			throw new Grow_ApiError("Grow API: Unable to call '{$method}'. {$error}");

		// parse response
		$response = json_decode($raw_response, true);

		if (!is_array($response) || !isset($response['status']))
			throw new Grow_ApiError("Grow API: Invalid response received for '{$method}'.");

		if ((int) $response['status'] != 1) {
			$error_id = isset($response['err']['id']) ? $response['err']['id'] : 0;
			$error_message = isset($response['err']['message']) ? $response['err']['message'] : 'Unknown error';
			throw new Grow_ApiError("Grow API: ({$error_id}) {$error_message}");
		}

		if (isset($response['data']) && is_array($response['data']))
			$result = $response['data']; else
			$result = array();

		return $result;
	}

	/**
	 * Check if specified URL is secure and hosted by Grow. Used to make sure
	 * buyers are redirected only to legitimate payment pages.
	 *
	 * @param string $url
	 * @return boolean
	 */
	public static function is_payment_page_url($url) {
		$result = false;
		$parts = parse_url($url);
		$is_secure = is_array($parts) && isset($parts['scheme']) && isset($parts['host']) && $parts['scheme'] == 'https';

		if ($is_secure)
			foreach (self::$allowed_hosts as $allowed_host) {
				$suffix = '.'.$allowed_host;
				$host = strtolower($parts['host']);

				if ($host == $allowed_host || substr($host, -strlen($suffix)) == $suffix) {
					$result = true;
					break;
				}
			}

		return $result;
	}
}

?>
