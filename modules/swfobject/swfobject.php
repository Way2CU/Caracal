<?php

/**
 * BLANK MODULE
 *
 * @author MeanEYE
 * @copyright RCF Group,2008.
 */

class swfobject extends Module {
	/**
	 * Minimum required flash version
	 * @var string
	 */
	var $flash_version = '9.0.0';

	/**
	 * Constructor
	 *
	 * @return swfobject
	 */
	function swfobject() {
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
			default:
				break;
		}

		// global control actions
		switch ($params['backend_action']) {
			default:
				break;
		}
	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler;

		// load module style and scripts
		if ($ModuleHandler->moduleExists('head_tag')) {
			$head_tag = $ModuleHandler->getObjectFromName('head_tag');
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/swfobject.js'), 'type'=>'text/javascript'));
		}
	}
	
	/**
	 * Embed flash player with specified parameters
	 * 
	 * @param string $url
	 * @param string $target_id
	 * @param integer $width
	 * @param integer $height
	 * @param array $flash_vars
	 * @param array $params
	 */
	function embedSWF($level, $url, $target_id, $width, $height, $flash_vars=array(), $params=array()) {
		$template = new TemplateHandler('embed.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'url'		=> "'{$url}'",
					'id'		=> "'{$target_id}'",
					'width'		=> "'{$width}'",
					'height'	=> "'{$height}'",
					'version'	=> "'{$this->flash_version}'"
				);
				
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);		
	}
}
