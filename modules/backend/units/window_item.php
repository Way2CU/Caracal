<?php

/**
 * Menu Item Class
 *
 * This class provides easy way to create backend menu items which,
 * upon clicking, open new window (or reload already opened window) and
 * load content from URL specified by action.
 *
 * Copyright © 2015 Way2CU. All Rights Reserved.
 * Author: Mladen Mijatov
 */

class WindowItem implements Item {
	protected $name;
	protected $title;
	protected $icon;
	protected $window;
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
	 * @param string $window
	 * @param Modules\Backend\Action $action
	 */
	public function __construct($title, $icon, $window, $action=null) {
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
	 * Sets target window name.
	 *
	 * @param string $window
	 */
	public function setWindowName($window) {
		$this->window = $window;
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

		// prepare action
		$action = 'javascript: window_system.openWindow(';
		$action .= "'{$this->window}',";
		$action .= $this->window_width.',';
		$action .= '\''.$this->window_title.'\',';
		$action .= ($this->window_can_close ? 'true' : 'false').',';
		$action .= '\''.$this->action->getUrl().'\', this);';

		// prepare parameters
		$params = array(
				'title'		=> $this->title,
				'icon'		=> $this->icon,
				'classes'	=> $class_string,
				'action'	=> $action
			);
	}
}

?>
