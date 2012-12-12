<?php
/**
 * Commonly used database functions
 * Copyright (c) 2012. by Way2CU
 *
 * @author Mladen Mijatov
 */

require_once('mysql.php');

/**
 * Connect to database server using data from config file.
 */
function database_connect() {
	global $db_type, $db, $db_config;
	$result = false;

	// create database object
	switch ($db_type) {
		case DatabaseType::MYSQL:
			if (class_exists('Database_MySQL')) 
				$db = new Database_MySQL();
			break;

		case DatabaseType::PGSQL:
			if (class_exists('Database_PostgreSQL'))
				$db = new Database_PostgreSQL();
			break;

		case DatabaseType::SQLITE:
			if (class_exists('Database_SQLite'))
				$db = new Database_SQLite();
			break;
	}

	// connect database
	if (!is_null($db))
		$result = $db->connect($db_config);

	// we are connected, select database
	if ($result) 
		$result = $db->select($db_config['name']); else
		trigger_error('Error connecting to database with specified credentials!', E_USER_ERROR);

	// select failed, we probably need to initialize database
	if (!$result)
		$result = database_initialize();

	return $result;
}

/**
 * Perform database initialization.
 */
function database_initialize() {
	global $db, $db_config, $data_path;

	$result = false;
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

	// create database
	if ($db->create($db_config['name']) && $db->select($db_config['name']) && $db->multi_query($sql)) {
		$module_manager = ModuleManager::getInstance();
		$module_handler = ModuleHandler::getInstance();
		$admin_manager = AdministratorManager::getInstance();

		// populate tables
		$raw_data = file_get_contents($xml_file);
		$data = new XMLParser($raw_data, $xml_file);
		$data->parse();

		// go over XML file and insert data
		foreach ($data->document->tagChildren as $item) 
			switch ($item->tagName) {
				case 'module':
					// insert data
					$module_manager->insertData(array(
									'name'		=> $item->tagAttrs['name'],
									'order'		=> $item->tagAttrs['order'],
									'preload'	=> $item->tagAttrs['preload'] == 'yes' ? 1 : 0,
									'active'	=> 1
								));

					// initialize module
					$module = $module_handler->_loadModule($item->tagAttrs['name']);

					if (!is_null($module)) 
						$module->onInit();

					break;

				case 'user':
					$admin_manager->insertData(array(
									'username'	=> $item->tagAttrs['username'],
									'password'	=> $item->tagAttrs['password'],
									'fullname'	=> $item->tagAttrs['fullname'],
									'level'		=> $item->tagAttrs['level']
								));
					break;
			}

		// set result
		$result = true;
	}

	return $result;
}

?>
