<?php

/**
 * Section Handler
 *
 * Author: Mladen Mijatov
 */

class SectionHandler {
	private static $_instance;
	public $engine;
	public $active = false;

	private $matched_file = null;
	private $matched_template = null;

	const PREFIX = '^(/(?<language>[a-z]{2}))?';
	const SUFFIX = '/?';
	const ROOT_KEY = '/';

	/**
	 * Constructor
	 */
	private function __construct() {
		global $data_path;

		$file = $data_path.'section.xml';

		if (file_exists($file)) {
			$this->engine = new XMLParser(@file_get_contents($file), $file);
			$this->engine->Parse();
			$this->active = true;
		}
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
	 * Match template based on URL and extract parameters.
	 */
	public function prepare() {
		$result = false;

		// try to match whole query string
		foreach ($this->data as $pattern => $template_file) {
			$match = preg_replace('|\{([\w\d\+-_]+)\}|iu', '(?<\1>[\w\d]+)', $pattern);
			$match = self::PREFIX.$match.self::SUFFIX;

			// successfully matched query string to template
			if (preg_match($match, $data, $matches)) {
				$this->matched_file = $template_file;
				$this->matched_template = $match;
				$result = true;
				break;
			}
		}

		// matching failed, try to load home template
		if (!$result)
			if (array_key_exists(self::ROOT_KEY, $this->data)) {
				$this->matched_file = $this->data[self::ROOT_KEY];
				$this->matched_template = self::ROOT_KEY;
				$result = true;
			}

		return $result;
	}

	/**
	 * Extract variables from URL and populate them to request array.
	 *
	 * @param string $pattern
	 */
	private function populate_variables($pattern) {
	}

	/**
	 * Find matching template and transfer control to it.
	 */
	public function transfer_control() {
		$found = false;

	}

	/**
	 * Retrieves file for parsing
	 *
	 * @param string $section
	 * @param string $action
	 * @param string $language
	 * @return string
	 */
	public function getFile($section, $action, $language='') {
		global $default_language;

		$result = '';

		if (!$this->active) return;
		$action = (empty($action)) ? '_default' : $action;
		$language = (empty($language)) ? $default_language : $language;

		$xml_languages = null;
		$xml_actions = null;

		// cycle through xml file and find the apropriate action
		foreach ($this->engine->document->section as $xml_section)
			if ($xml_section->tagAttrs['name'] == $section) {
				$xml_languages = $xml_section->language;
				break;
			}

		if (!is_null($xml_languages) && count($xml_languages) > 0)
			foreach ($xml_languages as $xml_language)
				if ($xml_language->tagAttrs['name'] == $language || $xml_language->tagAttrs['name'] == 'all') {
					if (array_key_exists('file', $xml_language->tagAttrs))
						$result = $xml_language->tagAttrs['file']; else
						$xml_actions = $xml_language->action;

					break;
				}

		if (empty($result) && !is_null($xml_actions) && count($xml_actions) > 0)
			foreach ($xml_actions as $xml_action)
				if ($xml_action->tagAttrs['name'] == $action) {
					$result = $xml_action->tagAttrs['file'];
					break;
				}

		return $result;
	}

	/**
	 * Transfers control to preconfigured template.
	 *
	 * @param string $section
	 * @param string $action
	 * @param string $language
	 */
	public function transferControl($section, $action, $language='') {
		$file = '';

		if (!_AJAX_REQUEST)
			$file = $this->getFile($section, $action, $language);

		if (_AJAX_REQUEST || empty($file)) {
			// request came from script, transfer control to modules
			if (ModuleHandler::is_loaded($section)) {
				$module = call_user_func(array(escape_chars($section), 'getInstance'));
				$params = array('action' => $action);

				// transfer control to module
				$module->transferControl($params, array());

			} else if ($section == 'backend_module' && ModuleHandler::is_loaded('backend')) {
				// transfer control to backend modules
				$module = backend::getInstance();
				$params = array('action' => 'transfer_control');

				// transfer control to module
				$module->transferControl($params, array());

			} else {
				// no matching module exist, try loading template
				$template = new TemplateHandler($file);
				$template->parse();
			}

		} else {
			// section file is defined, load and parse it
			$template = new TemplateHandler($file);
			$template->parse();
		}
	}
}

?>
