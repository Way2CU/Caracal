<?php

/**
 * BACKEND MODULE
 * Main backend framework class
 *
 * @author MeanEYE[rcf]
 */

define('_BACKEND_SECTION_', 'backend_module');
require_once('units/menu_item.php');

class backend extends Module {
	/**
	 * Menu list
	 * @var array
	 */
	var $menus = array();

	/**
	 * Constructor
	 *
	 * @return backend
	 */
	function backend() {
		$this->file = __FILE__;
		parent::Module();
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param string $action
	 * @param integer $level
	 */
	function transferControl($level, $params = array(), $children=array()) {
		global $ModuleHandler;

		switch ($params['action']) {
			case 'draw_menu':
				$this->drawCompleteMenu($level);
				break;

			case 'transfer_control':
				// fix input parameters
				foreach($_REQUEST as $key => $value)
					$_REQUEST[$key] = $this->utf8_urldecode($_REQUEST[$key]);

				// transfer control
				$action = fix_chars($_REQUEST['backend_action']);
				$module_name = fix_chars($_REQUEST['module']);
				$params['backend_action'] = $action;

				if ($ModuleHandler->moduleExists($module_name)) {
					$module = $ModuleHandler->getObjectFromName($module_name);
					$module->transferControl($level, $params, $children);
				}
				break;
		}
	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler, $LanguageHandler, $section;

		// load CSS and JScript
		if ($ModuleHandler->moduleExists('head_tag') && $section == $this->name) {
			$head_tag = $ModuleHandler->getObjectFromName('head_tag');

			// load style based on current language
			$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/backend.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			if ($LanguageHandler->isRTL())
				$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/backend_rtl.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));

			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/xmlhttp.js'), 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/page.js'), 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/window.js'), 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/tree_text.js'), 'type'=>'text/javascript'));
		}
	}

	/**
	 * Draws all menus for current level
	 *
	 * @param integer $level
	 */
	function drawCompleteMenu($level) {
		$tag_space = str_repeat("\t", $level);

		echo "$tag_space<ul id=\"navigation\">\n";

		foreach ($this->menus as $item)
			$item->drawItem($level+1);

		echo "$tag_space</ul>\n";
	}

	/**
	 * Adds menu to draw list
	 *
	 * @param string $module
	 * @param resource $menu
	 */
	function addMenu($module, $menu) {
		$this->menus[$module] = $menu;
	}

	/**
	 * This function decodes characters encoded by JavaScript
	 *
	 * @param string $str
	 * @return string
	 */
	function utf8_urldecode($str) {
		$str = preg_replace("/%u([0-9a-f]{3,4})/i","&#x\\1;", urldecode($str));
		return html_entity_decode($str, null, 'UTF-8');;
	}
}

?>
