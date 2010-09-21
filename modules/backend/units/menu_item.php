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
		if (empty($name) || is_null($name))
			$this->children[] = $item; else
			$this->children[$name] = $item;
	}
	
	/**
	 * Add separator to child list
	 * 
	 * @param integer $level
	 */
	function addSeparator($level) {
		$this->children[] = new backend_MenuSeparator($level);
	}

	/**
	 * Draws item on screen
	 * @param string $module
	 */
	function drawItem($level) {
		if (!$this->isDrawable()) return;
		
		$tag_space = str_repeat("\t", $level);

		$icon = "<img src=\"{$this->icon}\" alt=\"{$this->title}\">";
		$link = (!empty($this->action)) ? "<a href=\"javascript:void(0);\" onclick=\"{$this->action}\">{$icon}{$this->title}</a>" : $icon.$this->title;
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
	 * Check if item id available for current level
	 *
	 * @return boolean;
	 */
	function isDrawable() {
		return $_SESSION['level'] >= $this->level;
	}
}

class backend_MenuSeparator{
	/**
	 * Separator level
	 * @var integer
	 */
	var $level;
	
	function __construct($level) {
		$this->level = $level;
	}
	
	/**
	 * Draw separator
	 * 
	 * @param integer $level
	 */
	function drawItem($level) {
		if (!$this->isDrawable()) return;
		
		$tag_space = str_repeat("\t", $level);
		
		echo "{$tag_space}<li class=\"separator\"></li>";
	}
	
	/**
	 * Check if separator is available for current level
	 *
	 * @return boolean;
	 */
	function isDrawable() {
		return $_SESSION['level'] >= $this->level;
	}	
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

	return "javascript: window_system.openWindow('{$id}', {$width}, '{$title}', {$can_close}, '{$url}');";
}

function window_OpenHyperlink($text, $id, $width, $title, $can_close, $can_minimize, $module, $action) {
	$url = url_Make('transfer_control', _BACKEND_SECTION_, array('backend_action', $action), array('module', $module));

	return url_MakeHyperlink($text, window_Open($id, $width, $title, $can_close, $can_minimize, $url), $text);
}

function window_Close($window) {
	return "javascript: window_system.closeWindow('{$window}');";
}

function window_LoadContent($window, $url) {
	return "javascript: window_system.loadWindowContent('{$window}', '{$url}');";
}

function window_ReloadContent($window) {
	return "javascript: window_system.loadWindowContent('{$window}');";
}

function window_Resize($window, $size) {
	return "javascript: window_Resize('{$window}', {$size});";
}

?>
