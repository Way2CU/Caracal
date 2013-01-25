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
	 * @param boolean $include_only
	 */
	function loadModules($include_only=false) {
		global $db_use, $data_path;

		$preload_list = array();
		$normal_list = array();

		if ($db_use) {
			// database available, form module list from database entries
			$manager = ModuleManager::getInstance();

			// get priority module list
			$preload_raw = $manager->getItems(
									$manager->getFieldNames(),
									array(
										'active' 	=> 1,
										'preload'	=> 1
									),
									array('order')
								);

			// get normal module list
			$normal_raw = $manager->getItems(
									$manager->getFieldNames(),
									array(
										'active' 	=> 1,
										'preload'	=> 0
									),
									array('order')
								);

			foreach ($preload_raw as $preload_item)
				$preload_list[] = $preload_item->name;

			foreach ($normal_raw as $normal_item)
				$normal_list[] = $normal_item->name;

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
		if (count($preload_list) > 0)
			foreach ($preload_list as $module_name)
				$this->_loadModule($module_name);

		if (count($normal_list) > 0)
			if ($include_only) {
				foreach($normal_list as $module_name)
					$this->_includeModule($module_name);

			} else {
				foreach($normal_list as $module_name)
					$this->_loadModule($module_name);
			}
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

		if (file_exists($filename) && $this->_checkDependencies($name)) {
			include_once($filename);

			$class = basename($filename, '.php');
			$result = call_user_func(array($class, 'getInstance'));
		}

		return $result;
	}

	/**
	 * Only include module file so it can be used only when needed.
	 *
	 * @param string $filename
	 */
	public function _includeModule($name) {
		global $module_path;

		$filename = $module_path.$name.DIRECTORY_SEPARATOR.$name.'.php';

		if (file_exists($filename) && $this->_checkDependencies($name)) 
			include_once($filename);
	}

	/**
	 * Check for module dependancies
	 * @param string $name
	 */
	private function _checkDependencies($name) {
		global $module_path;
	
		$result = true;
		$filename = $module_path.$name.DIRECTORY_SEPARATOR.'depends';
		$required = array();
		$optional = array();
		$mode = 0;

		if (file_exists($filename)) {
			$data = file_get_contents($filename);
			$data = explode('\n', $data);

			foreach ($data as $line) {
				if ($line == 'requires:')
					$mode = 1;

				if ($line == 'optional:')
					$mode = 2;

				if (!empty($line) && $mode > 0)
					switch ($mode) {
						case 1:
							$required[] = $line;
							break;

						case 2:
							$options[] = $line;
							break;
					}

			}

			foreach ($required as $required_module) 
				if (!class_exists($required_module)) {
					$result = false;
					trigger_error("Module '{$name}' requires '{$required_module}' but it's not available!");
					break;
				}
			
		}

		return $result;
	}
}

?>
