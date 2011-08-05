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
		global $db_use, $data_path;

		$module_list = array();

		if ($db_use) {
			// database available, form module list from database entries
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

			// add each of preload items to list
			foreach ($preload_list as $module)
				$module_list[] = $module->name;

			// add each of normal items to list
			foreach ($normal_list as $module)
				$module_list[] = $module->name;

		} else {
			// no database available try to load from XML file
			$file = $data_path.'modules.xml';

			if (file_exists($file)) {
				$xml = new XMLParser(@file_get_contents($file), $file);
				$xml->Parse();

				foreach ($xml->document->module as $xml_module) {
					$module_list[] = $xml_module->tagAttrs['name'];
				}
			}
		}

		// load modules
		if (count($module_list) > 0)
			foreach($module_list as $module_name)
				$this->_loadModule($module_name);
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
