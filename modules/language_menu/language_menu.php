<?php

/**
 * LANGUAGE MODULE
 *
 * @author MeanEYE
 * @copyright RCF Group,2008.
 */

class language_menu extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// load CSS and JScript
		if (class_exists('head_tag')) {
			$head_tag = head_tag::getInstance();

			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/language.js'), 'type'=>'text/javascript'));

			if ($section == 'backend')
				$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/selector.js'), 'type'=>'text/javascript'));
		}
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'print':
					$this->printMenus($params);
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
	 * @param array $global_params
	 */
	private function printMenus($tag_params) {
		global $action, $section;

		// check if we were asked to get languages from specific module
		if (isset($tag_params['from_module']) && class_exists($tag_params['from_module'])) {
			$module = call_user_func(array($tag_params['from_module'], 'getInstance'));
			$list = $module->language->getLanguages(true);
		} else {
			$list = MainLanguageHandler::getInstance()->getLanguages(true);
		}

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('list_item.xml', $this->path.'templates/');
		}
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
				$template->parse( );
			}
	}

	/**
	 * Print JSON object for usage by the backend API
	 */
	private function json_Menu() {
		global $action, $section;

		define('_OMIT_STATS', 1);

		// check if we were asked to get languages from specific module
		if (isset($_REQUEST['from_module']) && class_exists($_REQUEST['from_module'])) {
			$module = call_user_func(array(escape_chars($_REQUEST['from_module']), 'getInstance'));

			$rtl = $module->language->getRTL();
			$list = $module->language->getLanguages(true);
			$default = $module->language->getDefaultLanguage();
		} else {
			$language_handler = MainLanguageHandler::getInstance();

			$rtl = $language_handler->getRTL();
			$list = $language_handler->getLanguages(true);
			$default = $language_handler->getDefaultLanguage();
		}

		$result = array(
					'error'				=> false,
					'error_message'		=> '',
					'items'				=> array(),
					'rtl'				=> $rtl,
					'default_language'	=> $default
				);

		foreach($list as $short => $long)
			$result['items'][] = array(
									'short'			=> $short,
									'long'			=> $long,
								);

		print json_encode($result);
	}

	/**
	 * Get language constant from specified module or from global language file
	 */
	private function json_GetText() {
		define('_OMIT_STATS', 1);

		// check if we were asked to get languages from specific module
		if (isset($_REQUEST['from_module']) && class_exists($_REQUEST['from_module'])) {
			$module = call_user_func(array(escape_chars($_REQUEST['from_module']), 'getInstance'));
			$text = $module->language->getText(escape_chars($_REQUEST['constant']));
		} else {
			$text = MainLanguageHandler::getInstance()->getText(escape_chars($_REQUEST['constant']));
		}

		$result = array(
					'text'	=> $text,
				);

		print json_encode($result);
	}

	/**
	 * Get current language
	 */
	private function json_GetCurrentLanguage() {
		global $language;

		define('_OMIT_STATS', 1);
		print json_encode($language);
	}
}
