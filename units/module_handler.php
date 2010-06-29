<?php

/**
 * MODULE HANDLER
 * 
 * @version 1.0
 * @author MeanEYE
 * @copyright RCF Group, 2008.
 */

if (!defined('_DOMAIN') || _DOMAIN !== 'RCF_WebEngine') die ('Direct access to this file is not allowed!');

class ModuleHandler {
	var $modules;
	
	/**
	 * Constructor
	 *
	 * @return ModuleHandler
	 */
	function ModuleHandler() {
		$this->modules = array();
	}
	
	/**
	 * Registers module in handler
	 *
	 * @param string $name
	 * @param string $version
	 * @param string $file
	 * @param pointer $object
	 */
	function registerModule($file, &$object) {
		$this->modules[$object->name] = array('object' => &$object, 'file' => $file);
		
		$object->onRegister();
	}
	
	/**
	 * Returns module object for specified name
	 *
	 * @param string $name
	 * @return object
	 */
	function getObjectFromName($name) {
		return $this->modules[$name]['object'];
	}

	/**
	 * Check if module with specified name exists
	 *
	 * @param string $name
	 * @return boolean
	 */
	function moduleExists($name) {
		if (!empty($this->modules))
			return array_key_exists($name, $this->modules); else 
			return false;
	}
	
	/**
	 * Loads module from file and returns object
	 *
	 * @param string $filename
	 * @return resource
	 */
	function loadModuleFromFile($filename) {
		if (!file_exists($filename)) return null; 
		
		include_once($filename);
		
		$class = basename($filename, '.php');
		$module = new $class;
		
		return $module;
	}
	
	/**
	 * Load module by its name
	 * 
	 * @param string $name
	 * @return resource
	 */
	function loadModule($name) {
		global $module_path;
		
		$module_file = $module_path.$name.DIRECTORY_SEPARATOR.$name.".php";
		return $this->loadModuleFromFile($module_file);
	}
	
	/**
	 * Load all modules in specified path
	 *
	 * @param string $path
	 */
	function loadModules() {
		global $module_path, $db, $db_active;
		
		if (!$db_active == 1) return ;

		$module_list = $db->get_results("SELECT `name` FROM `system_modules` WHERE `preload` = 0 AND `active` = 1 ORDER BY `order` ASC");
		if (count($module_list) > 0)
		foreach ($module_list as $module) 
			$this->loadModuleFromFile($module_path.$module->name.DIRECTORY_SEPARATOR.$module->name.'.php');
	}
	
	/**
	 * Loads priority modules
	 *
	 * @param string $path
	 */
	function loadPriorityModules() {
		global $db, $db_active, $module_path;
		
		if (!$db_active == 1) return ;
		
		$module_list = $db->get_results("SELECT `name` FROM `system_modules` WHERE `preload` = 1 AND `active` = 1 ORDER BY `order` ASC");
		if (count($module_list) > 0)
		foreach ($module_list as $module) 
			$this->loadModuleFromFile($module_path.$module->name.DIRECTORY_SEPARATOR.$module->name.'.php');
	}
	
	/**
	 * Checks for valid file extension
	 *
	 * @param string $filename
	 * @return boolean
	 */
	function __checkExtension($filename) {
		$res = false;
		$ext = 'php';
		$testExt = "\.".$ext."$";
	
		$res = eregi($testExt, $filename);
		return $res;
	}

	/**
	 * Returns list for directories containing modules
	 *
	 * @param string $path
	 * @return array
	 */
	function __getModuleList ($path) {
		$res = array();

		if (is_dir($path)) {
		    if ($dh = opendir($path)) {
		        while (($sub_dir = readdir($dh)) !== false)
		        if (is_dir($path.$sub_dir) && $sub_dir[0] !== '.')
					$res[] = $sub_dir;
		        closedir($dh);
		    }
		}

		return $res;
	}
		
	/**
	 * Returns list of files inside given path
	 *
	 * @param string $path
	 * @return array
	 */
	function __getFileList ($path) {
		$res = array();
		
		if (is_dir($path)) {
		    if ($dh = opendir($path)) {
		        while (($file = readdir($dh)) !== false)
		        if ($this->__checkExtension($file))
					$res[] = $file;            
		        closedir($dh);
		    }
		}
		return $res;
	}
}

?>
