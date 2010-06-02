<?php

/**
 * LANGUAGE MODULE
 *
 * @author MeanEYE
 * @copyright RCF Group,2008.
 */

class language_menu extends Module {

	/**
	 * Constructor
	 *
	 * @return journal
	 */
	function language_menu() {
		$this->file = __FILE__;
		parent::Module();
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param string $action
	 * @param integer $level
	 */
	function transferControl($level, $params = array(), $children = array()) {
		// global control actions
		switch ($params['action']) {
			case 'print':
				$this->printMenus($level, $params);
				break;
			default:
				break;
		}
	}

	/**
	 * Event called upon module initialisation
	 */
	function onInit() {

	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler;

		// load module style and scripts
		if ($ModuleHandler->moduleExists('head_tag')) {
			$head_tag = $ModuleHandler->getObjectFromName('head_tag');
			//$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/_blank.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/_blank.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if ($ModuleHandler->moduleExists('backend')) {
			$backend = $ModuleHandler->getObjectFromName('backend');

			//$group = new backend_MenuGroup("Blank", "", $this->name);
			//$group->addItem(new backend_MenuItem("Menu Item", "", "", 1));

			//$backend->addMenu($group);
		}
	}

	/**
	 * Prints language menu using OL
	 *
	 * @param integer $level
	 * @param array $global_params
	 */
	function printMenus($level, $global_params) {
		global $LanguageHandler, $action, $section;
		$list = $LanguageHandler->getLanguages(true);
		$number = 0;

		$template_file = (isset($global_params['template'])) ? $global_params['template'] : 'list_item.xml';
		$template = new TemplateHandler($template_file, $this->path.'templates/');
		$template->setMappedModule($this->name);

		if (count($list) > 0)
		foreach ($list as $short=>$long) {
			$link = url_Make($action, $section, array('language', $short));
			$number++;
			$params = array(
				'number'		=> $number,
				'short_name'	=> $short,
				'long_name'		=> $long,
				'url' 			=> $link
			);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}

	}
}
