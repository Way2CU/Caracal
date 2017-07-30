<?php

/**
 * Module Handler
 *
 * Author: Mladen Mijatov
 */

// make sure constant exists, it was added in 5.4
if (!defined('OPENSSL_RAW_DATA'))
	define('OPENSSL_RAW_DATA', 1);


class InvalidKeyException extends Exception{};


class ModuleHandler {
	private static $_instance;
	private static $loaded_modules = array();
	const CIPHER = 'aes-256-ctr';

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
				$xml = new XMLParser(@file_get_contents($file), $file);
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
	 * If omitted module list defaults to all modules.
	 *
	 * Options is associative array which is passed on to modules during export call.
	 * Keys and values it can contain can be customized but for standardization purpose
	 * the following list must be supported if applicable:
	 *
	 *	- include_files: boolean - Whether files should be included in export;
	 *	- description: string - Description for backup file, handled by the system. Modules
	 *		should ignore this.
	 *
	 * @param string $key
	 * @param string $file_name
	 * @param array $options
	 * @param array $modules
	 * @return boolean
	 */
	public static function export_data($key, $file_name, $options=array(), $modules=null) {
		global $backup_path;

		// increase security a little bit by extending key length through hash function
		// as people don't have a tendency to choose long passwords
		$key = hash('sha512', $key, true);

		// get size of initialization vector to use
		$iv_size = openssl_cipher_iv_length(self::CIPHER);

		// default export data structure
		$result = array(
			'timestamp'       => date('c'),
			'domain'          => _DOMAIN,
			'description'     => isset($options['description']) ? $options['description'] : '',
			'key_hash'        => hash('sha256', $key),  // used for password verification
			'encryption'      => self::CIPHER,
			'module_data'     => array(),
			'module_settings' => array()
		);

		// bail if no modules are specified
		$modules = is_null($modules) ? self::$loaded_modules : $modules;
		if (empty($modules))
			return false;

		// get settings manager instance for later use
		$manager = SettingsManager::get_instance();

		// collect export data from each module
		foreach ($modules as $module_name) {
			// skip modules which are not loaded
			if (!self::is_loaded($module_name))
				continue;

			// get module instance and its data
			$module = call_user_func(array($module_name, 'get_instance'));
			$raw_data = serialize($module->export_data($options));

			// encrypt module data
			$data_iv = openssl_random_pseudo_bytes($iv_size);
			$data = openssl_encrypt($raw_data, self::CIPHER, $key, OPENSSL_RAW_DATA, $data_iv);

			// get settings from systems table
			$variable_list = $manager->get_items(array('variable', 'value'), array('module' => $module_name));
			$raw_settings = array();
			if (count($variable_list) > 0)
				foreach($variable_list as $variable => $value)
					$raw_settings[$variable] = $value;
			$raw_settings = serialize($raw_settings);

			// encrypt settings
			$settings_iv = openssl_random_pseudo_bytes($iv_size);
			$settings = openssl_encrypt($raw_settings, self::CIPHER, $key, OPENSSL_RAW_DATA, $settings_iv);

			// store encrypted data
			$result['module_data'][$module_name] = base64_encode($data_iv.$data);
			$result['module_settings'][$module_name] = base64_encode($settings_iv.$settings);
		}

		// make sure storage path exists
		if (!file_exists($backup_path))
			if (mkdir($backup_path, 0775, true) === false) {
				trigger_error('Module handler: Error creating backup storage directory.', E_USER_WARNING);
				return false;
			}

		// save backup file
		file_put_contents($backup_path.$file_name, json_encode($result));

		return true;
	}

	/**
	 * Load specified file and restore backup for specified modules.
	 *
	 * The following options are recognized:
	 *	- include_files: boolean - Whether files should be included in export;
	 *	- include_settings: boolean - Whether module settings should be included. This
	 *		one is handled by the system itself modules can ignore it;
	 *
	 * @param string $key
	 * @param string $file_name
	 * @param array $options
	 * @param array $modules
	 * @return boolean
	 */
	public static function import_data($key, $file_name, $options=array(), $modules=null) {
		global $backup_path;

		// load and parse backup file
		if (!file_exists($backup_path.$file_name.'.backup'))
			return false;

		$backup = json_decode(file_get_contents($backup_path.$file_name.'.backup'), true);
		if ($backup === NULL)
			return false;

		// bail if no modules are specified
		$modules = is_null($modules) ? self::$loaded_modules : $modules;
		if (empty($modules))
			return false;

		// increase security a little bit by extending key length through hash function
		// as people don't have a tendency to choose long passwords
		$key = hash('sha512', $key, true);

		// verify key validity
		if (hash('sha256', $key) != $backup['key_hash'])
			throw new InvalidKeyException('Backup key does not match provided key. Unable to restore!');

		// get size of initialization vector to use
		$iv_size = openssl_cipher_iv_length(self::CIPHER);

		// prepare commonly used variables
		$manager = SettingsManager::get_instance();
		$import_settings = isset($options['import_settings']) && $options['import_settings'];

		// process data
		foreach ($modules as $module_name) {
			// skip modules which are not loaded
			if (!self::is_loaded($module_name))
				continue;

			// clear existing and import new settings for specific module
			if ($import_settings && isset($backup['module_settings'][$module_name])) {
				$raw_settings = base64_decode($backup['module_settings'][$module_name]);

				// get initialization vector for settings
				$settings_iv = substr($raw_settings, 0, $iv_size);
				$raw_settings = substr($raw_settings, $iv_size);

				// decrypt settings data
				$raw_settings = openssl_decrypt($raw_settings, self::CIPHER, $key, OPENSSL_RAW_DATA, $settings_iv);

				// restore settings
				if ($raw_settings !== FALSE) {
					$settings = unserialize($raw_settings);

					$manager->delete_items(array('module' => $module_name));
					foreach ($settings as $variable => $value)
						$manager->insert_item(array(
								'module'   => $module_name,
								'variable' => $variable,
								'value'    => $value
							));
				}
			}

			// restore module data
			if (isset($backup['module_data'][$module_name])) {
				// get initialization vector for data
				$raw_data = base64_decode($backup['module_data'][$module_name]);
				$data_iv = substr($raw_data, 0, $iv_size);
				$raw_data = substr($raw_data, $iv_size);

				// decrypt module data
				$raw_data = openssl_decrypt($raw_data, self::CIPHER, $key, OPENSSL_RAW_DATA, $data_iv);

				// pass the remaining data to module
				if ($raw_data !== FALSE) {
					$data = unserialize($raw_data);
					$module = call_user_func(array($module_name, 'get_instance'));
					$module->import_data($data, $options);
				}
			}
		}

		return true;
	}
}

?>
