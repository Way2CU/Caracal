<?php

/**
 * Backend Menu Item
 * @author MeanEYE[rcf]
 */


class backend_MenuItem {
	/**
	 * Menu text
	 * @var string
	 */
	var $title;

	/**
	 * Url to menu icon
	 * @var string
	 */
	var $icon;

	/**
	 * Action URL
	 * @var string
	 */
	var $action;

	/**
	 * Access level
	 */
	var $level;

	/**
	 * Submenu items
	 * @var array
	 */
	var $children = array();

	/**
	 * Constructor
	 *
	 * @param string $title
	 * @param string $icon
	 * @param string $action
	 * @param integer $level
	 * @param string $module
	 * @return backend_MenuItem
	 */
	function __construct($title, $icon, $action, $level=0) {
		$this->title = $title;
		$this->icon = (empty($icon)) ? url_GetFromFilePath(dirname(__FILE__).'/..').'/images/default_itemicon.gif' : $icon;
		$this->action = $action;
		$this->level = $level;
	}

	/**
	 * Adds item to child list
	 *
	 * @param string $name
	 * @param resource $item
	 */
	function addChild($name, $item) {
		if (empty($name))
			$this->children[] = $item; else
			$this->children[$name] = $item;
	}

	/**
	 * Draws item on screen
	 * @param string $module
	 */
	function drawItem($level) {
		$tag_space = str_repeat("\t", $level);

		$icon = "<img src=\"{$this->icon}\" alt=\"{$this->title}\">";
		$link = (!empty($this->action)) ? "<a href=\"javascript:void(0);\" onclick=\"{$this->action}\" title=\"{$this->title}\">{$icon}{$this->title}</a>" : $icon.$this->title;
		$class = (count($this->children) > 0) ? ' class="sub_menu"' : '';

		echo $tag_space."<li{$class}>\n";
		echo $tag_space."\t{$link}\n";

		if (count($this->children) > 0) {
			echo $tag_space."\t<ul>\n";

			foreach ($this->children as $child)
				$child->drawItem($level+2);

			echo $tag_space."\t</ul>\n";
		}

		echo $tag_space."</li>\n";
	}

	/**
	 * Returns if this item is available to draw for current level
	 *
	 * @return boolean;
	 */
	function isDrawable() {
		return $_SESSION['level'] >= $this->level;
	}
}

/**
 * Widely used function for generating backend window
 *
 * @param string $id newly created window id (unique)
 * @param integer $width
 * @param integer $height
 * @param string $title
 * @param boolean $can_close
 * @param boolean $can_minimize
 * @param string $module
 * @param string $action
 * @return string
 */
function backend_Window($id, $width, $title, $can_close, $can_minimize, $module, $action) {

	$url = url_Make('transfer_control', _BACKEND_SECTION_, array('backend_action', $action), array('module', $module));
	$close = $can_close ? 'true' : 'false';
	$minimize = $can_close ? 'true' : 'false';

	$result = "javascript: window_Open('{$id}', {$width}, '{$title}', {$close}, {$minimize}, '{$url}');";

	return $result;
}

/**
 * Widely used function for generating backend windo
 *
 * @param string $text
 * @param string $id newly created window id (unique)
 * @param integer $width
 * @param integer $height
 * @param string $title
 * @param boolean $can_close
 * @param boolean $can_minimize
 * @param string $module
 * @param string $action
 * @return string
 */
function backend_WindowHyperlink($text, $id, $width, $title, $can_close, $can_minimize, $module, $action) {
	return url_MakeHyperlink($text, backend_Window($id, $width, $title, $can_close, $can_minimize, $module, $action), $text);
}


/**
 * Fully formated URL for AJAX content loading
 *
 * @param string $window
 * @param string $module
 * @param string $action
 */
function backend_ContentUrl($window, $module, $action) {
	$url = url_Make('transfer_control', _BACKEND_SECTION_, array('backend_action', $action), array('module', $module));
	return "javascript: window_LoadContent('{$window}', '{$url}');";
}

/**
 * Fully formater URL for AJAX content loading with window resize
 *
 * @param string $window
 * @param string $module
 * @param string $action
 * @param integer $width
 */
function backend_ContentUrlResize($window, $module, $action, $width) {
	$url = url_Make('transfer_control', _BACKEND_SECTION_, array('backend_action', $action), array('module', $module));
	return "javascript: window_LoadContent('{$window}', '{$url}'); window_Resize('{$window}', {$width});";
}

/**
 * Returns fully formated URL for backend window content
 *
 * @param string $text
 * @param string $window
 * @param string $module
 * @param string $action
 * @return string
 */
function backend_ContentHyperlink($text, $window, $module, $action) {
	return url_MakeHyperlink($text, backend_ContentUrl($window, $module, $action), $text);
}

/**
 * Returns fully formated URL for backend window content with resize
 *
 * @param string $text
 * @param string $window
 * @param string $module
 * @param string $action
 * @return string
 */
function backend_ContentHyperlinkResize($text, $window, $module, $action, $width) {
	return url_MakeHyperlink($text, backend_ContentUrlResize($window, $module, $action, $width), $text);
}

/**
 * Create hyperlink with action to close specified window
 *
 * @param string $text
 * @param string $window
 */
function window_CloseHyperlink($text, $window) {
	return url_MakeHyperlink($text, window_Close($window), $text);
}

/**
 * Create backend form action URL for given action
 *
 * @param string $action
 * @return string
 */
function backend_UrlMake($module, $action) {
	return url_Make(
				'transfer_control',
				'backend_module',
				array('backend_action', $action),
				array('module', $module)
			);
}


/****
 ****/

function window_Open($id, $width, $title, $can_close, $can_minimize, $url) {
	$can_close = $can_close ? 'true' : 'false';
	$can_minimize = $can_minimize ? 'true' : 'false';

	return "javascript: window_Open('{$id}', {$width}, '{$title}', {$can_close}, {$can_minimize}, '{$url}');";
}

function window_Close($window) {
	return "javascript: window_Close('{$window}');";
}

function window_LoadContent($window, $url) {
	return "javascript: window_LoadContent('{$window}', '{$url}');";
}

function window_ReloadContent($window) {
	return "javascript: window_ReloadContent('{$window}');";
}

function window_Resize($window, $size) {
	return "javascript: window_Resize('{$window}', {$size});";
}

?>
