<?php

/**
 * Module Handler
 *
 * @author MeanEYE.rcf
 */

class ModuleHandler {
	private static $_instance;

	/**
	 * Get single instance of ModuleHandler
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Load all modules in specified path
	 *
	 * @param string $path
	 */
	function loadModules() {
		$manager = ModuleManager::getInstance();

		// get priority module list
		$preload_list = $manager->getItems(
								$manager->getFieldNames(),
								array(
									'active' 	=> 1,
									'preload'	=> 1
								),
								array('order')
							);

		// get normal module list
		$normal_list = $manager->getItems(
								$manager->getFieldNames(),
								array(
									'active' 	=> 1,
									'preload'	=> 0
								),
								array('order')
							);

		$module_list = array_merge($preload_list, $normal_list);

		// load modules
		if (count($module_list) > 0)
			foreach($module_list as $module)
				$this->_loadModule($module->name);
	}

	/**
	 * Loads module from file and returns object
	 *
	 * @param string $filename
	 * @return resource
	 */
	public function _loadModule($name) {
		global $module_path;

		$result = null;
		$filename = $module_path.$name.DIRECTORY_SEPARATOR.$name.'.php';

		if (file_exists($filename)) {
			include_once($filename);

			$class = basename($filename, '.php');
			$result = call_user_func(array($class, 'getInstance'));
		}

		return $result;
	}
}

?>
