<?php

/**
 * Menu Item Class
 *
 * This class provides easy constructing and defining menu item
 * elements for backend.
 *
 * Copyright © 2015 Mladen Mijatov. All Rights Reserved.
 */


class MenuItem {
	protected $name;
	protected $title;
	protected $icon;
	protected $action;
	protected $separator;
	protected $children = array();
	protected $module_name;

	protected static $template = null;

	/**
	 * Create new backend menu item with specified title, icon and action.
	 * Please note icon is only a file name without path. Path will be generated
	 * based on backend action parameter.
	 *
	 * @param string $title
	 * @param string $icon
	 * @param Modules\Backend\Action $action
	 */
	public function __construct($title, $icon, $action=null) {
		$this->title = $title;
		$this->icon = $icon;
		$this->action = $action;
	}

	/**
	 * Add child menu item.
	 *
	 * @param MenuItem $child
	 */
	public function addChild($child) {
		$this->children[] = $child;
	}

	/**
	 * Set if menu item has separator following.
	 *
	 * @param boolean $separator
	 */
	public function setSeparator($separator) {
		$this->separator = $separator;
	}

	/**
	 * Set menu item name. This also registers menu
	 * with global backend menu item list and makes menu
	 * accessible by other parts of the system.
	 *
	 * @param string $name
	 */
	public function setName($name) {
		// store name locally
		$this->name = $name;

		// register with backend
		$backend = backend::getInstance();
		$backend->registerNamedItem($name, $this);
	}

	/**
	 * Set module name to be used if Action is not set. This
	 * name is only used for icon path generation.
	 *
	 * @param string $name
	 */
	public function setModuleName($name) {
		$this->module_name = $name;
	}

	/**
	 * Draw menu item.
	 */
	public function draw() {
		if (!$this->action->hasPermission())
			return;

		// prepare classes required for this menu item
		$classes = array();

		if (count($this->children) > 0)
			$classes[] = 'submenu';

		if ($this->separator)
			$classes[] = 'separator';

		$class_string = implode(' ', $classes);

		// load template
		if (is_null(self::$template)) {
			$backend = backend::getInstance();
			self::$template = new TemplateHandler('menu_item.xml', $backend->path);
		}

		// prepare parameters
		$params = array(
				'title'		=> $this->title,
				'icon'		=> $this->icon,
				'classes'	=> $class_string,
				'action'	=> 'javascript: void(0);'
			);
	}
}


class backend_MenuItem {
	/**
	 * Menu text
	 * @var string
	 */
	private $title;

	/**
	 * Url to menu icon
	 * @var string
	 */
	private $icon;

	/**
	 * Action URL
	 * @var string
	 */
	private $action;

	/**
	 * Access level
	 */
	private $level;

	/**
	 * Submenu items
	 * @var array
	 */
	private $children = array();

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
		$this->icon = $icon;
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
		if (is_null($name) || empty($name))
			$this->children[] = $item; else
			$this->children[$name] = $item;

		// register named item in backend
		if (!is_null($name)) {
			$backend = backend::getInstance();
			$backend->registerNamedItem($name, $item);
		}
	}

	/**
	 * Insert item to child list on specified location
	 *
	 * @param resource $item
	 * @param integer $position
	 */
	function insertChild($item, $position=0) {
		array_splice($this->children, $position, 0, array($item));
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
	 */
	function drawItem() {
		if (!$this->isDrawable()) return;

		$icon = '<span style="background-image: url('.$this->icon.')"></span>';

		if (!empty($this->action))
			$link =  "<a onclick=\"{$this->action}\">{$icon}{$this->title}</a>"; else
			$link =  "<a>{$icon}{$this->title}</a>";

		$class = (count($this->children) > 0) ? ' class="submenu"' : '';

		echo "<li{$class}>{$link}";

		if (count($this->children) > 0) {
			echo '<ul>';

			foreach ($this->children as $child)
				$child->drawItem();

			echo '</ul>';
		}

		echo '</li>';
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
	function drawItem() {
		if (!$this->isDrawable()) return;
		echo '<li class="separator"></li>';
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
function backend_UrlMake($module, $action, $sub_action=null) {
	if (is_null($sub_action)) {
		$result = url_Make(
				'transfer_control',
				'backend_module',
				array('backend_action', $action),
				array('module', $module)
			);
	} else {
		$result = url_Make(
				'transfer_control',
				'backend_module',
				array('backend_action', $action),
				array('sub_action', $sub_action),
				array('module', $module)
			);
	}

	return $result;
}


function window_Open($id, $width, $title, $can_close, $can_minimize, $url) {
	$can_close = $can_close ? 'true' : 'false';
	$can_minimize = $can_minimize ? 'true' : 'false';

	return "javascript: window_system.openWindow('{$id}', {$width}, '{$title}', {$can_close}, '{$url}', this);";
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
