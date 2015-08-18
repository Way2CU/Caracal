<?php

/**
 * Base Module Class
 *
 * This class provides basic functions required for module to function. All modules
 * should extend this class and provide methods required.
 *
 * Copyright © 2015 Way2CU. All Rights Reserved.
 * Author: Mladen Mijatov
 */

namespace Core;

// temporary fallbacks
use \Language as Language;
use \LanguageHandler as LanguageHandler;
use \SettingsManager as SettingsManager;
use \TemplateHandler as TemplateHandler;


class AddActionError extends \Exception {}


abstract class Module {
	protected $language = null;
	protected $file;

	private $actions = array();
	private $backend_actions = array();

	public $name;
	public $path;
	public $settings = null;

	/**
	 * Constructor
	 *
	 * @return Module
	 */
	protected function __construct($file, $load_settings=True) {
		global $module_path;

		// detect module path
		if (substr($file, 0, strlen(_BASEPATH)) == _BASEPATH)
			$this->path = dirname($file).'/'; else
			$this->path = _BASEPATH.'/'.$module_path.'/'.get_class($this).'/';

		// store class name
		$this->name = get_class($this);

		// load language file if present
		$data_path = $this->path.'data/';
		if (file_exists($data_path))
			$this->language = new LanguageHandler($data_path);

		// load settings from database
		if ($load_settings)
			$this->settings = $this->loadSettings();
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function transferControl($params, $children) {
		$result = false;
		$action_name = isset($params['action']) ? $params['action'] : null;
		$backend_action = isset($params['backend_action']) ? $params['backend_action'] : null;

		// cache checks
		$action_exists = !is_null($action_name) && array_key_exists($action_name, $this->actions);
		$backend_action_exists = !is_null($backend_action) && array_key_exists($backend_action, $this->backend_actions);

		// call frontend action if defined
		if ($action_exists && $this->checkLicense()) {
			$config = $this->actions[$action_name];
			$action = $config[0];

			switch ($config[1]) {
				case self::PARAMS_XML:
					call_user_func_array($action->getCallable(), array($params, $children));
					break;

				case self::PARAMS_NONE:
				default:
					call_user_func($action->getCallable());
					break;
			}

			$result = true;
		}

		// call backend action if defined
		if ($backend_action_exists && $_SESSION['logged']) {
			$config = $this->backend_actions[$backend_action];
			$action = $config[0];

			switch ($config[1]) {
				case self::PARAMS_XML:
					call_user_func_array($action->getCallable(), array($params, $children));
					break;

				case self::PARAMS_NONE:
				default:
					call_user_func($action->getCallable());
					break;
			}

			$result = true;
		}

		return $result;
	}

	/**
	 * Create new action with specified name and add it to module.
	 *
	 * @param string $name
	 * @param string/array $callable
	 * @param integer $params
	 * @throws AddActionError
	 */
	protected function createAction($name, $callable, $params) {
		if (array_key_exists($name, $this->actions))
			throw new AddActionError("Action '{$name}' is already defined!");

		$this->actions[$name] = array($callable, $params);
	}

	/**
	 * Create new backend only action with specified name.
	 *
	 * @param string $name
	 * @param string/array $callable
	 * @param integer $params
	 * @throws AddActionError
	 */
	protected function createBackendAction($name, $callable, $params) {
		if (array_key_exists($name, $this->backend_actions))
			throw new AddActionError("Backend action '{$name}' is already defined!");

		$this->backend_actions[$name] = array($callable, $params);
	}

	/**
	 * Returns text for given module specific constant
	 *
	 * @param string $constant
	 * @param string $language
	 * @return string
	 */
	public function getLanguageConstant($constant, $language=null) {
		// make sure language is loaded
		if (is_null($this->language)) {
			trigger_error("Requested '{$constant}' but language file was not loaded for module '{$this->name}'.", E_USER_WARNING);
			return '';
		}

		$language_in_use = empty($language) ? $_SESSION['language'] : $language;
		$result = $this->language->getText($constant, $language_in_use);

		if (empty($result))
			$result = Language::getText($constant, $language_in_use);

		return $result;
	}

	/**
	 * Extracts multi-language field data and pack them in array.
	 *
	 * @param string $name
	 * @return array
	 */
	public function getMultilanguageField($name) {
		$result = array();
		$list = Language::getLanguages(false);

		foreach($list as $lang) {
			$param_name = "{$name}_{$lang}";
			$result[$lang] = escape_chars($_REQUEST[$param_name], false);
		}

		return $result;
	}

	/**
	 * This function is called every time module is initialized. Function
	 * is not called when module is temporarily turned off and then turned back
	 * on.
	 *
	 * Function should be use to create tables and files specific to module
	 * in question.
	 */
	public function onInit() {
	}

	/**
	 * Function called when module is disabled. This function should be used to
	 * clean up database and other module specific parts of the system.
	 */
	public function onDisable() {
	}

	/**
	 * Load and return module settings from database.
	 *
	 * @return array
	 */
	protected function loadSettings() {
		global $db, $db_use;

		$result = array();

		// make sure we have database connection
		if (!$db_use)
			return $result;

		// get manager
		$manager = SettingsManager::getInstance();

		// get values from the database
		$settings = $manager->getItems($manager->getFieldNames(), array('module' => $this->name));

		if (count($settings) > 0)
			foreach ($settings as $setting)
				$result[$setting->variable] = $setting->value;

		return $result;
	}

	/**
	 * Updates or creates new setting variable.
	 *
	 * @param string $var
	 * @param string $value
	 */
	protected function saveSetting($var, $value) {
		global $db, $db_use;

		// this method is only meant for used with database
		if (!$db_use)
			return;

		// get settings manager
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
			$path = '';
			$file_name = $params[$param_name];

			if (isset($params['local']) && $params['local'] == 1) {
				// load local template
				$path = $this->path.'templates/';
			} else if (isset($params['template_path'])) {
				// load template from specified path
				$path = $params['template_path'];
			}

		} else {
			// load template from module path
			$path = $this->path.'templates/';
			$file_name = $default_file;
		}

		// load template
		$template = new TemplateHandler($file_name, $path);
		$template->setMappedModule($this->name);

		return $template;
	}
}

?>
