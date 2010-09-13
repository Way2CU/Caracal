<?php

/**
 * LANGUAGE HANDLER
 *
 * @version 1.0
 * @author MeanEYE
 * @copyright RCF Group, 2008.
 */

if (!defined('_DOMAIN') || _DOMAIN !== 'RCF_WebEngine') die ('Direct access to this file is not allowed!');

class LanguageHandler {
	var $engine;
	var $active;

	/**
	 * Constructor
	 *
	 * @return LanguageHandler
	 */
	function __construct($file="") {
		global $data_path;

		$this->active = false;
		$file = (empty($file)) ? $data_path.'system_language.xml' : $file;

		if (file_exists($file)) {
			$this->engine = new XMLParser(@file_get_contents($file), $file);
			$this->engine->Parse();
			$this->active = true;
		}
	}

	/**
	 * Returns localised text for given constant
	 *
	 * @param string $constant
	 * @param string $language
	 * @return string
	 */
	function getText($constant, $lang='') {
		global $language;

		$result = '';

		if ($this->active) {
			if (empty($lang)) $lang = $language;  // use current language

			foreach ($this->engine->document->language as $xml_language)
				if ($xml_language->tagAttrs['short'] == $lang)
					foreach ($xml_language->constant as $xml_constant)
						if ($xml_constant->tagAttrs['name'] == $constant) {
							$result = empty($xml_constant->tagData) ? $xml_constant->tagAttrs['value'] : $xml_constant->tagData;
							break;
						}
		}

		return $result;
	}

	/**
	 * Returns list of languages available
	 *
	 * @param boolean $printable What list should contain, printable text or language code
	 * @return array
	 */
	function getLanguages($printable = true) {
		if (!$this->active) return;

		$result = array();

		foreach ($this->engine->document->language as $xml_language)
			if($printable)
				$result[$xml_language->tagAttrs['short']] = $xml_language->tagAttrs['name']; else
				$result[] = $xml_language->tagAttrs['short'];

		return $result;
	}

	/**
	 * Return only short list of RTL languages
	 *
	 * @return array
	 */
	function getRTL() {
		if (!$this->active) return;

		$result = array();

		foreach ($this->engine->document->language as $xml_language)
			if (isset($xml_language->tagAttrs['rtl']) && $xml_language->tagAttrs['rtl'] == 'yes')
				$result[] = $xml_language->tagAttrs['short'];

		return $result;
	}

	/**
	 * Returns default language
	 *
	 * @return string
	 */
	function getDefaultLanguage() {
		$result = 'en';

		foreach ($this->engine->document->language as $xml_language)
			if (key_exists('default', $xml_language->tagAttrs)) {
				$result = $xml_language->tagAttrs['short'];
				break;
			}

		return $result;
	}

	/**
	 * Check if current language is RTL (right-to-left)
	 *
	 * @return boolean
	 */
	function isRTL() {
		global $language;

		$result = false;

		foreach ($this->engine->document->language as $xml_language)
			if ($language == $xml_language->tagAttrs['short']) {
				$result = array_key_exists('rtl', $xml_language->tagAttrs) && strtolower($xml_language->tagAttrs['rtl']) == 'yes';
				break;
			}

		return $result;
	}
}

class MainLanguageHandler {
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

	function __construct() {
		global $data_path;

		$this->language_system = new LanguageHandler();

		if (file_exists($data_path."language.xml"))
			$this->language_local = new LanguageHandler($data_path."language.xml");
	}

	/**
	 * Returns localised text for given constant
	 *
	 * @param string $constant
	 * @param string $language
	 * @return string
	 */
	function getText($constant, $language='') {
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
	function getLanguages($printable = true) {
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
	function getRTL() {
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
	function getDefaultLanguage() {
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
	function isRTL() {
		if (!is_null($this->language_local))
			$result = $this->language_local->isRTL(); else
			$result = $this->language_system->isRTL();

		return $result;
	}
}

?>
