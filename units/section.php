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
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Retrieves file for parsing
	 *
	 * @param string $section
	 * @param string $action
	 * @param string $language
	 * @return string
	 */
	public function get_file($section, $action, $language='') {
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
	public function transfer_control($section, $action, $language='') {
		$file = '';

		if (!_AJAX_REQUEST)
			$file = $this->get_file($section, $action, $language);

		if (_AJAX_REQUEST || empty($file)) {
			// request came from script, transfer control to modules
			if (ModuleHandler::is_loaded($section)) {
				$module = call_user_func(array(escape_chars($section), 'get_instance'));
				$params = array('action' => $action);

				// transfer control to module
				$module->transfer_control($params, array());

			} else if ($section == 'backend_module' && ModuleHandler::is_loaded('backend')) {
				// transfer control to backend modules
				$module = backend::get_instance();
				$params = array('action' => 'transfer_control');

				// transfer control to module
				$module->transfer_control($params, array());

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
