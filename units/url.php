<?php

/**
 * Make URL from specified parameters. If you need more parameters than default
 * action and section add them as array pair of param and value.
 *
 * @param string $action
 * @param string $section
 * @return string
 * @example url_Make( "action", "section", array("param", "value"), array("param2", "value2") );
 * @author MeanEYE
 */
function url_Make($action, $section) {
	$result = $_SERVER['PHP_SELF'];

	if (!empty($section) && $section != 'home')
		$result .= '?section='.urlencode($section);
	
	if (!empty($action) &&  $action != '_default') 
		$result .= '&amp;action='.urlencode($action);

	if (func_num_args() > 2)
		for ($i=2; $i<func_num_args(); $i++) {
			$arg = func_get_arg($i);
			$result .= '&amp;'.$arg[0].'='.urlencode($arg[1]);
		}

	return $result;
}

/**
 * Forms URL from single parameter
 *
 * @param array $params
 * @return string
 * @author MeanEYE
 */
function url_MakeFromArray($params) {
	$temp = array();

	foreach($params as $key => $value)
		$temp[] = "{$key}={$value}";

	return $_SERVER['PHP_SELF']."?".join('&amp;', $temp);
}

/**
 * Creates trailing url
 *
 * @param array $args
 * @return string
 * @author MeanEYE
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
 * @author MeanEYE
 */
function url_MakeHyperlink($content, $link, $title='', $class='', $target='') {
	if (!empty($title)) $title = ' title="'.$title.'"';
	if (!empty($class)) $class = ' class="'.$class.'"';
	if (!empty($target)) $target = ' target="'.$target.'"';

	$res = '<a href="'.$link.'"'.$target.$class.$title.'>'.$content.'</a>';
	return $res;
}

/**
 * Schedules page refrest to specified URL witin specified time. If URL is ommited
 * refresh of current page occurs. Default time is 2 seconds.
 *
 * @param string $url
 * @param integer $seconds
 * @author MeanEYE
 */
function url_SetRefresh($url='', $seconds=2) {
        if ($url !== '')
                $s_url = $url; else
                $s_url = $_SERVER['REQUEST_URI'];
        echo "<meta http-equiv=\"refresh\" content=\"$seconds;url=$s_url\">";
}

/**
 * Function to form URL from file path
 *
 * @param string $path
 * @return string
 */
function url_GetFromFilePath($path) {
	$base_url = dirname('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']);
	$base_url = preg_replace("/\/$/i", "", $base_url);

	$path = str_replace('\\', '/', $path);
	$result = $base_url.substr($path, strlen(_BASEPATH));
	return $result;
}
?>
