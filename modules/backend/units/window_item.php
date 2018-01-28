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
namespace Modules\Backend\Menu;


class WindowItem implements Item {
	protected $name;
	protected $title;
	protected $icon;
	protected $window;
	protected $separator;
	protected $actions = array();
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
		$this->window = $window;
	}

	/**
	 * Add specified action to chain of execution.
	 *
	 * @param object $action
	 */
	public function add_action($action) {
		if (is_subclass_of($action, WindowAction))
			$action->set_menu_item($this);

		$this->actions[] = $action;
	}

	/**
	 * Set menu item name. This also registers menu
	 * with global backend menu item list and makes menu
	 * accessible by other parts of the system.
	 *
	 * @param string $name
	 */
	public function set_name($name) {
		// store name locally
		$this->name = $name;

		// register with backend
		$backend = backend::get_instance();
		$backend->registerNamedItem($name, $this);
	}

	/**
	 * Sets target window name.
	 *
	 * @param string $window
	 */
	public function set_window($window) {
		$this->window = $window;
	}

	/**
	 * Draw menu item.
	 */
	public function draw() {
		// load template
		if (is_null(self::$template)) {
			$backend = backend::get_instance();
			self::$template = new TemplateHandler('menu_item.xml', $backend->path);
		}

		// prepare action
		$result = 'javascript:';
		foreach ($this->actions as $action)
			$result .= $action->get_url().';';

		// prepare parameters
		$params = array(
				'title'		=> $this->title,
				'icon'		=> $this->icon,
				'classes'	=> '',
				'action'	=> $action
			);
	}
}

?>
