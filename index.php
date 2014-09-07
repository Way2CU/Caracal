<?php

/**
 * Caracal Framework
 * Copyright (c) 2014. by Way2CU
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

require_once('units/database/common.php');
require_once('units/database/item_manager.php');
require_once('units/system_managers.php');
require_once('units/event_handler.php');
require_once('units/module.php');
require_once('units/module_handler.php');
require_once('units/url.php');
require_once('units/common.php');
require_once('units/language.php');
require_once('units/template.php');
require_once('units/section.php');
require_once('units/xml_parser.php');
require_once('units/markdown.php');
require_once('units/code_optimizer.php');
require_once('units/cache/cache.php');
require_once('units/cache/manager.php');
require_once('units/page_switch.php');
require_once('units/session.php');
require_once('units/config.php');

// include site config
if (file_exists($site_path.'config.php'))
	require_once($site_path.'config.php');

// include remaining units
require_once('units/doctypes.php');
require_once('units/gravatar.php');

// make namespaces more friendly
use Core\Cache\Manager as Cache;

// set timezone as specificed in the config
date_default_timezone_set(_TIMEZONE);

// change error reporting level
if (!defined('DEBUG'))
	error_reporting(E_ERROR | E_WARNING | E_USER_ERROR | E_USER_WARNING); else
	error_reporting(E_ALL | E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE);

// define constants
define('_DOMAIN', $_SERVER['SERVER_NAME']);
define('_SECURE', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off');
define('_BASEPATH', dirname(__FILE__));
define('_BASEURL', url_GetBaseURL());
define('_LIBPATH', _BASEPATH.'/libraries/');
define('_DESKTOP_VERSION', get_desktop_version());
define('_MOBILE_VERSION', !_DESKTOP_VERSION);
define('_AJAX_REQUEST', 
			!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
			strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
		);
define('_BROWSER_OK', is_browser_ok());
define('_VERSION', 0.1);

// start measuring time
$time_start = explode(" ", microtime());
$time_start = $time_start[0] + $time_start[1];

// start session
Session::start();

// unpack parameters if needed
if ($url_rewrite)
	url_UnpackValues();

// set default values for variables
if (!isset($_SESSION['level']) || empty($_SESSION['level'])) $_SESSION['level'] = 0;
if (!isset($_SESSION['logged']) || empty($_SESSION['logged'])) $_SESSION['logged'] = false;
$section = (!isset($_REQUEST['section']) || empty($_REQUEST['section'])) ? 'home' : fix_chars($_REQUEST['section']);
$action = (!isset($_REQUEST['action']) || empty($_REQUEST['action'])) ? '_default' : fix_chars($_REQUEST['action']);

// get main language handler instance
$language_handler = MainLanguageHandler::getInstance();

if (!isset($_REQUEST['language'])) {
	// no language change was specified, check session
	if (!isset($_SESSION['language']) || empty($_SESSION['language'])) 
		$_SESSION['language'] = $language_handler->getDefaultLanguage();

} else {
	// language change was specified, make sure it's valid
	if (array_key_exists($_REQUEST['language'], $language_handler->getLanguages())) {
		$_SESSION['language'] = fix_chars($_REQUEST['language']);

	} else {
		// set language without asking if module is backend
		if (in_array($section, array('backend', 'backend_module')))
			$_SESSION['language'] = fix_chars($_REQUEST['language']); else
			$_SESSION['language'] = $language_handler->getDefaultLanguage();
	}
}

$language = $_SESSION['language'];
$language_rtl = $language_handler->isRTL();

// turn off URL rewrite for backend
if ($section == 'backend' || $section == 'backend_module')
	$url_rewrite = false;

// start database engine
if ($db_use && !database_connect())
	die('There was an error while trying to connect database.');

// transfer display control
$cache = Cache::getInstance();
$module_handler = ModuleHandler::getInstance();

if ($cache->isCached()) {
	// only include specified modules
	$module_handler->loadModules(true);

	// show cached page
	$cache->printCache();

} else {
	// get main section handler so we can transfer control
	$section_handler = SectionHandler::getInstance();

	// load all the modules
	$module_handler->loadModules();

	// show page and cache it along the way
	$cache->startCapture();
	$section_handler->transferControl($section, $action, $language);
	$cache->endCapture();
}

// print out copyright and timing
$time_end = explode(" ", microtime());
$time_end = $time_end[0] + $time_end[1];
$time = round($time_end - $time_start, 3);

if (!defined('_OMIT_STATS') && !_AJAX_REQUEST) {
	echo "\n<!-- Page generated with Caracal in $time second(s) -->";
}

?>
