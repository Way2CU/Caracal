<?php

/**
 * Stripe API Helper
 *
 * Small wrapper around Stripe REST API. Requests are form encoded and
 * authenticated with secret key, responses are JSON objects. Errors
 * reported by Stripe are turned into exceptions. Helper also verifies
 * webhook signatures and converts amounts to and from minor units.
 */

class Stripe_ApiError extends Exception {}


final class Stripe_Api {
	const BASE_URL = 'https://api.stripe.com/v1/';
	const CHECKOUT_HOST = 'checkout.stripe.com';
	const SIGNATURE_TOLERANCE = 300;  // seconds

	// currencies stripe handles without decimal places
	private static $zero_decimal_currencies = array(
			'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga',
			'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'
		);

	private static $secret_key = '';
	private static $timeout = 30;

	/**
	 * Set secret key used for authentication.
	 *
	 * @param string $key
	 */
	public static function set_secret_key($key) {
		self::$secret_key = $key;
	}

	/**
	 * Make API call and return decoded response.
	 *
	 * @param string $method
	 * @param string $path
	 * @param array $params
	 * @param string $idempotency_key
	 * @return array
	 * @throws Stripe_ApiError
	 */
	public static function call($method, $path, $params=array(), $idempotency_key=null) {
		$url = self::BASE_URL.$path;
		$headers = array(
				'Authorization: Bearer '.self::$secret_key,
				'Content-Type: application/x-www-form-urlencoded'
			);

		if (empty(self::$secret_key))
			throw new Stripe_ApiError('Stripe API: Secret key is not configured.');

		if (!is_null($idempotency_key))
			$headers[] = 'Idempotency-Key: '.$idempotency_key;

		$handle = curl_init();

		switch ($method) {
			case 'POST':
				curl_setopt($handle, CURLOPT_POST, true);
				curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($params));
				break;

			case 'DELETE':
				curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'DELETE');
				curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($params));
				break;

			case 'GET':
			default:
				if (count($params) > 0)
					$url .= '?'.http_build_query($params);
				break;
		}

		curl_setopt($handle, CURLOPT_URL, $url);
		curl_setopt($handle, CURLOPT_HEADER, false);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($handle, CURLOPT_USERAGENT, 'Caracal '._VERSION);
		curl_setopt($handle, CURLOPT_TIMEOUT, self::$timeout);
		$raw_response = curl_exec($handle);
		$error = curl_error($handle);
		$code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		curl_close($handle);

		if ($raw_response === false)
			throw new Stripe_ApiError("Stripe API: Unable to call '{$path}'. {$error}");

		$result = json_decode($raw_response, true);

		if (!is_array($result))
			throw new Stripe_ApiError("Stripe API: Invalid response received for '{$path}'.");

		if ($code >= 400 || isset($result['error'])) {
			$type = isset($result['error']['type']) ? $result['error']['type'] : 'http_'.$code;
			$message = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown error';
			throw new Stripe_ApiError("Stripe API: ({$type}) {$message}");
		}

		return $result;
	}

	/**
	 * Convert amount to minor units expected by Stripe.
	 *
	 * @param float $amount
	 * @param string $currency
	 * @return integer
	 */
	public static function to_minor_units($amount, $currency) {
		if (in_array(strtolower($currency), self::$zero_decimal_currencies))
			$result = (int) round($amount); else
			$result = (int) round($amount * 100);

		return $result;
	}

	/**
	 * Convert amount from minor units to regular decimal value.
	 *
	 * @param integer $amount
	 * @param string $currency
	 * @return float
	 */
	public static function from_minor_units($amount, $currency) {
		if (in_array(strtolower($currency), self::$zero_decimal_currencies))
			$result = (float) $amount; else
			$result = $amount / 100;

		return $result;
	}

	/**
	 * Check if URL is a secure Stripe hosted checkout page.
	 *
	 * @param string $url
	 * @return boolean
	 */
	public static function is_checkout_url($url) {
		$parts = parse_url($url);
		$result = is_array($parts) && isset($parts['scheme']) && isset($parts['host']) &&
				$parts['scheme'] == 'https' && strtolower($parts['host']) == self::CHECKOUT_HOST;

		return $result;
	}

	/**
	 * Verify webhook payload signature.
	 *
	 * @param string $payload
	 * @param string $header
	 * @param string $secret
	 * @return boolean
	 */
	public static function verify_signature($payload, $header, $secret) {
		$result = false;
		$timestamp = null;
		$signatures = array();

		// parse header
		foreach (explode(',', $header) as $part) {
			$pair = explode('=', trim($part), 2);

			if (count($pair) != 2)
				continue;

			if ($pair[0] == 't')
				$timestamp = (int) $pair[1]; else if ($pair[0] == 'v1')
				$signatures[] = $pair[1];
		}

		// check timestamp and signatures
		$timestamp_is_fresh = !is_null($timestamp) && abs(time() - $timestamp) <= self::SIGNATURE_TOLERANCE;

		if ($timestamp_is_fresh && !empty($secret)) {
			$expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

			foreach ($signatures as $signature)
				if (hash_equals($expected, $signature)) {
					$result = true;
					break;
				}
		}

		return $result;
	}
}

?>
