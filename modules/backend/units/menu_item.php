<?php


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
	 * Template parser used for rendering tags.
	 * @var object
	 */
	private $template;

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
			$backend = backend::get_instance();
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
	 * Render menu item using specified template.
	 *
	 * @param object $template
	 */
	function drawItem($template) {
		if (!$this->isDrawable())
			return;

		// prepare params
		$params = array(
				'action'       => $this->action,
				'title'        => $this->title,
				'icon'         => $this->icon,
				'has_children' => count($this->children) > 0
			);

		// store template handler so children can render with same object
		$this->template = $template;

		// render tag
		$template->register_tag_handler('cms:children', $this, 'render_children');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Render child tags.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function render_children($tag_params, $children) {
		foreach ($this->children as $child)
			$child->drawItem($this->template);
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
		if (!$this->isDrawable())
			return;
		echo '<hr>';
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
		$result = URL::make_query(
				'backend_module',
				'transfer_control',
				array('backend_action', $action),
				array('module', $module)
			);
	} else {
		$result = URL::make_query(
				'backend_module',
				'transfer_control',
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

	return "javascript: Caracal.window_system.open_window('{$id}', {$width}, '{$title}', '{$url}', this);";
}

function window_OpenHyperlink($text, $id, $width, $title, $can_close, $can_minimize, $module, $action) {
	$url = URL::make_query(_BACKEND_SECTION_, 'transfer_control', array('backend_action', $action), array('module', $module));

	return URL::make_hyperlink($text, window_Open($id, $width, $title, $can_close, $can_minimize, $url), $text);
}

function window_Close($window) {
	return "javascript: Caracal.window_system.close_window('{$window}');";
}

function window_LoadContent($window, $url) {
	return "javascript: Caracal.window_system.load_window_content('{$window}', '{$url}');";
}

function window_ReloadContent($window) {
	return "javascript: Caracal.window_system.load_window_content('{$window}');";
}

function window_Resize($window, $size) {
	return "javascript: window_Resize('{$window}', {$size});";
}

?>
