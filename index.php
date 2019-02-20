<?php

/**
 * Caracal Framework
 * Copyright (c) 2018. by Way2CU, http://way2cu.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Author: Mladen Mijatov
 */

// define base constants
define('_BASEPATH', dirname(__FILE__));
define('_LIBPATH', _BASEPATH.'/libraries/');
define('_DOMAIN', $_SERVER['SERVER_NAME']);
define('_SECURE', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off');
define('_VERSION', 0.5);

// include main system components
require_once('units/database/common.php');
require_once('units/database/item_manager.php');
require_once('units/system_managers.php');
require_once('units/event_handler.php');
require_once('units/module.php');
require_once('units/module_handler.php');
require_once('units/url.php');
require_once('units/string/distance.php');
require_once('units/common.php');
require_once('units/language.php');
require_once('units/template.php');
require_once('units/section.php');
require_once('units/xml_parser.php');
require_once('units/csp.php');
require_once('units/cors.php');
require_once('units/markdown.php');
require_once('units/code_optimizer.php');
require_once('units/cache/cache.php');
require_once('units/testing/handler.php');
require_once('units/page_switch.php');
require_once('units/session/manager.php');
require_once('units/config.php');

// include site config
if (file_exists($site_path.'config.php'))
	require_once($site_path.'config.php');

// include remaining units
require_once('units/doctypes.php');
require_once('units/gravatar.php');

// start measuring time
if (defined('DEBUG'))
	$time_start = microtime(true);

// make namespaces more friendly
use Core\CORS\Manager as CORS;
use Core\Cache\Manager as Cache;
use Core\Session\Manager as Session;

// set timezone as specificed in the config
date_default_timezone_set(_TIMEZONE);

// change error reporting level
if (!defined('DEBUG'))
	error_reporting(E_ERROR | E_WARNING | E_USER_ERROR | E_USER_WARNING); else
	error_reporting(E_ALL | E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE);

// define constants
define('_BASEURL', URL::get_base());
define('_DESKTOP_VERSION', get_desktop_version());
define('_MOBILE_VERSION', !_DESKTOP_VERSION);
define('_AJAX_REQUEST',
			!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
			strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
		);
define('_BROWSER_OK', is_browser_ok());

// force secure connection if requested
if (should_force_https()) {
	$url = URL::get_base(true);
	header('Location: '.$url, true, 301);
	exit();
}

// prepare for page rendering
Session::start();
$page_match = SectionHandler::prepare();
URL::unpack_values();

// set default values for variables
$section = (!isset($_REQUEST['section']) || empty($_REQUEST['section'])) ? null: fix_chars($_REQUEST['section']);

// initialize language system and apply language
Language::apply_for_session();

// start database engine
if ($db_type !== DatabaseType::NONE && !database_connect())
	die('There was an error while trying to connect database.');

// transfer display control
$cache = Cache::get_instance();
$module_handler = ModuleHandler::get_instance();

if ($cache->is_cached()) {
	// only include specified modules
	$module_handler->load_modules(true);

	// show cached content
	$cache->show_cached_page();

} else {
	// load all the modules
	$module_handler->load_modules(false);

	// handle preflight requests and exit
	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS')
		CORS::handle_preflight_request();

	// include response headers for regular and simple requests
	CORS::add_response_headers();

	// check if module is being requested and is available
	$module_match = ModuleHandler::is_loaded($section);
	$module_match |= $section == 'backend_module';

	// show page and cache it along the way
	if ($page_match || $module_match) {
		$cache->start_capture();
		SectionHandler::transfer_control();
		$cache->end_capture();

	} else {
		// neither page nor module were matched, show error
		SectionHandler::show_error_page(404);
	}
}

// print out copyright and timing
if (defined('DEBUG') && !_AJAX_REQUEST) {
	$time_end = microtime(true);
	$time = round($time_end - $time_start, 3);
	echo "\n<!-- Page generated with Caracal in $time second(s) -->";
}

?>
