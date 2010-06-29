<?php

/**
 * BLANK MODULE
 *
 * @author MeanEYE.rcf
 */

class _blank extends Module {

	/**
	 * Constructor
	 */
	function __construct() {
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
		if (isset($params['action']))
			switch ($params['action']) {
				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	function onInit() {

	}

	/**
	 * Event triggered upon module deinitialization
	 */
	function onDisable() {
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
		}
	}
}

/*
class SomeManager extends ItemManager {
	function __construct() {
		parent::ItemManager();
		
		$this->addProperty('id', 'int');
	}
}
*/