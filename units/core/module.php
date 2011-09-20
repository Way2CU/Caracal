<?php

/**
 * Base Module Class
 *
 * @author MeanEYE.rcf
 */

class Module {
	protected $language;
	protected $sections;
	protected $file;
	public $name;
	public $path;
	public $settings;

	/**
	 * Constructor
	 *
	 * @return Module
	 */
	protected function __construct($file) {
		$this->path = dirname($file).'/';
		$this->name = get_class($this);
		$this->language = new LanguageHandler($this->path.'/data/language.xml');
		$this->sections = new SectionHandler($this->path.'/data/section.xml');

		$this->settings = $this->getSettings();
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param string $action
	 * @param integer $level
	 */
	public function transferControl($level, $params = array(), $children=array()) {

	}

	/**
	 * Returns module mapped section file relative to module path
	 *
	 * @param string $section
	 * @param string $action
	 * @param string $language
	 * @return string
	 */
	public function getSectionFile($section, $action, $language="") {
		return $this->path.$this->sections->getFile($section, $action, $language);
	}

	/**
	 * Returns text for given module specific constant
	 *
	 * @param string $constant
	 * @param string $language
	 * @return string
	 */
	public function getLanguageConstant($constant, $language="") {
		$language_in_use = empty($language) ? $_SESSION['language'] : $language;
		$result = $this->language->getText($constant, $language_in_use);

		if (empty($result))
			$result = MainLanguageHandler::getInstance()->getText($constant, $language_in_use);

		return $result;
	}

	/**
	 * Extracts multi-language field data and pack them in array
	 *
	 * @param string $name
	 * @return array
	 */
	public function getMultilanguageField($name) {
		$result = array();
		$list = MainLanguageHandler::getInstance()->getLanguages(false);

		foreach($list as $lang)
			$result[$lang] = isset($_REQUEST["{$name}_{$lang}"]) ? $_REQUEST["{$name}_{$lang}"] : '';

		return $result;
	}

	/**
	 * Check license for current module
	 * @return boolean
	 */
	protected function checkLicense() {
		$result = false;
		$license = isset($_REQUEST['key']) ? fix_chars($_REQUEST['key']) : null;

		if (class_exists('license')) {
			$license = license::getInstance();
			$result = $license->isLicenseValid($this->name, $license);
		}

		return $result;
	}

	/**
	 * Event called upon module initialisation
	 */
	public function onInit() {
	}

	/**
	 * Event called upon module removal
	 */
	public function onDisable() {
	}

	/**
	 * Returns module defined variables
	 *
	 * @return array
	 */
	protected function getSettings() {
		global $db, $db_active;
		$result = array();

		if ($db_active == 1) {
			$settings = $db->get_results("SELECT `variable`, `value` FROM `system_settings` WHERE `module` = '$this->name' ORDER BY `variable` ASC");

			if ($db->num_rows > 0)
				foreach ($settings as $setting)
					$result[$setting->variable] = $setting->value;
		}
		return $result;
	}

	/**
	 * Create TemplateHandler object from specified tag params
	 *
	 * @param array $params
	 * @param string $default_file
	 * @return TemplateHandler
	 */
	public function loadTemplate($params, $default_file) {
		if (isset($params['template'])) {
			if (isset($params['local']) && $params['local'] == 1) {
				// load local template
				$template = new TemplateHandler($params['template'], $this->path.'templates/');

			} else if (isset($params['template_path'])) {
				// load template from specified path
				$template = new TemplateHandler($params['template'], $params['template_path']);

			} else {
				// load template from absolute path
				$template = new TemplateHandler($params['template']);
			}
		} else {
			// load template from module path
			$template = new TemplateHandler($default_file, $this->path.'templates/');
		}

		$template->setMappedModule($this->name);

		return $template;
	}

	/**
	 * Updates or creates new variable in module settings
	 *
	 * @param string $var
	 * @param string $value
	 */
	protected function saveSetting($var, $value) {
		global $db;

		// TODO: Make usage of Item Manager
		$select_query = "SELECT count(`id`) FROM `system_settings` WHERE
						`module` = '{$this->name}' AND `variable` = '{$var}'";

		$update_query = "UPDATE `system_settings`
						SET `value` = '{$value}'
						WHERE `variable` = '{$var}' AND `module` = '{$this->name}'";

		$insert_query = "INSERT INTO `system_settings` (`module`,`variable`,`value`)
						VALUES ('{$this->name}', '{$var}', '{$value}')";

		$update = $db->get_var($select_query) > 0;
		$db->query( ($update) ? $update_query : $insert_query );
	}

}

?>
