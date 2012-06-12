<?php

/**
 * Modular Web Engine
 * Copyright (c) 2012. by Mladen Mijatov
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 * @author Mladen Mijatov <meaneye.rcf@gmail.com>
 */

require_once('units/config.php');
require_once('units/doctypes.php');
require_once('units/rcf_db/rcf_sql_core.php');
require_once('units/rcf_db/rcf_sql_mysql.php');
require_once('units/core/item_manager.php');
require_once('units/system_managers.php');
require_once('units/core/module.php');
require_once('units/module_handler.php');
require_once('units/url.php');
require_once('units/common.php');
require_once('units/language.php');
require_once('units/template.php');
require_once('units/section.php');
require_once('units/xml_parser.php');
require_once('units/markdown.php');
require_once('units/cache/cache.php');
require_once('units/cache/manager.php');
require_once('units/page_switch.php');

define('_BASEPATH', dirname(__FILE__));
define('_BASEURL', url_GetBaseURL());
define('_AJAX_REQUEST', 
			!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
			strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
		);

$time_start = explode(" ", microtime());
$time_start = $time_start[0] + $time_start[1];

// start session
session_start();

// unpack parameters if needed
if ($url_rewrite)
	url_UnpackValues();

// set default values for variables
if (!isset($_SESSION['level']) || empty($_SESSION['level'])) $_SESSION['level'] = 0;
if (!isset($_SESSION['logged']) || empty($_SESSION['logged'])) $_SESSION['logged'] = false;
$section = (!isset($_REQUEST['section']) || empty($_REQUEST['section'])) ? 'home' : fix_chars($_REQUEST['section']);
$action = (!isset($_REQUEST['action']) || empty($_REQUEST['action'])) ? '_default' : fix_chars($_REQUEST['action']);

// only get language is it's not present in session
$language_handler = MainLanguageHandler::getInstance();

if (!isset($_REQUEST['language'])) {
	// no language change was specified, check session
	if (!isset($_SESSION['language']) || empty($_SESSION['language'])) 
		$_SESSION['language'] = $language_handler->getDefaultLanguage();

} else {
	// language change was specified, make sure it's valid
	if (array_key_exists($_REQUEST['language'], $language_handler->getLanguages()))
		$_SESSION['language'] = fix_chars($_REQUEST['language']); else
		$_SESSION['language'] = $language_handler->getDefaultLanguage();
}

$language = $_SESSION['language'];
$language_rtl = $language_handler->isRTL();

// turn off URL rewrite for backend
if ($section == 'backend' || $section == 'backend_module')
	$url_rewrite = false;

// start database engine
if ($db_use) {
	$db = new rcfDB_mysql();
	$db_active = $db->quick_connect($db_user, $db_pass, $db_name, $db_host);

	// set default protocol encoding
	if ($db_active) 
		$db->query('SET NAMES \'utf8\'');
}

// transfer display control
$cache = CacheHandler::getInstance();
$module_handler = ModuleHandler::getInstance();

if ($cache->isCached()) {
	// only include specified modules
	$module_handler->loadModules(true);

	// show cached page
	$cache->printCache();

} else {
	// get main section handler so we can transfer control
	$section_handler = MainSectionHandler::getInstance();

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
	echo "\n<!-- Page generated in $time second(s) -->\n";
	echo "\n<!--\nCopyright (c) ".date('Y').". by:";
	echo "\n    RCF Group - http://rcf-group.com\n    Way2CU - http://way2cu.com\n-->";
}

?>
