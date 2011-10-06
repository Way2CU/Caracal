<?php

/**
 * Modular Web Engine
 * Copyright (c) 2011. by Mladen Mijatov
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

define('_DOMAIN', 'RCF_WebEngine');
define('_BASEPATH', dirname(__FILE__));

require_once('units/doctypes.php');
require_once('units/config.php');
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

$time_start = explode(" ", microtime());
$time_start = $time_start[0] + $time_start[1];

// start session
session_start();

// create main handlers
$ModuleHandler = ModuleHandler::getInstance();
$LanguageHandler = MainLanguageHandler::getInstance();
$SectionHandler = MainSectionHandler::getInstance();

// load primary variables
$default_language = $LanguageHandler->getDefaultLanguage();
if (!isset($_SESSION['level']) || empty($_SESSION['level'])) $_SESSION['level'] = 0;
if (!isset($_SESSION['logged']) || empty($_SESSION['logged'])) $_SESSION['logged'] = false;
if (!isset($_SESSION['language']) || empty($_SESSION['language'])) $_SESSION['language'] = $default_language;
$section = (!isset($_REQUEST['section']) || empty($_REQUEST['section'])) ? 'home' : fix_chars($_REQUEST['section']);
$action = (!isset($_REQUEST['action']) || empty($_REQUEST['action'])) ? '_default' : fix_chars($_REQUEST['action']);

// if language change is specified
if (isset($_REQUEST['language']))
	if (array_key_exists($_REQUEST['language'], $LanguageHandler->getLanguages()))
		$_SESSION['language'] = fix_chars($_REQUEST['language']);

$language = $_SESSION['language'];

// start database engine
if ($db_use) {
	$db = new rcfDB_mysql();
	$db_active = $db->quick_connect($db_user, $db_pass, $db_name, $db_host);
}

// load all the modules and start parsing the page
$ModuleHandler->loadModules();

// transfer display control
$SectionHandler->transferControl($section, $action, $language);

// print out copyright and timing
$time_end = explode(" ", microtime());
$time_end = $time_end[0] + $time_end[1];
$time = round($time_end - $time_start, 3);

if (!defined('_OMIT_STATS')) {
	echo "\n<!-- Modular Web Engine (c) ".date('Y').". by RCF Group, www.rcf-group.com -->";
	echo "\n<!-- Page generated in $time second(s) -->";
}

?>
