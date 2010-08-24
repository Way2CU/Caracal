<?php

/**
 * MAIN CONFIG FILE
 *
 * @version 1.0
 * @author MeanEYE
 * @copyright RCF Group, 2009.
 */

if (!defined('_DOMAIN') || _DOMAIN !== 'RCF_WebEngine') die ('Direct access to this file is not allowed!');

$module_path = 'modules/';
$data_path = 'data/';
$template_path = 'site/';
$system_template_path = 'units/templates/';

// database
$db = null;
$db_use = true;
$db_active = false;
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'matrix';
$db_name = 'web_engine';

// various
$default_language = 'en';
$per_page = 10;
$max_pages = 15;
$max_file_size = 1000000;
$hint_timeout = 10000;

// fingerprint
$fp_size = 4;
$fp_arcnum = 15;
$fp_fontsize = 25;
$fp_chartype = 'numbers';
$fp_fontdir = "./";
$fp_accepted_hosts = array($_SERVER['HTTP_HOST'], 'http://'.$_SERVER['HTTP_HOST']);
$fp_err_image = '../images/captcha_err.png';

?>
