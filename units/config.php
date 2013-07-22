<?php

/**
 * Main Configuration File
 */

// document standard
define('_STANDARD', 'html5');
define('_TIMEZONE', 'America/New_York');

// define('DEBUG', 1);
// define('SQL_DEBUG', 1);

// paths
$module_path = 'modules/';
$data_path = 'data/';
$template_path = 'site/';
$cache_path = 'cache/';
$system_template_path = 'units/templates/';

// database
$db = null;
$db_use = true;
$db_type = DatabaseType::MYSQL;
$db_config = array(
		'host' => 'localhost',
		'user' => 'root',
		'pass' => 'matrix',
		'name' => 'web_engine'
	);

// cache
$cache_enabled = false;
$cache_expire_period = 86400;
$cache_max_pages = 200;

// head tag
$include_scripts = false;
$optimize_code = false;

// various
$url_rewrite = false;
$url_add_extension = false;
$url_language_optional = true;

?>
