<?php

/**
 * Universal resource locator (URL) helper class provides functions
 * for creating, modifying and working with URLs.
 *
 * Author: Mladen Mijatov
 */
final class URL {
	/**
	 * Construct URL from provided parameters. Template file can be specified
	 * to avoid ambiguity when parameters have similar names in different templates.
	 *
	 * @param array $params
	 * @param string $template_file
	 * @return string
	 */
	public static function make($params=array(), $file=null, $force_secure=false) {
		global $url_rewrite, $language, $default_language;

		$result = '';
		$matched_pattern = null;
		$matched_params = null;

		if (!(empty($params) && is_null($file))) {
			// get list of URL templates matching specified file
			$pattern_list = SectionHandler::get_patterns_for_file($file);

			if (count($pattern_list) == 0)
				return $result;

			// try to find matching template based on params
			$temp = $params;
			if (isset($temp['language']))
				unset($temp['language']);
			$param_names = array_keys($temp);

			foreach ($pattern_list as $pattern => $pattern_params)
				if ($pattern_params[2] == $param_names) {
					$matched_pattern = $pattern;
					$matched_params = $pattern_params[0];
					break;
				}

			// build universal resource locator string
			if (is_null($matched_pattern))
				return $result;

			$result = str_replace($matched_params, $temp, $matched_pattern);

		} else {
			// special case scenario when all parameters are omitted
			$result = SectionHandler::ROOT_KEY;
		}

		// append language if specified
		$language_to_add = isset($params['language']) ? $params['language'] : $language;

		if ($language_to_add != $default_language)
			$result = '/'.$language_to_add.$result;

		// add URL base
		$result = self::get_base($force_secure).($url_rewrite ? '' : '?').$result;

		return $result;
	}

	/**
	 * Build query string from specified parameters. This function accepts more than
	 * two arguments. Subsequent arguments, after `$section` and `$action`, are associative array(s).
	 *
	 * @param string $section
	 * @param string $action
	 * @param array ...
	 * @return string
	 */
	public static function make_query($section=null, $action=null) {
		global $language, $default_language;

		$result = self::get_base().'/';
		$arguments = array();

		// add section and action to argument list
		if (!is_null($section))
			$arguments['section'] = $section;

		if (!is_null($action) && !empty($action))
			$arguments['action'] = $action;

		// keep cross-page language selection
		if ($language != $default_language)
			$arguments['language'] = $language;

		// add remaining arguments
		if (func_num_args() > 2) {
			$additional = array_slice(func_get_args(), 2);
			foreach ($additional as $argument)
				$arguments[$argument[0]] = $argument[1];
		}

		// build query
		$result = $result.'?'.http_build_query($arguments);

		return $result;
	}

	/**
	 * Return URL for file specified using current base URL.
	 *
	 * @param string $path
	 * @return string
	 */
	public static function from_file_path($path) {
		$base_url = self::get_base();

		$path = str_replace('\\', '/', $path);
		$result = $base_url.substr($path, strlen(_BASEPATH));
		return $result;
	}

	/**
	 * Return absolute path to the file based on URL provided. If base parameter
	 * is omitted default one will be used.
	 *
	 * @param string $url
	 * @param string $base
	 * @return string
	 */
	public static function to_file_path($url, $base=null) {
		// get base URL
		$base = is_null($base) ? self::get_base() : $base;

		// shorten the base URL for protocol relative links
		if (substr($url, 0, 2) == '//') {
			$length = strpos($base, '://');
			$base = substr($base, $length + 1);
		}

		return _BASEPATH.substr($url, strlen($base));
	}

	/**
	 * Get base URL where site is located. This includes domain, path
	 * and potentially script name.
	 *
	 * @param boolean $force_secure
	 * @return string
	 */
	public static function get_base($force_secure=false) {
		$base = (_SECURE || $force_secure ? 'https://' : 'http://')._DOMAIN;

		$port = $_SERVER['SERVER_PORT'];
		if ($port != 80 && $port != 443)
			$base .= ':'.$port;

		$result = dirname($base.$_SERVER['PHP_SELF']);
		$result = preg_replace('|/$|i', '', $result);

		return $result;
	}

	/**
	 * Create anchor tag with specified parameters.
	 *
	 * @param string $content
	 * @param string $url
	 * @param string $title
	 * @param string $class
	 * @param string $target
	 * @return string
	 */
	public static function make_hyperlink($content, $url, $title=null, $class=null, $target=null) {
		$attribute_list = array();

		// populate attribute list
		if (!is_null($title))
			$attribute_list['title'] = $title;
		if (!is_null($class))
			$attribute_list['class'] = $class;
		if (!is_null($target))
			$attribute_list['target'] = $target;

		// move javascript URLs to onclick handler
		if (substr($url, 0, 11) == 'javascript:') {
			$attribute_list['onclick'] = $url;
			$attribute_list['href'] = 'javascript:void(0);';
		} else {
			$attribute_list['href'] = $url;
		}

		// generate attribute string
		$attributes = '';
		foreach ($attribute_list as $key => $value)
			$attributes .= ' '.$key.'="'.$value.'"';

		$result = '<a'.$attributes.'>'.$content.'</a>';
		return $result;
	}

	/**
	 * Show code setting page refresh for specified URL and timeout. If omitted URL defaults
	 * to current one and forcing page reload.
	 *
	 * @param string $url
	 * @param integer $timeout
	 */
	public static function set_refresh($url=null, $timeout=2) {
		$url = is_null($url) ? $_SERVER['REQUEST_URI'] : $url;
		$output = '<script type="text/javascript">';
		$output .= 'setTimeout(function() { window.location = \''.$url.'\'; }, '.($timeout * 1000).')';
		$output .= '</script>';

		print $output;
	}

	/**
	 * Populate global arrays with variable values extracted with
	 * template used to match page template file.
	 */
	public static function unpack_values() {
		// get pattern and data for matching
		$pattern = SectionHandler::get_matched_pattern();
		$request_path = self::get_request_path();
		$query_string = self::get_query_string();

		// update GET parameters
		if (!is_null($query_string)) {
			parse_str($query_string, $query_string_values);
			$_GET = array_merge($_GET, $query_string_values);
		}

		// update POST parameters
		if (!is_null($pattern)) {
			// extract parameter values from request path
			preg_match($pattern, $request_path, $request_path_matches);

			// filter matched request path values
			$request_path_values = array();
			foreach ($request_path_matches as $name => $value)
				if (!is_int($name))
					$request_path_values[$name] = $value;

			// modify global variables
			switch ($_SERVER['REQUEST_METHOD']) {
				case 'GET':
					$_GET = array_merge($_GET, $request_path_values);
					break;

				case 'POST':
					$post_values = self::decode_values($_POST);
					$_POST = array_merge($post_values, $request_path_values);
					break;
			}
		}

		// update joint parameter storage
		$_REQUEST = array_merge($_GET, $_POST);
	}

	/**
	 * This function decodes characters encoded by JavaScript.
	 *
	 * @param string/array $text
	 * @return string/array
	 */
	public static function decode($text) {
		$result = '';

		if (!is_array($text)) {
			$text = preg_replace("/%u([0-9a-f]{3,4})/i","&#x\\1;", urldecode($text));
			$result = html_entity_decode($text, null, 'UTF-8');;

		} else {
			$result = array();
			foreach ($text as $index => $value)
				$result[$index] = self::decode($value);
		}

		return $result;
	}

	/**
	 * Decode all request parameter values.
	 *
	 * @param array $input
	 */
	public static function decode_values($input) {
		$result = array();

		foreach ($input as $key => $value)
			$result[$key] = self::decode($value);

		return $result;
	}

	/**
	 * Return request URI.
	 *
	 * @return string
	 */
	public static function get_request_uri() {
		$result = '';
		$query = $_SERVER['QUERY_STRING'];
		$contains_question_mark = strpos($query, '?') !== false;
		$starts_with_slash = substr($query, 0, 1) == SectionHandler::ROOT_KEY;

		if (!$starts_with_slash && !$contains_question_mark) {
			$result = SectionHandler::ROOT_KEY.'?'.$_SERVER['QUERY_STRING'];
		} else if ($starts_with_slash && !$contains_question_mark) {
			$result = $query.'?';
		} else if (!$starts_with_slash && $contains_question_mark) {
			$result = SectionHandler::ROOT_KEY.$query;
		} else {
			$result = $query;
		}

		return $result;
	}

	/**
	 * Return query string properly decoded.
	 *
	 * @return string
	 */
	public static function get_query_string() {
		$result = null;
		$raw_uri = self::get_request_uri();

		if (strpos($raw_uri, '?') !== false) {
			// split path and query string
			$uri = explode('?', self::get_request_uri(), 2);
			$result = $uri[1];

		} else {
			// no path was specified, use whole query string
			$result = $raw_uri;
		}

		// decode html encoded unicode codes
		return $result;
	}

	/**
	 * Get path for the request.
	 *
	 * @return string
	 */
	public static function get_request_path() {
		$raw_uri = self::get_request_uri();

		// split request path from query string
		if (strpos($raw_uri, '?') !== false) {
			$uri = explode('?', self::get_request_uri(), 2);
			$result = $uri[0];

		} else {
			// no path was specified, default to root
			$result = SectionHandler::ROOT_KEY;
		}

		// decode html encoded unicode codes
		return URL::decode($result);
	}

	/**
	 * Return current URL.
	 *
	 * @return string
	 */
	public static function get_current($secure=false) {
		return self::get_base($secure).self::get_request_path();
	}

	/**
	 * Test for currently matched file and return class or false.
	 * This function is commonly used with `cms:optional` attribute
	 * to provide different highlight for active menu items.
	 *
	 * @return mixed
	 */
	public static function is_active($template_file, $class='active') {
		$result = false;

		if ($template_file == SectionHandler::get_matched_file())
			$result = $class;

		return $result;
	}
}

?>
