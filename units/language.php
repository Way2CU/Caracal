<?php


class LanguageHandler {
	private $active = false;
	private $system = false;
	private $data = array();
	private $list = null;
	private $file;

	/**
	 * Constructor
	 *
	 * @return LanguageHandler
	 */
	public function __construct($path) {
		global $data_path, $language;

		// decide which file to load
		$this->file = $this->get_language_file($path);

		// make sure language file exists
		if (!file_exists($this->file) && $language != 'en') {
			trigger_error('Missing language file: '.$this->file.'. Defaulting to English!', E_USER_WARNING);
			$this->file = $this->get_language_file($path, 'en');
		}

		if (!file_exists($this->file)) {
			trigger_error('English version wasn\'t found.', E_USER_NOTICE);
			return;
		}

		// load language file
		$this->data = json_decode(file_get_contents($this->file));
		$this->active = !is_null($this->data);

		// report error
		if (is_null($this->data))
			trigger_error('Invalid language file: '.$this->file, E_USER_WARNING);
	}

	/**
	 * Load language file data from specified patha and for specified language.
	 *
	 * @param string $path
	 * @param string $specified_language
	 * @return string
	 */
	private function get_language_file($path, $specified_language=null) {
		global $language;

		// detect which language to load
		$language_to_load = is_null($specified_language) ? $language : $specified_language;

		// prepare path
		$result = $path.'language_'.$language_to_load.'.json';

		return $result;
	}

	/**
	 * Returns localised text for given constant
	 *
	 * @param string $constant
	 * @param string $specified_language
	 * @return string
	 */
	public function getText($constant, $specified_language=null) {
		global $language;

		// prepare default result
		$result = '';

		// get value
		if (property_exists($this->data, $constant))
			$result = $this->data->$constant;

		return $result;
	}

	/**
	 * Returns list of languages available
	 *
	 * @param boolean $printable What list should contain, printable text or language code
	 * @return array
	 */
	public function getLanguages($printable=true) {
		global $available_languages;
		$result = array();

		if ($printable) {
			// there's no cached result, prepare one
			if (is_null($this->list)) {
				$this->list = array();

				foreach ($available_languages as $code)
					$this->list[$code] = Language::getPrintable($code);
			}
			$result = $this->list;

		} else {
			$result = $available_languages;
		}

		return $result;
	}

	/**
	 * Check if current language is RTL (right-to-left)
	 *
	 * @param string $specified_language
	 * @return boolean
	 */
	public function isRTL($specified_language=null) {
		return Language::isRTL($specified_language);
	}
}

/**
 * Helper class for language handling.
 */
final class Language {
	private static $system_handler = null;
	private static $site_handler = null;
	private static $list;

	/**
	 * Get localized value for specified constant and language.
	 *
	 * @param string $constant
	 * @param string $language
	 * @return string
	 */
	public static function getText($constant, $language=null) {
		$result = '';

		// get site specific constant
		if (!is_null(self::$site_handler))
			$result = self::$site_handler->getText($constant, $language);

		// get system constant
		if (empty($result))
			$result = self::$system_handler->getText($constant, $language);

		return $result;
	}

	/**
	 * Returns list of languages available. You can optionally specify to get
	 * associative array of languages as keys and its full name.
	 *
	 * @param boolean $printable
	 * @return array
	 */
	public static function getLanguages($printable=true) {
		$result = array();

		// get site specific constant
		if (!is_null(self::$site_handler))
			$result = self::$site_handler->getLanguages($printable);

		// get system constant
		if (empty($result))
			$result = self::$system_handler->getLanguages($printable);

		return $result;
	}

	/**
	 * Get full language name form specified code.
	 *
	 * @param string $code
	 * @return string
	 */
	public static function getPrintable($code) {
		$result = '';

		if (property_exists(self::$list->list, $code))
 			$result = self::$list->list->$code;

		return $result;
	}

	/**
	 * Check if currently selected language or specified language is
	 * written from right to left.
	 *
	 * @param string $specified_language
	 * @return boolean
	 */
	public static function isRTL($specified_language=null) {
		global $language;
		$language_to_check = is_null($specified_language) ? $language : $specified_language;

		return in_array($language_to_check, self::$list->rtl);
	}

	/**
	 * Get list of RTL languages.
	 *
	 * @return array
	 */
	public static function getRTL() {
		return self::$list->rtl;
	}

	/**
	 * Try to match locally supported language with browser's desired
	 * language. In case match is not found local default language is
	 * returned.
	 *
	 * @param array $supported_languages
	 * @param string $default
	 * @return string
	 */
	public static function matchBrowserLanguage($supported_languages, $default) {
		$result = $default;
		$languages = array();

		// is browser didn't specify, return default
		if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
			return $result;

		// parse language list
		preg_match_all(
				'/((\w{2})[-\w]*(;\s*q=([\d\.]+))?)/i',
				strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']),
				$matches
			);

		// no matches were found, return default
		if (count($matches[1]) == 0)
			return $result;

		// pack languages in to single array
		$codes = $matches[1];
		$scores = $matches[3];

		foreach ($codes as $index => $code) {
			if (empty($scores[$index]))
				$scores[$index] = 1;

			if (!isset($languages[$code]) || $scores[$index] > $languages[$code])
				$languages[$code] = (float) $scores[$index];
		}

		// choose highest rated language
		arsort($languages);

		foreach ($languages as $code => $score)
			if (in_array($code, $supported_languages)) {
				$result = $code;
				break;
			}

		return $result;
	}

	/**
	 * Apply language for current session. If language is not defined
	 * function will try to match the language with browser's desired
	 * language or use site's default.
	 */
	public static function applyForSession() {
		global $section, $language, $default_language, $available_languages, $language_rtl,
	 		$data_path, $system_path;

		if (!isset($_REQUEST['language'])) {
			// no language change was specified, check session
			if (!isset($_SESSION['language']) || empty($_SESSION['language']))
				$_SESSION['language'] = self::matchBrowserLanguage($available_languages, $default_language);

		} else {
			// language change was specified, make sure it's valid
			if (in_array($_REQUEST['language'], $available_languages)) {
				$_SESSION['language'] = fix_chars($_REQUEST['language']);

			} else {
				// set language without asking if module is backend
				if (in_array($section, array('backend', 'backend_module')))
					$_SESSION['language'] = fix_chars($_REQUEST['language']); else
					$_SESSION['language'] = self::matchBrowserLanguage($available_languages, $default_language);
			}
		}

		// load language definitions
		self::$list = json_decode(file_get_contents($system_path.'languages.json'));

		// store language to global variable
		$language = $_SESSION['language'];
		$language_rtl = self::isRTL($language);

		// create language handlers
		self::$system_handler = new LanguageHandler($system_path);
		self::$site_handler = new LanguageHandler($data_path);
	}
}

?>
