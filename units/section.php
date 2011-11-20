<?php

/**
 * Section Handler
 *
 * @author MeanEYE.rcf
 */

class SectionHandler {
	public $engine;
	public $active = false;

	/**
	 * Constructor
	 */
	public function __construct($file="") {
		global $data_path;

		$file = (empty($file)) ? $data_path.'system_section.xml' : $file;

		if (file_exists($file)) {
			$this->engine = new XMLParser(@file_get_contents($file), $file);
			$this->engine->Parse();
			$this->active = true;
		}
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

		$result = "";

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
				if ($xml_language->tagAttrs['name'] == $language || $xml_language->tagAttrs['name'] == "all") {
					$xml_actions = $xml_language->action;
					break;
				}

		if (!is_null($xml_actions) && count($xml_actions) > 0)
			foreach ($xml_actions as $xml_action)
				if ($xml_action->tagAttrs['name'] == $action) {
					$result = $xml_action->tagAttrs['file'];
					break;
				}

		return $result;
	}
}

/**
 * This manager is used only in index file. Sole purpose of this
 * object is to provide a separate section files.
 *
 * @author MeanEYE.rcf
 */
class MainSectionHandler {
	private static $_instance;
	private $section_system = null;
	private $section_local = null;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $data_path;

		$this->section_system = new SectionHandler();

		if (file_exists($data_path."section.xml"))
			$this->section_local = new SectionHandler($data_path."section.xml");
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
	 * Retrieves file for parsing
	 *
	 * @param string $section
	 * @param string $action
	 * @param string $language
	 * @return string
	 */
	public function getFile($section, $action, $language='') {
		$file = "";

		// check for site specific section definition
		if (!is_null($this->section_local))
			$file = $this->section_local->getFile($section, $action, $language);

		// in case local section definition does not exist, try system
		if (empty($file))
			$file = $this->section_system->getFile($section, $action, $language);

		return $file;
	}

	/**
	 * Transfers control to preconfigured template
	 *
	 * @param string $section
	 * @param string $action
	 * @param string $language
	 */
	public function transferControl($section, $action, $language='') {
		$file = $this->getFile($section, $action, $language);

		if (empty($file)) {
			// if no section is defined, check for module with the same name
			if (class_exists($section)) {
				$module = call_user_func(array(escape_chars($section), 'getInstance'));
				$params = array('action' => $action);

				// transfer control to module
				$module->transferControl($params, array());
			}

		} else {
			// section file is defined, load and parse it
			$template = new TemplateHandler($file);
			$template->parse();
		}
	}

}

?>
