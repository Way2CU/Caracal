<?php

/**
 * Page Information
 *
 * @author MeanEYE.rcf
 */

class page_info extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section, $db_use;

		parent::__construct(__FILE__);

		// load module style and scripts
		if (class_exists('head_tag')) {
			$head_tag = head_tag::getInstance();

			// content meta tags
			$language_list = MainLanguageHandler::getInstance()->getLanguages(false);
			$head_tag->addTag('meta',
						array(
							'http-equiv'	=> 'Content-Type',
							'content'		=> 'text/html; charset=UTF-8'
						));
			$head_tag->addTag('meta',
						array(
							'http-equiv'	=> 'Content-Language',
							'content'		=> join(', ', $language_list)
						));

			// robot tags
			$head_tag->addTag('meta', array('name' => 'robots', 'content' => 'index, follow'));
			$head_tag->addTag('meta', array('name' => 'googlebot', 'content' => 'index, follow'));
			$head_tag->addTag('meta', array('name' => 'rating', 'content' => 'general'));

			if ($section != 'backend' && $section != 'backend_module' && $db_use) {
				// google analytics
				if (!empty($this->settings['analytics']))
					$head_tag->addGoogleAnalytics($this->settings['analytics']);

				// google webmasters tools
				if (!empty($this->settings['wm_tools']))
					$head_tag->addTag('meta',
								array(
									'name' 		=> 'google-site-verification',
									'content' 	=> $this->settings['wm_tools']
								));
			}

			// page description
			if ($db_use) 
				$head_tag->addTag('meta',
							array(
								'name'		=> 'description',
								'content'	=> $this->settings['description']
							));

			// copyright
			$copyright = MainLanguageHandler::getInstance()->getText('copyright');
			$copyright = strip_tags($copyright);
			$head_tag->addTag('meta',
						array(
							'name'		=> 'copyright',
							'content'	=> $copyright
						));

			// favicon
			if (file_exists(_BASEPATH.'/images/favicon.png'))
				$icon_file = _BASEPATH.'/images/favicon.png'; else
				$icon_file = _BASEPATH.'/images/default_icon.png';

			$head_tag->addTag('link',
						array(
							'rel'	=> 'icon',
							'type'	=> 'image/png',
							'href'	=> url_GetFromFilePath($icon_file)
						));

			// add default styles and script if they exists
			if ($section != 'backend') {
				$head_tag->addTag('link',
						array(
							'rel'	=> 'stylesheet',
							'type'	=> 'text/css',
							'href'	=> url_GetFromFilePath(_BASEPATH.'/styles/common.css')
						));
	
				if (file_exists(_BASEPATH.'/styles/main.css'))
					$head_tag->addTag('link',
							array(
								'rel'	=> 'stylesheet',
								'type'	=> 'text/css',
								'href'	=> url_GetFromFilePath(_BASEPATH.'/styles/main.css')
							));
	
				if (file_exists(_BASEPATH.'/scripts/main.js'))
					$head_tag->addTag('script',
							array(
								'type'	=> 'text/javascript',
								'src'	=> url_GetFromFilePath(_BASEPATH.'/scripts/main.js')
							));
			}
		}

		// register backend
		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			$menu = $backend->getMenu($backend->name);

			if (!is_null($menu))
				$menu->insertChild(new backend_MenuItem(
										$this->getLanguageConstant('menu_page_info'),
										url_GetFromFilePath($this->path.'images/icon.png'),
										window_Open( // on click open window
													'page_settings',
													400,
													$this->getLanguageConstant('title_page_info'),
													true, false, // disallow minimize, safety feature
													backend_UrlMake($this->name, 'show')
												),
										$level=5
									), 1);
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
	public function transferControl($params, $children) {
		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'show':
					$this->showSettings();
					break;

				case 'save':
					$this->saveSettings();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		if (!isset($this->settings['description']))
			$this->saveSetting('description', '');

		if (!isset($this->settings['analytics']))
			$this->saveSetting('analytics', '');

		if (!isset($this->settings['wm_tools']))
			$this->saveSetting('wm_tools', '');
	}

	public function onDisable() {
	}

	/**
	 * Show settings form
	 */
	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
						'form_action'	=> backend_UrlMake($this->name, 'save'),
						'cancel_action'	=> window_Close('page_settings')
					);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save settings
	 */
	private function saveSettings() {
		$description = fix_chars($_REQUEST['description']);
		$analytics = fix_chars($_REQUEST['analytics']);
		$wm_tools = fix_chars($_REQUEST['wm_tools']);

		$this->saveSetting('description', $description);
		$this->saveSetting('analytics', $analytics);
		$this->saveSetting('wm_tools', $wm_tools);

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('page_settings')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}
}
