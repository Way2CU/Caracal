<?php

/**
 * Main Configuration File
 */

use Core\Cache\Type as CacheType;

// paths
$site_path = 'site/';
$cache_path = 'cache/';

$system_images_path = 'system/images/';
$system_styles_path = 'system/styles/';
$system_template_path = 'system/templates/';

$data_path = $site_path.'data/';
$module_path = $site_path.'modules/';
$template_path = $site_path.'templates/';
$scripts_path = $site_path.'scripts/';
$styles_path = $site_path.'styles/';
$images_path = $site_path.'images/';

// database
$db = null;
$db_use = false;
$db_type = DatabaseType::MYSQL;
$db_config = array(
		'host' => 'localhost',
		'user' => 'root',
		'pass' => '',
		'name' => 'database'
	);

// cache
$cache_method = CacheType::NONE;
$cache_expire_period = 86400;

// head tag
$include_scripts = false;
$optimize_code = false;

// various
$url_rewrite = false;
$url_add_extension = false;
$url_language_optional = true;

// gravatar global variables
$gravatar_url = 'gravatar.com/avatar/{email_hash}?s={size}&amp;d={default}&amp;r={rating}';
$gravatar_rating = 'x';
$gravatar_default = 'mm';

?>
