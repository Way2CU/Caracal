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
	function __construct() {
		$this->file = __FILE__;
		parent::__construct();
	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler;

		// load CSS and JScript
		if ($ModuleHandler->moduleExists('head_tag')) {
			$head_tag = $ModuleHandler->getObjectFromName('head_tag');

			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/language.js'), 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/selector.js'), 'type'=>'text/javascript'));
		}
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
				case 'print':
					$this->printMenus($level, $params);
					break;

				case 'json':
					$this->json_Menu();
					break;

				case 'json_get_text':
					$this->json_GetText();
					break;

				case 'json_get_current_language':
					$this->json_GetCurrentLanguage();
					break;

				default:
					break;
			}
	}

	/**
	 * Prints language menu using OL
	 *
	 * @param integer $level
	 * @param array $global_params
	 */
	function printMenus($level, $tag_params) {
		global $ModuleHandler, $LanguageHandler, $action, $section;

		// check if we were asked to get languages from specific module
		if (isset($tag_params['from_module']) && $ModuleHandler->moduleExists($tag_params['from_module'])) {
			$module = $ModuleHandler->getObjectFromName($tag_params['from_module']);
			$list = $module->language->getLanguages(true);
		} else {
			$list = $LanguageHandler->getLanguages(true);
		}

		$template_file = (isset($tag_params['template'])) ? $tag_params['template'] : 'list_item.xml';
		$template = new TemplateHandler($template_file, $this->path.'templates/');
		$template->setMappedModule($this->name);

		$link_params = array();
		foreach($_GET as $key => $value)
			if ($key != 'language')
				$link_params[$key] = escape_chars($value);

		if (count($list) > 0)
			foreach ($list as $short=>$long) {
				$link_params['language'] = $short;
				$link = url_MakeFromArray($link_params);

				$params = array(
					'short_name'	=> $short,
					'long_name'		=> $long,
					'url' 			=> $link
				);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse($level);
			}
	}

	/**
	 * Print JSON object for usage by the backend API
	 */
	function json_Menu() {
		global $ModuleHandler, $LanguageHandler, $action, $section;

		define('_OMIT_STATS', 1);

		// check if we were asked to get languages from specific module
		if (isset($_REQUEST['from_module']) && $ModuleHandler->moduleExists($_REQUEST['from_module'])) {
			$module = $ModuleHandler->getObjectFromName($_REQUEST['from_module']);

			$rtl = $module->language->getRTL();
			$list = $module->language->getLanguages(true);
			$default = $module->language->getDefaultLanguage();
		} else {
			$rtl = $LanguageHandler->getRTL();
			$list = $LanguageHandler->getLanguages(true);
			$default = $LanguageHandler->getDefaultLanguage();
		}

		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'items'			=> array(),
					'rtl'			=> $rtl
				);

		foreach($list as $short => $long)
			$result['items'][] = array(
									'short'		=> $short,
									'long'		=> $long,
									'default' 	=> $short == $default
								);

		print json_encode($result);
	}

	/**
	 * Get language constant from specified module or from global language file
	 */
	function json_GetText() {
		global $ModuleHandler, $LanguageHandler;

		define('_OMIT_STATS', 1);

		// check if we were asked to get languages from specific module
		if (isset($_REQUEST['from_module']) && $ModuleHandler->moduleExists($_REQUEST['from_module'])) {
			$module = $ModuleHandler->getObjectFromName(escape_chars($_REQUEST['from_module']));
			$text = $module->language->getText(escape_chars($_REQUEST['constant']));
		} else {
			$text = $LanguageHandler->getText(escape_chars($_REQUEST['constant']));
		}

		$result = array(
					'text'	=> $text,
				);

		print json_encode($result);
	}

	/**
	 * Get current language
	 */
	function json_GetCurrentLanguage() {
		global $language;

		define('_OMIT_STATS', 1);
		print json_encode($language);
	}
}
