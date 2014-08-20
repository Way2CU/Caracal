<?php

/**
 * Base Module Class
 *
 * Author: Mladen Mijatov
 */
namespace Core;

// temporary fallbacks
use \LanguageHandler as LanguageHandler;
use \SettingsManager as SettingsManager;
use \TemplateHandler as TemplateHandler;
use \MainLanguageHandler as MainLanguageHandler;


abstract class Module {
	protected $language;
	protected $file;

	public $name;
	public $path;
	public $settings;

	/**
	 * Constructor
	 *
	 * @return Module
	 */
	protected function __construct($file, $load_settings=True) {
		$this->path = dirname($file).'/';
		$this->name = get_class($this);
		$language_file = $this->path.'data/language.xml';

		// load language file if present
		if (file_exists($language_file))
			$this->language = new LanguageHandler($language_file);

		// load settings from database
		if ($load_settings)
			$this->settings = $this->getSettings();
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	abstract public function transferControl($params, $children);

	/**
	 * Returns text for given module specific constant
	 *
	 * @param string $constant
	 * @param string $language
	 * @return string
	 */
	public function getLanguageConstant($constant, $language='') {
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
		global $db;

		$result = array();
		$list = MainLanguageHandler::getInstance()->getLanguages(false);

		foreach($list as $lang) {
			$value = '';
			$param_name = "{$name}_{$lang}";

			// properly escape string
			if (isset($_REQUEST[$param_name]))
				if ($db->is_active())
					$value = $db->escape_string($_REQUEST[$param_name]); else
					$value = mysql_real_escape_string($_REQUEST[$param_name]);

			$result[$lang] = $value;
		}

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
	abstract public function onInit();

	/**
	 * Event called upon module removal
	 */
	abstract public function onDisable();

	/**
	 * Returns module defined variables
	 *
	 * @return array
	 */
	protected function getSettings() {
		global $db, $db_use;

		// this method is only meant to be used with database
		if (!$db_use)
			return;

		// get manager
		$manager = SettingsManager::getInstance();
		$result = array();

		// get values from the database
		$settings = $manager->getItems($manager->getFieldNames(), array('module' => $this->name));

		if (count($settings) > 0)
			foreach ($settings as $setting)
				$result[$setting->variable] = $setting->value;

		return $result;
	}

	/**
	 * Updates or creates new variable in module settings
	 *
	 * @param string $var
	 * @param string $value
	 */
	protected function saveSetting($var, $value) {
		global $db, $db_use;

		// this method is only meant for used with database
		if (!$db_use)
			return;

		// get manager
		$manager = SettingsManager::getInstance();

		// check if specified setting already exists
		$setting = $manager->getSingleItem(
								array('id'),
								array(
									'module'	=> $this->name,
									'variable'	=> $var
								));

		// update or insert data
		if (is_object($setting)) {
			$manager->updateData(
						array('value' => $value),
						array('id' => $setting->id)
					);

		} else {
			$manager->insertData(array(
						'module'	=> $this->name,
						'variable'	=> $var,
						'value'		=> $value
					));
		}
	}

	/**
	 * Create TemplateHandler object from specified tag params
	 *
	 * @param array $params
	 * @param string $default_file
	 * @return TemplateHandler
	 */
	public function loadTemplate($params, $default_file, $param_name='template') {
		if (isset($params[$param_name])) {
			if (isset($params['local']) && $params['local'] == 1) {
				// load local template
				$template = new TemplateHandler($params[$param_name], $this->path.'templates/');

			} else if (isset($params['template_path'])) {
				// load template from specified path
				$template = new TemplateHandler($params[$param_name], $params['template_path']);

			} else {
				// load template from absolute path
				$template = new TemplateHandler($params[$param_name]);
			}
		} else {
			// load template from module path
			$template = new TemplateHandler($default_file, $this->path.'templates/');
		}

		$template->setMappedModule($this->name);

		return $template;
	}
}

?>
