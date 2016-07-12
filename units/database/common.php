<?php
/**
 * Commonly used database functions
 * Copyright (c) 2012. by Way2CU
 *
 * @author Mladen Mijatov
 */

require_once('mysql.php');
require_once('sqlite.php');

/**
 * Connect to database server using data from config file.
 */
function database_connect() {
	global $db_type, $db, $db_config;
	$result = false;

	// create database object
	switch ($db_type) {
		case DatabaseType::MYSQL:
			$db = new Database_MySQL();
			$connected = $db->connect($db_config);
			$selected = $db->select($db_config['name']);

			$result = $connected && $selected;

			// connection was successful but database doesn't exist
			if ($connected && (!$selected || ($selected && !ModuleManager::getInstance()->table_exists())))
				$result = database_initialize(!$selected);

			break;

		case DatabaseType::PGSQL:
			break;

		case DatabaseType::SQLITE:
			$db = new Database_SQLite();
			$result = $db->connect($db_config);

			// try to initialize database
			if (!$result && !$db->exists($db_config['name'])) {
				$result = $db->create($db_config['name']);

				if ($result)
					$result = database_initialize();
			}
			break;
	}

	return $result;
}

/**
 * Perform database initialization.
 *
 * @param boolean $create_database
 * @return boolean
 */
function database_initialize($create_database) {
	global $db, $db_config, $data_path;

	$result = false;
	$database_exists = false;
	$sql_file = 'units/database/init.sql';
	$xml_file = $data_path.'system_init.xml';

	if (!file_exists($sql_file) || !file_exists($xml_file)) {
		trigger_error('Can not initialize database, missing configuration!', E_USER_ERROR);
		return $result;
	}

	// make a log entry
	trigger_error('Initializing database: '.$db_config['name'], E_USER_NOTICE);

	// get initialization SQL
	$sql = file_get_contents($sql_file);

	// create database if needed
	if ($create_database) {
		try {
			$db->create($db_config['name']);
			$db->select($db_config['name']);
			$database_exists = true;

		} catch (Exception $error) {
			$database_exists = false;
		}
	} else {
		$database_exists = true;
	}

	// create database
	if ($database_exists && $db->multi_query($sql)) {
		$module_manager = ModuleManager::getInstance();
		$module_handler = ModuleHandler::getInstance();
		$admin_manager = UserManager::getInstance();

		// populate tables
		$raw_data = file_get_contents($xml_file);
		$data = new XMLParser($raw_data, $xml_file);
		$data->parse();

		// go over XML file and insert data
		foreach ($data->document->tagChildren as $item)
			switch ($item->tagName) {
				case 'module':
					// insert data
					$module_manager->insert_item(array(
									'name'		=> $item->tagAttrs['name'],
									'order'		=> $item->tagAttrs['order'],
									'preload'	=> $item->tagAttrs['preload'] == 'yes' ? 1 : 0,
									'active'	=> 1
								));

					// initialize module
					$module = $module_handler->loadModule($item->tagAttrs['name']);

					if (!is_null($module))
						$module->onInit();

					break;

				case 'user':
					$salt = hash('sha256', UserManager::SALT.strval(time()));
					$password = hash_hmac('sha256', $item->tagAttrs['password'], $salt);
					$data = array(
							'username'	=> $item->tagAttrs['username'],
							'password'	=> $password,
							'level'		=> $item->tagAttrs['level'],
							'verified'	=> 1,
							'salt'		=> $salt
						);

					// prepare user's name
					if (isset($item->tagAttrs['fullname'])) {
						$data['fullname'] = $item->tagAttrs['fullname'];
						$raw_data = explode(' ', $data['fullname'], 1);

						if (count($raw_data) == 2) {
							$data['first_name'] = $raw_data[0];
							$data['last_name'] = $raw_data[1];

						} else {
							$data['first_name'] = $data['fullname'];
							$data['last_name'] = '';
						}

					} else if (isset($item->tagAttrs['first_name'])) {
						$data['first_name'] = $item->tagAttrs['first_name'];
						$data['last_name'] = $item->tagAttrs['last_name'];
						$data['fullname'] = $data['first_name'].' '.$data['last_name'];
					}

					$admin_manager->insert_item($data);
					break;
			}

		// set result
		$result = true;
	}

	return $result;
}

?>
