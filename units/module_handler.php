<?php

/**
 * Module Handler
 *
 * Author: Mladen Mijatov
 */
require_once('export_file.php');

use Core\Exports;


class ModuleHandler {
	private static $_instance;
	private static $loaded_modules = array();

	/**
	 * Get single instance of ModuleHandler
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Load all modules in specified path
	 *
	 * @param boolean $include_only
	 */
	public function load_modules($include_only=false) {
		global $db, $data_path;

		$preload_list = array();
		$normal_list = array();

		if (!is_null($db)) {
			// database available, form module list from database entries
			$manager = ModuleManager::get_instance();

			// get priority module list
			$preload_raw = $manager->get_items(
									$manager->get_field_names(),
									array(
										'active' 	=> 1,
										'preload'	=> 1
									),
									array('order')
								);

			// get normal module list
			$normal_raw = $manager->get_items(
									$manager->get_field_names(),
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
			// no database available use system initialization file
			$file = $data_path.'system_init.xml';

			if (file_exists($file)) {
				$xml = new Core\XML\Parser(@file_get_contents($file), $file);
				$xml->Parse();

				foreach ($xml->document->tagChildren as $xml_tag)
					if ($xml_tag->tagName == 'module')
						$normal_list[] = $xml_tag->tagAttrs['name'];
			}
		}

		// load modules
		if (count($preload_list) > 0)
			foreach ($preload_list as $module_name)
				$this->load_module($module_name);

		if (count($normal_list) > 0)
			if ($include_only) {
				foreach($normal_list as $module_name)
					$this->include_module($module_name);

			} else {
				foreach($normal_list as $module_name)
					$this->load_module($module_name);
			}
	}

	/**
	 * Loads module from file and returns object
	 *
	 * @param string $filename
	 * @return resource
	 */
	public function load_module($name) {
		global $module_path, $system_module_path;

		$result = null;
		$filename = $module_path.$name.'/'.$name.'.php';
		$system_filename = $system_module_path.$name.'/'.$name.'.php';

		// try to load user define plugin
		if (file_exists($filename)) {
			include_once($filename);

			$class = basename($filename, '.php');
			$result = call_user_func(array($class, 'get_instance'));

		} else if (file_exists($system_filename)) {
			// no user plugin, try to load system
			include_once($system_filename);

			$class = basename($system_filename, '.php');
			$result = call_user_func(array($class, 'get_instance'));
		}

		// add module to the list of loaded ones
		if (!is_null($result))
			self::$loaded_modules[] = $name;

		return $result;
	}

	/**
	 * Only include module file so it can be used only when needed.
	 *
	 * @param string $filename
	 */
	public function include_module($name) {
		global $module_path, $system_module_path;

		$result = false;
		$filename = $module_path.$name.'/'.$name.'.php';
		$system_filename = $system_module_path.$name.'/'.$name.'.php';

		if (file_exists($filename) && $this->check_dependencies($name)) {
			include_once($filename);
			$result = true;

		} else if (file_exists($system_filename) && $this->check_dependencies($name)) {
			include_once($system_filename);
			$result = true;
		}

		// add module to the list of loaded ones
		if ($result)
			self::$loaded_modules[] = $name;
	}

	/**
	 * Check for module dependancies
	 * @param string $name
	 */
	private function check_dependencies($name) {
		global $module_path, $system_module_path;

		$result = true;
		$required = array();
		$optional = array();
		$mode = 0;

		// default to user module
		$filename = $module_path.$name.'/depends';

		// try system module
		if (!file_exists($filename))
			$filename = $system_module_path.$name.'/depends';

		// check dependencies
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
				if (!self::is_loaded($required_module)) {
					$result = false;
					trigger_error("Module '{$name}' requires '{$required_module}' but it's not available!");
					break;
				}

		}

		return $result;
	}

	/**
	 * Check if specified module is loaded.
	 *
	 * @param string name
	 * @return boolean
	 */
	public static function is_loaded($name) {
		return in_array($name, self::$loaded_modules);
	}

	/**
	 * Export data from specified list of `$modules` as JSON encoded string with
	 * individual data encrypted with specified `$key`. Encryption is mandatory to
	 * avoid potential issues with neglected backups. Output is then placed in
	 * globally defined `$backup_path` with specified `$file_name`.
	 *
	 * If omitted module list defaults to all modules. Existing files are never
	 * overwritten, function returns `false` in that case.
	 *
	 * Options is associative array which is passed on to modules during export call.
	 * Keys and values it can contain can be customized but for standardization purpose
	 * the following list must be supported if applicable:
	 *
	 *	- include_files: boolean - Whether files should be included in export;
	 *	- description: string - Description for backup file, handled by the system. Modules
	 *		should ignore this.
	 *
	 * When calling `export` on each individual module, handler will pass current
	 * export file to provide modules with opportunity to store custom data and files.
	 *
	 * @param string $key
	 * @param string $file_name
	 * @param array $options
	 * @param array $modules
	 * @return boolean
	 */
	public static function export_data($key, $file_name, $options=array(), $modules=null) {
		global $backup_path;

		// bail if no modules are specified
		$modules = is_null($modules) ? self::$loaded_modules : $modules;
		if (empty($modules))
			return false;

		// refuse to overwrite existing exports, opening existing file would
		// make it read-only and verify key against the old one
		if (file_exists($backup_path.basename($file_name)))
			return false;

		// get settings manager instance for later use
		$manager = SettingsManager::get_instance();

		// create new exports file
		$file = new Core\Exports\File($file_name, $key);

		// collect export data from each module, discarding
		// partial file if anything goes wrong along the way
		try {
			$file->write(Core\Exports\Section::TIMESTAMP, date('c'));
			$file->write(Core\Exports\Section::DOMAIN, _DOMAIN);
			if (isset($options['description']))  // write non-encrypted description
				$file->write(Core\Exports\Section::DESCRIPTION, $options['description'], null, false);

			foreach ($modules as $module_name) {
				// skip modules which are not loaded
				if (!self::is_loaded($module_name))
					continue;

				// get module instance and its data
				$module = call_user_func(array($module_name, 'get_instance'));
				$raw_data = serialize($module->export_data($options, $file));

				// get settings from systems table
				$variable_list = $manager->get_items(array('variable', 'value'), array('module' => $module_name));
				$raw_settings = array();
				if (count($variable_list) > 0)
					foreach($variable_list as $variable => $value)
						$raw_settings[$variable] = $value;
				$raw_settings = serialize($raw_settings);

				// store encrypted data
				$file->write(Core\Exports\Section::DATA, $raw_data, $module_name);
				$file->write(Core\Exports\Section::SETTINGS, $raw_settings, $module_name);
			}

			$file->close();

		} catch (Throwable $error) {
			$file->discard();
			throw $error;
		}

		return true;
	}

	/**
	 * Load specified export file and restore data and settings for specified
	 * modules. Lists of modules for data and settings are independent, `null`
	 * restores everything present in the file while empty array restores nothing.
	 * Modules which are not loaded are skipped.
	 *
	 * Options are passed on to modules during import call. The following are
	 * recognized:
	 *	- include_files: boolean - Whether files should be restored as well;
	 *
	 * Exceptions from export file are propagated, most notably
	 * `Core\Exports\InvalidKeyException` and `Core\Exports\InvalidExportException`.
	 *
	 * @param string $key
	 * @param string $file_name
	 * @param array $options
	 * @param array $data_modules
	 * @param array $settings_modules
	 * @return boolean
	 */
	public static function import_data($key, $file_name, $options=array(), $data_modules=null, $settings_modules=null) {
		global $backup_path;

		// opening nonexistent file would create it, make sure it's there first
		if (!is_file($backup_path.basename($file_name)))
			throw new Core\Exports\InvalidExportException('Export file does not exist.');

		// open export file, this verifies key as well
		$file = new Core\Exports\File($file_name, $key);

		// default to everything stored in the file
		$key_list_data = $file->get_key_names(Core\Exports\Section::DATA);
		$key_list_settings = $file->get_key_names(Core\Exports\Section::SETTINGS);

		if (is_null($data_modules))
			$data_modules = $key_list_data;
		if (is_null($settings_modules))
			$settings_modules = $key_list_settings;

		// get settings manager instance for later use
		$manager = SettingsManager::get_instance();

		// restore data and settings for each module
		$module_list = array_unique(array_merge($key_list_data, $key_list_settings), SORT_REGULAR);
		foreach ($module_list as $module_name) {
			// skip modules which are not loaded
			if (!self::is_loaded($module_name))
				continue;

			$module = call_user_func(array($module_name, 'get_instance'));

			// restore module data
			if (in_array($module_name, $data_modules) && in_array($module_name, $key_list_data)) {
				$data = $file->read(Core\Exports\Section::DATA, $module_name);
				$data = unserialize($data, array('allowed_classes' => array('stdClass')));
				$module->import_data($data, $options, $file);
			}

			// restore module settings
			if (in_array($module_name, $settings_modules) && in_array($module_name, $key_list_settings)) {
				$data = $file->read(Core\Exports\Section::SETTINGS, $module_name);
				$data = unserialize($data, array('allowed_classes' => array('stdClass')));

				if ($data !== FALSE)
					// replace variable value instead of calling
					// update, this way we ensure value is inserted
					foreach ($data as $row) {
						$manager->delete_items(array(
							'module'   => $module_name,
							'variable' => $row->variable
						));
						$manager->insert_item(array(
							'module'   => $module_name,
							'variable' => $row->variable,
							'value'    => $row->value
						));
					}
			}
		}

		$file->close();

		return true;
	}
}

?>
