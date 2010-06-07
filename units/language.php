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
	function LanguageHandler($file="") {
		global $site_path;

		$this->active = false;
		$file = (empty($file)) ? $site_path.'language.xml' : $file;

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
	function getText($constant, $language='') {
		global $default_language;

		if (!$this->active) return '';
		$result = '';
		$language = (empty($language)) ? $default_language : $language;

		foreach ($this->engine->document->language as $xml_language)
			if ($xml_language->tagAttrs['short'] == $language)
				foreach ($xml_language->constant as $xml_constant)
					if ($xml_constant->tagAttrs['name'] == $constant) {
						$result = empty($xml_constant->tagData) ? $xml_constant->tagAttrs['value'] : $xml_constant->tagData;
						break;
					}

		return $result;
	}

	/**
	 * Draws localised text for given constant
	 *
	 * @param string $constant
	 * @param string $language
	 */
	function drawText($constant, $pre_text='', $language='') {
		if (!$this->active) return;

		$text = $this->getText($constant, $language);
		echo $text;
	}

	/**
	 * Returns list of languages available
	 *
	 * @param boolean $printable What list should contain, printable text or language code
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

?>
