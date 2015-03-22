<?php

/**
 * Page Information
 *
 * This module integrates some of the basic elements of the web site
 * pages. It also provides support for Google Webmasters Tools and Analytics.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;


class page_info extends Module {
	private static $_instance;
	private $omit_elements = array();
	private $optimizer_page = '';
	private $optimizer_show_control = false;
	private $page_description = null;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section, $db_use;

		parent::__construct(__FILE__);

		// let the browser/crawler know we have different desktop/mobile styles
		if ($_SERVER['SERVER_PROTOCOL'] == 'HTTP/1.1')
			header('Vary: User-Agent');

		// change powered by header
		header('X-Powered-By: Caracal/'._VERSION);

		// send encoding
		header('Content-Type: text/html; charset=UTF-8');

		// register backend
		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			$menu = $backend->getMenu($backend->name);

			if (!is_null($menu))
				$menu->insertChild(new backend_MenuItem(
										$this->getLanguageConstant('menu_page_info'),
										url_GetFromFilePath($this->path.'images/icon.svg'),
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
		if (isset($params['action']))
			switch ($params['action']) {
				case 'set_omit_elements':
					$this->omit_elements = fix_chars(explode(',', $params['elements']));
					break;

				case 'set_optimizer_page':
					$this->optimizer_page = fix_chars($params['page']);
					if (isset($params['show_control']))
						$this->optimizer_show_control = fix_id($params['show_control']) == 0 ? false : true;
					break;

				case 'set_description':
					$this->setDescription($params, $children);
					break;

				default:
					break;
			}

		// backend control actions
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

		if (!isset($this->settings['bing_wm_tools']))
			$this->saveSetting('bing_wm_tools', '');
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

		$template->registerTagHandler('cms:analytics_versions', $this, 'tag_AnalyticsVersions');

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
		$analytics_domain = fix_chars($_REQUEST['analytics_domain']);
		$analytics_version = fix_chars($_REQUEST['analytics_version']);
		$wm_tools = fix_chars($_REQUEST['wm_tools']);
		$bing_wm_tools = fix_chars($_REQUEST['bing_wm_tools']);
		$optimizer = fix_chars($_REQUEST['optimizer']);
		$optimizer_key = fix_chars($_REQUEST['optimizer_key']);

		$this->saveSetting('description', $description);
		$this->saveSetting('analytics', $analytics);
		$this->saveSetting('analytics_domain', $analytics_domain);
		$this->saveSetting('analytics_version', $analytics_version);
		$this->saveSetting('wm_tools', $wm_tools);
		$this->saveSetting('bing_wm_tools', $bing_wm_tools);
		$this->saveSetting('optimizer', $optimizer);
		$this->saveSetting('optimizer_key', $optimizer_key);

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

	/**
	 * Method called by the page module to add elements before printing
	 */
	public function addElements() {
		global $section, $db_use, $optimize_code, $url_rewrite, $styles_path,
			$images_path, $scripts_path, $system_styles_path, $system_images_path,
			$default_language;

		$head_tag = head_tag::getInstance();
		$collection = collection::getInstance();
		$language_list = Language::getLanguages(false);

		// add base url tag
		$head_tag->addTag('base', array('href' => _BASEURL));

		// add mobile menu script
		if (_MOBILE_VERSION && !in_array('mobile_menu', $this->omit_elements))
			$collection->includeScript(collection::MOBILE_MENU);

		// content meta tags
		if (!in_array('content_type', $this->omit_elements)) {
			$head_tag->addTag('meta',
						array(
							'http-equiv'	=> 'Content-Type',
							'content'		=> 'text/html; charset=UTF-8'
						));
		}

		if (!in_array('viewport', $this->omit_elements) && _MOBILE_VERSION)
			$head_tag->addTag('meta',
						array(
							'name'		=> 'viewport',
							'content'	=> 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0'
						));

		if (!in_array('language', $this->omit_elements) && _STANDARD == 'html401')
			$head_tag->addTag('meta',
						array(
							'http-equiv'	=> 'Content-Language',
							'content'		=> join(', ', $language_list)
						));

		// add other languages if required
		if (count($language_list) > 1 && $url_rewrite && class_exists('language_menu'))
			language_menu::getInstance()->addMeta();

		// robot tags
		$head_tag->addTag('meta', array('name' => 'robots', 'content' => 'index, follow'));
		$head_tag->addTag('meta', array('name' => 'googlebot', 'content' => 'index, follow'));
		$head_tag->addTag('meta', array('name' => 'rating', 'content' => 'general'));

		if ($section != 'backend' && $section != 'backend_module' && $db_use) {
			// google analytics
			if (!empty($this->settings['analytics']))
				$head_tag->addGoogleAnalytics(
										$this->settings['analytics'],
										$this->settings['analytics_domain'],
										$this->settings['analytics_version']
									);

			// google website optimizer
			if (!empty($this->settings['optimizer']))
				$head_tag->addGoogleSiteOptimizer(
										$this->settings['optimizer'],
										$this->settings['optimizer_key'],
										$this->optimizer_page,
										$this->optimizer_show_control
									);

			// google webmaster tools
			if (!empty($this->settings['wm_tools']))
				$head_tag->addTag('meta',
							array(
								'name' 		=> 'google-site-verification',
								'content' 	=> $this->settings['wm_tools']
							));

			// bing webmaster tools
			if (!empty($this->settings['bing_wm_tools']))
				$head_tag->addTag('meta',
							array(
								'name' 		=> 'msvalidate.01',
								'content' 	=> $this->settings['bing_wm_tools']
							));

			// page description
			if (!is_null($this->page_description))
				$value = $this->page_description; else
				$value = isset($this->settings['description']) ? $this->settings['description'] : '';

			$head_tag->addTag('meta',
						array(
							'name'		=> 'description',
							'content'	=> $value
						));
		}

  		// copyright
		if (!in_array('copyright', $this->omit_elements) && _STANDARD == 'html401') {
			$copyright = Language::getText('copyright');
			$copyright = strip_tags($copyright);
			$head_tag->addTag('meta',
						array(
							'name'		=> 'copyright',
							'content'	=> $copyright
						));
		}

		// favicon
		if (file_exists(_BASEPATH.'/'.$images_path.'favicon.png')) {
			// regular, single size favicon
			$icon_files = array(
					'16x16'	=> _BASEPATH.'/'.$images_path.'favicon.png'
				);

		} else if (file_exists(_BASEPATH.'/'.$images_path.'favicon')) {
			$icon_sizes = array(16, 32, 64);
			$icon_files = array();

			foreach ($icon_sizes as $size) {
				$file_name = _BASEPATH.'/'.$images_path.'favicon/'.$size.'.png';
				if (file_exists($file_name))
					$icon_files[$size.'x'.$size] = $file_name;
			}

		} else {
			$icon_files = array(
					'16x16'	=> _BASEPATH.'/'.$system_images_path.'default_icon/16.png',
					'32x32'	=> _BASEPATH.'/'.$system_images_path.'default_icon/32.png',
					'64x64'	=> _BASEPATH.'/'.$system_images_path.'default_icon/64.png'
				);
		}

		foreach ($icon_files as $sizes => $icon)
			$head_tag->addTag('link',
						array(
							'rel'	=> 'icon',
							'type'	=> 'image/png',
							'sizes'	=> $sizes,
							'href'	=> url_GetFromFilePath($icon)
						));

		// add default styles and script if they exists
		$collection->includeScript(collection::JQUERY);

		if ($section != 'backend') {
			$styles = array();
			$less_style = null;

			// prepare list of files without extensions
			if (_DESKTOP_VERSION) {
				$styles = array(
						$system_styles_path.'common.css',
						$styles_path.'main.css',
						$styles_path.'header.css',
						$styles_path.'content.css',
						$styles_path.'footer.css'
					);
				$less_style = $styles_path.'main.less';

			} else {
				$styles = array(
						$system_styles_path.'common.css',
						$styles_path.'main.css',
						$styles_path.'header_mobile.css',
						$styles_path.'content_mobile.css',
						$styles_path.'footer_mobile.css'
					);

				$less_style = $styles_path.'main_mobile.less';
			}

			// include styles
			foreach ($styles as $style) {
				// check for css files
				if (file_exists(_BASEPATH.'/'.$style))
					$head_tag->addTag('link',
							array(
								'rel'	=> 'stylesheet',
								'type'	=> 'text/css',
								'href'	=> url_GetFromFilePath(_BASEPATH.'/'.$style)
							));
			}

			// add main less file if it exists
			if (file_exists(_BASEPATH.'/'.$less_style)) {
				$head_tag->addTag('link',
						array(
							'rel'	=> 'stylesheet/less',
							'type'	=> 'text/css',
							'href'	=> url_GetFromFilePath(_BASEPATH.'/'.$less_style)
						));

				if (!$optimize_code)
					$collection->includeScript(collection::LESS);
			}

			// add main javascript
			if (file_exists(_BASEPATH.'/'.$scripts_path.'main.js'))
				$head_tag->addTag('script',
						array(
							'type'	=> 'text/javascript',
							'src'	=> url_GetFromFilePath(_BASEPATH.'/'.$scripts_path.'main.js')
						));
		}
	}

	/**
	 * Set page description for current execution.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	private function setDescription($tag_params, $children) {
		global $language;

		// set from language constant
		if (isset($tag_params['constant'])) {
			$constant = fix_chars($tag_params['constant']);
			$this->page_description = Language::getText($constant);

		// set from article
		} else if (isset($tag_params['article']) && class_exists('articles')) {
			$manager = ArticleManager::getInstance();
			$text_id = fix_chars($tag_params['article']);

			// get article from database
			$item = $manager->getSingleItem(array('content'), array('text_id' => $text_id));

			if (is_object($item)) {
				$content = strip_tags(Markdown($item->content[$language]));
				$data = explode("\n", utf8_wordwrap($content, 150, "\n", true));

				if (count($data) > 0)
					$this->page_description = $data[0];
			}
		}
	}

	/**
	 * Show list of available analytics versions.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_AnalyticsVersions($tag_params, $children) {
		// load template
		$template = $this->loadTemplate($tag_params, 'analytics_version.xml');
		$selected = isset($tag_params['selected']) ? $tag_params['selected'] : '0';

		// get available versions
		$versions = array();
		$files = scandir(_BASEPATH.'/modules/head_tag/templates/');

		foreach ($files as $file)
			if (substr($file, 0, 16) == 'google_analytics')
				$versions[] = substr(basename($file, '.xml'), 17);

		// show options
		if (count($versions) > 0)
			foreach ($versions as $version) {
				$params = array(
						'version'	=> $version,
						'selected'	=> $selected
					);
				$template->setLocalParams($params);
				$template->restoreXML();
				$template->parse();
			}
	}
}
