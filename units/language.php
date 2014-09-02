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
			if (count($xml_language->constant) > 0)
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
	public function getLanguages($printable = true) {
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
	 * @return boolean
	 */
	public function isRTL() {
		global $language;

		return in_array($language, $this->rtl_languages);
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
	public function getLanguages($printable = true) {
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
	 * @return boolean
	 */
	public function isRTL() {
		if (!is_null($this->language_local))
			$result = $this->language_local->isRTL(); else
			$result = $this->language_system->isRTL();

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
	public static function getLanguages($printable = true) {
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
	public static function isRTL() {
		if (!isset(self::$handler))
			self::$handler = MainLanguageHandler::getInstance();

		return self::$handler->isRTL();
	}
}

?>
