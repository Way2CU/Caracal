<?php


class LanguageHandler {
	private $active;

	private $rtl_languages = array();
	private $languages = array();
	private $data = array();
	private $default = null;

	/**
	 * Constructor
	 *
	 * @return LanguageHandler
	 */
	public function __construct($file='') {
		global $data_path;

		$this->active = false;
		$file = (empty($file)) ? 'system/language.xml' : $file;

		// make sure language file exists
		if (!file_exists($file))
			return;

		// parse language file
		$engine = new XMLParser(@file_get_contents($file), $file);
		$engine->Parse();
		$this->active = true;

		// make sure language file is not empty
		if (!isset($engine->document) || !isset($engine->document->language))
			return;

		foreach ($engine->document->language as $xml_language) {
			$short_name = $xml_language->tagAttrs['short'];
			$full_name = isset($xml_language->tagAttrs['name']) ? $xml_language->tagAttrs['name'] : '';
			$is_rtl = isset($xml_language->tagAttrs['rtl']) && $xml_language->tagAttrs['rtl'] == 'yes';
			$default = isset($xml_language->tagAttrs['default']);

			// add to language list
			$this->languages[$short_name] = $full_name;

			// create storage for constants
			$this->data[$short_name] = array();

			// mark as RTL
			if ($is_rtl)
				$this->rtl_languages[] = $short_name;

			// set as default
			if (is_null($this->default) && $default)
				$this->default = $short_name;

			// parse language constants
			foreach ($xml_language->constant as $xml_constant) {
				$constant_name = $xml_constant->tagAttrs['name'];
				$value = $xml_constant->tagData;
				$this->data[$short_name][$constant_name] = $value;
			}
		}

		// remove parser
		unset($engine);
	}

	/**
	 * Returns localised text for given constant
	 *
	 * @param string $constant
	 * @param string $specified_language
	 * @return string
	 */
	public function getText($constant, $specified_language='') {
		global $language;

		$result = '';
		$lang = empty($specified_language) ? $language : $specified_language;

		if ($this->active && isset($this->data[$lang][$constant]))
			$result = $this->data[$lang][$constant];

		return $result;
	}

	/**
	 * Returns list of languages available
	 *
	 * @param boolean $printable What list should contain, printable text or language code
	 * @return array
	 */
	public function getLanguages($printable=true) {
		if (!$this->active)
			return;

		if ($printable)
			$result = $this->languages; else
			$result = array_keys($this->languages);

		return $result;
	}

	/**
	 * Return only short list of RTL languages
	 *
	 * @return array
	 */
	public function getRTL() {
		if (!$this->active)
			return;

		return $this->rtl_languages;
	}

	/**
	 * Returns default language
	 *
	 * @return string
	 */
	public function getDefaultLanguage() {
		$result = 'en';

		if (!is_null($this->default))
			$result = $this->default;

		return $result;
	}

	/**
	 * Check if current language is RTL (right-to-left)
	 *
	 * @param string $specified_language
	 * @return boolean
	 */
	public function isRTL($specified_language=null) {
		global $language;

		$language_to_check = is_null($specified_language) ? $language : $specified_language;
		return in_array($language_to_check, $this->rtl_languages);
	}
}

class MainLanguageHandler {
	private static $_instance;

	/**
	 * Core system language definitions
	 * @var resource
	 */
	var $language_system = null;

	/**
	 * Per-site language definitions
	 * @var resource
	 */
	var $language_local = null;

	private function __construct() {
		global $data_path;

		$this->language_system = new LanguageHandler();

		if (file_exists($data_path."language.xml"))
			$this->language_local = new LanguageHandler($data_path."language.xml");
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Returns localised text for given constant
	 *
	 * @param string $constant
	 * @param string $language
	 * @return string
	 */
	public function getText($constant, $language='') {
		$result = "";
		if (!is_null($this->language_local))
			$result = $this->language_local->getText($constant, $language);

		if (empty($result))
			$result = $this->language_system->getText($constant, $language);

		return $result;
	}

	/**
	 * Returns list of languages available
	 *
	 * @param boolean $printable What list should contain, printable text or language code
	 * @return array
	 */
	public function getLanguages($printable=true) {
		if (!is_null($this->language_local))
			$result = $this->language_local->getLanguages($printable); else
			$result = $this->language_system->getLanguages($printable);

		return $result;
	}

	/**
	 * Return short list of RTL languages on the system
	 *
	 * @return array
	 */
	public function getRTL() {
		if (!is_null($this->language_local))
			$result = $this->language_local->getRTL(); else
			$result = $this->language_system->getRTL();

		return $result;
	}

	/**
	 * Returns default language
	 *
	 * @return string
	 */
	public function getDefaultLanguage() {
		if (!is_null($this->language_local))
			$result = $this->language_local->getDefaultLanguage(); else
			$result = $this->language_system->getDefaultLanguage();

		return $result;
	}

	/**
	 * Check if current language is RTL (right-to-left)
	 *
	 * @param string $language
	 * @return boolean
	 */
	public function isRTL($language=null) {
		if (!is_null($this->language_local))
			$result = $this->language_local->isRTL($language); else
			$result = $this->language_system->isRTL($language);

		return $result;
	}
}

/**
 * Helper class for language handling.
 */
final class Language {
	private static $handler;

	/**
	 * Get localized value for specified constant and language.
	 *
	 * @param string $constant
	 * @param string $language
	 * @return string
	 */
	public static function getText($constant, $language='') {
		if (!isset(self::$handler))
			self::$handler = MainLanguageHandler::getInstance();

		return self::$handler->getText($constant, $language);
	}

	/**
	 * Returns list of languages available
	 *
	 * @param boolean $printable What list should contain, printable text or language code
	 * @return array
	 */
	public static function getLanguages($printable=true) {
		if (!isset(self::$handler))
			self::$handler = MainLanguageHandler::getInstance();

		return self::$handler->getLanguages($printable);
	}

	/**
	 * Returns default language
	 *
	 * @return string
	 */
	public static function getDefaultLanguage() {
		if (!isset(self::$handler))
			self::$handler = MainLanguageHandler::getInstance();

		return self::$handler->getDefaultLanguage();
	}

	/**
 	 * Check if currently selected language is right-to-left.
	 *
	 * @return boolean
	 */
	public static function isRTL($language=null) {
		if (!isset(self::$handler))
			self::$handler = MainLanguageHandler::getInstance();

		return self::$handler->isRTL($language);
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
		global $section, $language, $language_rtl;

		$default_language = self::getDefaultLanguage();
		$supported_languages = self::getLanguages(false);

		if (!isset($_REQUEST['language'])) {
			// no language change was specified, check session
			if (!isset($_SESSION['language']) || empty($_SESSION['language']))
				$_SESSION['language'] = self::matchBrowserLanguage($supported_languages, $default_language);

		} else {
			// language change was specified, make sure it's valid
			if (in_array($_REQUEST['language'], $supported_languages)) {
				$_SESSION['language'] = fix_chars($_REQUEST['language']);

			} else {
				// set language without asking if module is backend
				if (in_array($section, array('backend', 'backend_module')))
					$_SESSION['language'] = fix_chars($_REQUEST['language']); else
					$_SESSION['language'] = self::matchBrowserLanguage($supported_languages, $default_language);
			}
		}

		// store language to global variable
		$language = $_SESSION['language'];
		$language_rtl = self::isRTL($language);
	}
}

?>
