<?php

/**
 * SECTION HANDLER
 * 
 * @version 1.0
 * @author MeanEYE
 * @copyright RCF Group, 2008.
 */

if (!defined('_DOMAIN') || _DOMAIN !== 'RCF_WebEngine') die ('Direct access to this file is not allowed!');

class SectionHandler {
	var $engine;
	var $active;
	
	/**
	 * Constructor
	 *
	 * @return SectionHandler
	 */
	function SectionHandler($file="") {
		global $site_path;
		
		$this->active = false;
		$file = (empty($file)) ? $site_path.'section.xml' : $file;
		
		if (file_exists($file)) {
			$this->engine = new XMLParser(@file_get_contents($file), $file);
			$this->engine->Parse();
			$this->active = true;
		}
	}
	
	/**
	 * Retrieves file for parsing
	 *
	 * @param string $section
	 * @param string $action
	 * @param string $language
	 * @return string
	 */
	function getFile($section, $action, $language='') {
		global $ModuleHandler, $default_language;
		
		$result = "";
		
		if (!$this->active) return;
		$action = (empty($action)) ? '_default' : $action;
		$language = (empty($language)) ? $default_language : $language;
		
		// cycle through xml file and find the apropriate action
		foreach ($this->engine->document->section as $xml_section)
			if ($xml_section->tagAttrs['name'] == $section) {
				// check if section is mapped to a module
				if (key_exists('module', $xml_section->tagAttrs)) {
					foreach ($xml_section->language as $xml_language) 
						if ($xml_language->tagAttrs['name'] == $language || $xml_language->tagAttrs['name'] == "all") {
							$result = array($xml_language->tagAttrs['file'], $xml_section->tagAttrs['module']);
							break;
						}
				} else {
					// if section is not module mapped continue checking
					foreach ($xml_section->language as $xml_language)
						if ($xml_language->tagAttrs['name'] == $language || $xml_language->tagAttrs['name'] == "all")
							foreach ($xml_language->action as $xml_action)
								if ($xml_action->tagAttrs['name'] == $action) 
									$result = $xml_action->tagAttrs['file'];
				}
			}
		return $result;
	}
	
	/**
	 * Transfers control to preconfigured template
	 *
	 * @param string $section
	 * @param string $action
	 * @param string $language
	 */
	function transferControl($section, $action, $language='') {
		global $TemplateHandler, $ModuleHandler;
		
		if (!$this->active) return;
		
		$file = $this->getFile($section, $action, $language);
		if (is_array($file)) {
			$template = new TemplateHandler($file[0]);
			$template->setMappedModule($file[1]);
		} else {
			$template = new TemplateHandler($file);
		}
		
		// check if login is required 
		if (isset($template->engine->document->tagAttrs['minimum_level']))
			if ($template->engine->document->tagAttrs['minimum_level'] > $_SESSION['level'])
				if ($ModuleHandler->moduleExists('session')) {
					$_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
					$module = $ModuleHandler->getObjectFromName('session');
	
					$file = $module->getSectionFile($module->name, '', $language);
					
					$new = new TemplateHandler(basename($file), dirname($file).'/');
					$new->setMappedModule($module->name);
					$new->parse($level);
					return ;
				}
		
		$template->parse(0);
	}
}
?>
