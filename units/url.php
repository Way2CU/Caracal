<?php

/**
 * Unpack values from URL rewrite
 */
function url_UnpackValues() {
	global $url_add_extension;

	// resulting array to be implemented
	// in global variables
	$result = array();

	if (isset($_REQUEST['_rewrite'])) {
		$data = $_REQUEST['_rewrite'];

		// remove extensions if needed
		if ($url_add_extension && substr($data, -5) == '.html')
			$data = substr($data, 0, -5);

		// split data
		$raw = explode('/', $data);

		// get language
		if (count($raw) > 0 && strlen($raw[0]) == 2)
			$result['language'] = array_shift($raw);

		// get section
		if (count($raw) > 0)
			$result['section'] = array_shift($raw);

		// get action
		if (count($raw) > 0)
			$result['action'] = array_shift($raw);

		// restore rest of the arguments
		if (count($raw) > 0)
			while (count($raw) > 0) {
				$key = array_shift($raw);
				$value = array_shift($raw);

				$result[$key] = $value;
			}

		// modify global variables
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET':
				$_GET = array_merge($_GET, $result);
				break;

			case 'POST':
				$_POST = array_merge($_POST, $result);
				break;
		}

		$_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
	}
}

/**
 * Make URL from specified parameters. If you need more parameters than default
 * action and section add them as array pair of param and value.
 *
 * @param string $action
 * @param string $section
 * @return string
 * @example url_Make( "action", "section", array("param", "value"), array("param2", "value2") );
 * @author Mladen Mijatov
 */
function url_Make($action='', $section='') {
	$arguments = array();

	// make sure we have all parameters
	if (!empty($section))
		$arguments['section'] = $section; else
		$arguments['section'] = 'home';

	if (!empty($action))
		$arguments['action'] = $action; else
		$arguments['action'] = '_default';

	// get additional arguments
	if (func_num_args() > 2) {
		$tmp = array_slice(func_get_args(), 2);

		foreach ($tmp as $param)
			if (is_array($param))
				$arguments[$param[0]] = $param[1];
	}

	$result = url_MakeFromArray($arguments);

	return $result;
}

/**
 * Forms URL from single specified parameter array. If html_ampersand is false
 * simple `&` will be used instead of `&amp;`. In most cases later is more suitable
 * for use in HTML code.
 *
 * @param array $params
 * @param string $html_ampersand.
 * @return string
 */
function url_MakeFromArray($params, $html_ampersand=true) {
	global $url_rewrite, $url_add_extension, $url_language_optional, $language, $section, $action;

	$arguments = $params;
	$glue = $html_ampersand ? '&amp;' : '&';

	// unset parameters we need to control order of
	$section_argument = $section;
	if (array_key_exists('section', $arguments)) {
		$section_argument = $arguments['section'];
		unset($arguments['section']);
	}

	$action_argument = $action;
	if (array_key_exists('action', $arguments)) {
		$action_argument = $arguments['action'];
		unset($arguments['action']);
	}

	if ($url_rewrite) {
		// form URL with rewrite engine on mind
		$result = '';
		$param_count = 0;
		$include_language = false;
		$include_section = false;
		$include_action = false;

		// should we include language in URL
		$language_argument = $language;
		if (array_key_exists('language', $arguments)) {
			$language_argument = $arguments['language'];
			unset($arguments['language']);

			$include_language = true;
		}

		// should we include section in URL
		if ($section_argument != 'home') {
			$include_section = true;

			if (!$url_language_optional)
				$include_language = true;
		}

		// should we include action in URL
		if ($action_argument != '_default' || count($arguments) > 0) {
			$include_action = true;
			$include_section = true;

			if (!$url_language_optional)
				$include_language = true;
		}

		if ($include_section || $include_language) {
			// form URL
			$result = '';

			// add language
			if ($include_language)
				$result .= '/'.$language_argument;

			// add section
			if ($include_section)
				$result .= '/'.$section_argument;

			// add action
			if ($include_action)
				$result .= '/'.$action_argument;

			if (count($arguments) > 0)
				foreach ($arguments as $key => $value)
					$result .= '/'.$key.'/'.$value;

		} else {
			// rare cases where we only need home page
			$result = '/';

			if (!$url_language_optional)
				$result .= $language_argument;
		}

		// add relative path and domain
		$result = _BASEURL.$result;

		// add extension in the end
		if ($url_add_extension && $include_section)
			$result .= '.html';

	} else {
		// form normal URL
		$base = _BASEURL;
		$base .= '/'.basename($_SERVER['PHP_SELF']);

		// form the rest of the URL
		$result = $base;

		if ($section_argument != 'home')
			$result .= '?section='.urlencode($section_argument);

		if ($action_argument != '_default')
			$result .= $glue.'action='.urlencode($action_argument);

		if (count($arguments) > 0)
			foreach ($arguments as $key => $value)
				$result .= $glue.$key.'='.urlencode($value);
	}

	return $result;
}

/**
 * Creates trailing url
 *
 * @param array $args
 * @return string
 * @author Mladen Mijatov
 */
function url_Form($args, $start_separator=true) {
	$res = "";

	for ($i=0; $i<count($args); $i++) {
		$arg = $args[$i];
		$res .= (empty($res) && !$start_separator ? '' : '&').'&'.$arg[0].'='.urlencode($arg[1]);
	}
	return $res;
}

/**
 * Make HTML tag A with specified preferences.
 *
 * @param string $content Content of hyperlink
 * @param string $link Action for hyperlink. JavaScript is allowed
 * @param string $title Title for specified hyperlink (tooltip). (not required)
 * @param string $class Alternative class. (not required)
 * @param string $target Window name where link will be opened. Some specific ones are "_blank", "_top"
 * @return string
 * @example url_MakeHyperlink( "click here", url_Make("action", "section") );
 * @author Mladen Mijatov
 */
function url_MakeHyperlink($content, $link, $title='', $class='', $target='') {
	$on_click = '';
	if (!empty($title)) $title = ' title="'.$title.'"';
	if (!empty($class)) $class = ' class="'.$class.'"';
	if (!empty($target)) $target = ' target="'.$target.'"';

	// move javascript URL's to onclick handler
	if (substr($link, 0, 11) == 'javascript:') {
		$on_click = ' onclick="'.$link.'"';
		$link = '';
		$href = '';
	} else {
		$href = ' href="'.$link.'"';
	}

	$res = '<a '.$href.$on_click.$target.$class.$title.'>'.$content.'</a>';
	return $res;
}

/**
 * Schedules page refrest to specified URL witin specified time. If URL is ommited
 * refresh of current page occurs. Default time is 2 seconds.
 *
 * @param string $url
 * @param integer $seconds
 * @author Mladen Mijatov
 */
function url_SetRefresh($url='', $seconds=2) {
	$url = $url === '' ? $_SERVER['REQUEST_URI'] : $url;
	print '<script type="text/javascript">setTimeout(function() { window.location = \''.$url.'\'; }, '.($seconds * 1000).')</script>';
}

/**
 * Function to form URL from file path
 *
 * @param string $path
 * @return string
 */
function url_GetFromFilePath($path) {
	$base_url = _BASEURL;

	$path = str_replace('\\', '/', $path);
	$result = $base_url.substr($path, strlen(_BASEPATH));
	return $result;
}

/**
 * Get local file path from URL
 *
 * @param string $url
 * @return string
 */
function path_GetFromURL($url, $base=_BASEURL) {
	return _BASEPATH.substr($url, strlen($base));
}

/**
 * Form base URL
 *
 * @return string
 */
function url_GetBaseURL() {
	$base = (_SECURE ? 'https://' : 'http://')._DOMAIN;

	$port = $_SERVER['SERVER_PORT'];
	if ($port != 80 && $port != 443)
		$base .= ':'.$port;

	$result = dirname($base.$_SERVER['PHP_SELF']);
	$result = preg_replace("/\/$/i", "", $result);

	return $result;
}

?>
