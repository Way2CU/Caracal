<?php

/**
 * CORS Support Class
 *
 * Class meant to help with CORS (cross-origin resource sharing)
 * mechanism implementation and usage.
 *
 * Both preflight and regular (simple) CORS requests are supported.
 *
 * Usage example:
 *
 *	$domain = Manager::add_domain('http://domain.com');
 *	Manager::allow_methods($domain, array('GET', 'PUT'));
 *	Manager::allow_headers($domain, array('X-Requested-With'));
 *	Manager::allow_credentials($domain, true);
 *	Manager::set_max_age($domain, 36000);
 *
 */
namespace Core\CORS;


class CorsException extends \Exception{};


final class Manager {
	private static $domains = array();
	private static $config = array();

	private static $preflight_allowed_methods = array('PUT', 'DELETE', 'CONNECT', 'OPTIONS', 'TRACE', 'PATCH');
	private static $simple_request_allowed_methods = array('GET', 'HEAD', 'POST');

	/**
	 * Add domain to the list of handled and return its unique id. This
	 * id is used for all the other functions to further configure behavior
	 * for each CORS request per-domain.
	 *
	 * @param string $domain
	 * @return integer
	 */
	public static function add_domain($domain) {
		if (in_array($domain, self::$domains))
			throw new CorsException('Domain you specified is already in the list!');

		$index = count(self::$domains);
		self::$domains[$index]= $domain;
		self::$config[$index] = array(
				'credentials' => null,
				'methods'     => array(),
				'headers'     => array(),
				'expose'      => array(),
				'max_age'     => null
			);

		return $index;
	}

	/**
	 * Configure whether we allow response to the request to exposed to the
	 * browser or not. When used as a part of response to preflight request
	 * this indicated whether or not the actual request can be made using
	 * credentials.
	 *
	 * @param integer $domain
	 * @param boolean $allowed
	 */
	public static function allow_credentials($domain, $allowed=true) {
		if (!array_key_exists($domain, self::$domains))
			throw new CorsException('Invalid domain handle.');

		self::$config[$domain]['credentials'] = $allowed;
	}

	/**
	 * Allow methods for specified domain. Values for `$methods` array
	 * are in uppercase string format.
	 *
	 * @param integer $domain
	 * @param array $methods
	 */
	public static function allow_methods($domain, $methods) {
		if (!array_key_exists($domain, self::$domains))
			throw new CorsException('Invalid domain handle.');

		if (!is_array($methods))
			throw new CorsException('Methods parameter must be array.');

		self::$config[$domain]['methods'] = $methods;
	}

	/**
 	 * Set list of headers allowed to appear in actual request.
	 *
	 * @param integer $domain
	 * @param array $headers
	 */
	public static function allow_headers($domain, $headers) {
		if (!array_key_exists($domain, self::$domains))
			throw new CorsException('Invalid domain handle.');

		if (!is_array($headers))
			throw new CorsException('Headers parameter must be array.');

		self::$config[$domain]['headers'] = $headers;
	}

	/**
	 * Set specified list of headers to be exposed to the browser after
	 * actual request has been made.
	 *
	 * @param integer $domain
	 * @param array $headers
	 */
	public static function expose_headers($domain, $headers) {
		if (!array_key_exists($domain, self::$domains))
			throw new CorsException('Invalid domain handle.');

		if (!is_array($headers))
			throw new CorsException('Headers parameter must be array.');

		self::$config[$domain]['expose'] = $headers;
	}

	/**
	 * Set how long the results of preflight request can be cached in
	 * seconds. After specified number of seconds has passed since the original
	 * preflight request, browser will send a new one.
	 *
	 * @param integer $domain
	 * @param integer $seconds
	 */
	public static function set_max_age($domain, $seconds) {
		if (!array_key_exists($domain, self::$domains))
			throw new CorsException('Invalid domain handle.');

		if (!is_int($seconds))
			throw new CorsException('Seconds parameter must be integer.');

		self::$config[$domain]['max_age'] = $seconds;
	}

	/**
	 * Handle OPTIONS preflight request.
	 *
	 * Note: This function terminates current request.
	 */
	public static function handle_preflight_request() {
		// terminate early, we need to know origin as per spec
		if (!isset($_SERVER['HTTP_ORIGIN']))
			exit;

		$index = false;
		$origin =  $_SERVER['HTTP_ORIGIN'];

		// try to find domain in configuration
		if (in_array($origin, self::$domains))
			$index = array_search($origin, self::$domains);

		// try matching all domains
		if ($index === false && in_array('*', self::$domains))
			$index = array_search('*', self::$domains);

		if (is_int($index)) {
			$domain = self::$domains[$index];
			$config = self::$config[$index];

			header('Access-Control-Allow-Origin: '.$domain);

			if (!is_null($config['credentials']))
				header('Access-Control-Allow-Credentials: '.($config['credentials'] ? 'true' : 'false'));

			$methods = array_intersect(self::$preflight_allowed_methods, $config['methods']);
			if (!empty($methods))
				header('Access-Control-Allow-Methods: '.implode(', ', $methods));

			if (!empty($config['headers']))
				header('Access-Control-Allow-Headers: '.implode(', ', $config['headers']));

			if (!is_null($config['max_age']))
				header('Access-Control-Max-Age: '.$config['max_age']);
		}

		exit;
	}

	/**
	 * Handle request and send response headers and appropriate
	 * values configured by other functions in configuration file.
	 */
	public static function add_response_headers() {
		// add headers only if needed
		if (!isset($_SERVER['HTTP_ORIGIN']))
			return;

		$index = false;
		$origin = $_SERVER['HTTP_ORIGIN'];

		// try to find domain in configuration
		if (in_array($origin, self::$domains))
			$index = array_search($origin, self::$domains);

		// try matching all domains
		if ($index === false && in_array('*', self::$domains))
			$index = array_search('*', self::$domains);

		if (is_int($index)) {
			$domain = self::$domains[$index];
			$config = self::$config[$index];

			header('Access-Control-Allow-Origin: '.$domain);

			if (!is_null($config['credentials']))
				header('Access-Control-Allow-Credentials: '.($config['credentials'] ? 'true' : 'false'));

			$methods = array_intersect(self::$simple_request_allowed_methods, $config['methods']);
			if (!empty($methods))
				header('Access-Control-Allow-Methods: '.implode(', ', $methods));
		}
	}
}

?>
