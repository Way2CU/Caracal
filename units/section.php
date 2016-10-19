<?php

/**
 * Section Handler
 *
 * Author: Mladen Mijatov
 */

final class SectionHandler {
	private static $_instance;

	private static $data;
	private static $params;
	private static $matched_file = null;
	private static $matched_pattern = null;
	private static $matched_params = null;

	const PREFIX = '^(/(?<language>[a-z]{2}))?';
	const SUFFIX = '/?$';
	const ROOT_KEY = '/';

	/**
	 * Match template based on URL and extract parameters.
	 */
	public static function prepare() {
		global $url_rewrite, $data_path;
		$result = false;

		// prepare storage
		self::$data = array();
		self::$params = array();

		// load section data
		$raw_data = file_get_contents($data_path.'section.json');
		if ($raw_data !== FALSE) {
			// decode section file
			self::$data = json_decode($raw_data, true);

		} else {
			// report loading error
			error_log('Missing section file!');
			return $result;
		}

		// report decoding error
		if (self::$data == NULL) {
			error_log('Invalid section file!');
			return $result;
		}

		// get query string
		$query_string = $_SERVER['QUERY_STRING'];
		if (substr($query_string, 0, 1) != self::ROOT_KEY)
			$query_string = self::ROOT_KEY.$query_string;

		// try to match whole query string
		foreach (self::$data as $pattern => $template_file) {
			$match = preg_replace('|\{([\w\d-_]+)\}|iu', '(?<\1>[\w\d-_\+]+)', $pattern);
			$match = self::PREFIX.$match;
			if ($pattern == self::ROOT_KEY)
				$match .= '?';  // make root slash optional as well
			$match .= self::SUFFIX;
			$match = self::wrap_pattern($match);

			// store pattern params for later use
			preg_match_all('|\{([\w\d_-]+)\}|is', $pattern, $params);
			self::$params[$pattern] = $params;

			// successfully matched query string to template
			if (!$result && preg_match($match, $query_string, $matches)) {
				self::$matched_file = $template_file;
				self::$matched_pattern = $match;
				self::$matched_params = $params;
				$result = true;
			}
		}

		return $result;
	}

	/**
	 * Return matched template file based on query string.
	 *
	 * @return string
	 */
	public static function get_matched_file() {
		return self::$matched_file;
	}

	/**
	 * Return regular expression template used to match template file.
	 *
	 * @return string
	 */
	public static function get_matched_pattern() {
		return self::$matched_pattern;
	}

	/**
	 * Get parameter names for matched pattern.
	 *
	 * @return string
	 */
	public static function get_matched_params() {
		return self::$matched_params;
	}

	/**
	 * Return list of matched patterns for specified template file.
	 *
	 * @param string $file
	 * @return string
	 */
	public static function get_patterns_for_file($file=null) {
		$result = array();

		// collect templates
		if (is_null($file)) {
			$result = self::$params;
		} else {
			foreach (self::$data as $pattern => $template_file)
				if ($file == $template_file)
					$result[$pattern] = self::$params[$pattern];
		}

		return $result;
	}

	/**
	 * Find matching template and transfer control to it.
	 */
	public static function transfer_control() {
		$section = isset($_REQUEST['section']) ? escape_chars($_REQUEST['section']) : null;

		// transfer call to modules
		if (!is_null($section)) {
			// call named module
			if (ModuleHandler::is_loaded($section)) {
				$module = call_user_func(array($section, 'get_instance'));

				// prepare parameters
				$params = array();
				if (isset($_REQUEST['action']))
					$params['action'] = fix_chars($_REQUEST['action']); else
					$params['action'] = '_default';

				// transfer control to module
				$module->transfer_control($params, array());

			// call backend and allow it to transfer control
			} else if ($section == 'backend_module' && ModuleHandler::is_loaded('backend')) {
				$module = call_user_func(array('backend', 'get_instance'));
				$params = array('action' => 'transfer_control');

				// transfer control to backend module
				$module->transfer_control($params, array());
			}

		// transfer call to module parser
		} else {
			// make sure we have matched page template
			if (is_null(self::$matched_file))
				return;

			// create template handler
			$template = new TemplateHandler(self::$matched_file);
			$template->parse();
		}
	}

	/**
	 * Wrap pattern in match safe delimiters and apply flags.
	 *
	 * @param string
	 * @return string
	 */
	public static function wrap_pattern($pattern) {
		return '|'.$pattern.'|is';
	}
}

?>
