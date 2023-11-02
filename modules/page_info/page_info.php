<?php

/**
 * Page Information
 *
 * This module integrates some of the basic elements of the web site
 * pages. It also provides support for Google Webmasters Tools and Analytics.
 *
 * Author: Mladen Mijatov
 */
use Core\Events;
use Core\Module;
use Core\Markdown;


class page_info extends Module {
	private static $_instance;
	private $omit_elements = array();
	private $optimizer_page = '';
	private $optimizer_show_control = false;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// connect events
		Events::connect('head-tag', 'before-print', 'add_tags', $this);
		Events::connect('backend', 'add-menu-items', 'add_menu_items', $this);
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
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
	public function transfer_control($params, $children) {
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
					$this->save_settings();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function initialize() {
		if (!isset($this->settings['analytics']))
			$this->save_setting('analytics', '');

		if (!isset($this->settings['wm_tools']))
			$this->save_setting('wm_tools', '');

		if (!isset($this->settings['bing_wm_tools']))
			$this->save_setting('bing_wm_tools', '');
	}

	public function cleanup() {
	}

	/**
	 * Add items to backend menu.
	 */
	public function add_menu_items() {
		$backend = backend::get_instance();
		$menu = $backend->getMenu($backend->name);

		if (!is_null($menu))
			$menu->insertChild(new backend_MenuItem(
								$this->get_language_constant('menu_page_info'),
								$this->path.'images/icon.svg',
								window_Open( // on click open window
											'page_settings',
											400,
											$this->get_language_constant('title_page_info'),
											true, false, // disallow minimize, safety feature
											backend_UrlMake($this->name, 'show')
										),
								$level=6
							), 1);
	}

	/**
	 * Method called by the page module to add elements before printing
	 */
	public function add_tags() {
		global $section, $db_type, $styles_path, $images_path, $scripts_path;
		global $system_styles_path, $system_images_path, $default_language;

		$head_tag = head_tag::get_instance();
		$collection = collection::get_instance();
		$language_list = Language::get_languages(false);
		$ignored_section = in_array($section, array('backend', 'backend_module'));

		// add base url tag
		$head_tag->add_tag('meta',
			array(
				'property' => 'base-url',
				'content'  => _BASEURL
			));
		$head_tag->add_tag('meta',
			array(
				'http-equiv' => 'X-UA-Compatible',
				'content'    => 'IE=edge'
			));

		// add mobile menu script
		if (_MOBILE_VERSION && !in_array('mobile_menu', $this->omit_elements))
			$collection->includeScript(collection::MOBILE_MENU);

		// content meta tags
		if (!in_array('charset', $this->omit_elements)) {
			$head_tag->add_tag('meta', array('charset' => 'UTF-8'));
		}

		if (!in_array('viewport', $this->omit_elements))
			$head_tag->add_tag('meta',
						array(
							'name'		=> 'viewport',
							'content'	=> 'width=device-width, initial-scale=1, maximum-scale=5'
						));

		// robot tags
		$head_tag->add_tag('meta', array('name' => 'robots', 'content' => 'index, follow'));
		$head_tag->add_tag('meta', array('name' => 'googlebot', 'content' => 'index, follow'));

		if (!$ignored_section && $db_type != DatabaseType::NONE) {
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
				$head_tag->add_tag('meta',
							array(
								'name' 		=> 'google-site-verification',
								'content' 	=> $this->settings['wm_tools']
							));

			// bing webmaster tools
			if (!empty($this->settings['bing_wm_tools']))
				$head_tag->add_tag('meta',
							array(
								'name' 		=> 'msvalidate.01',
								'content' 	=> $this->settings['bing_wm_tools']
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
			$head_tag->add_tag('link',
						array(
							'rel'	=> 'icon',
							'type'	=> 'image/png',
							'sizes'	=> $sizes,
							'href'	=> URL::from_file_path($icon)
						));

		// add default styles and script if they exists
		$collection->includeScript(collection::JQUERY);

		if (!$ignored_section) {
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
					$head_tag->add_tag('link',
							array(
								'rel'	=> 'stylesheet',
								'type'	=> 'text/css',
								'href'	=> URL::from_file_path(_BASEPATH.'/'.$style)
							));
			}

			// add main less file if it exists
			if (file_exists(_BASEPATH.'/'.$less_style)) {
				$head_tag->add_tag('link',
						array(
							'rel'	=> 'stylesheet/less',
							'type'	=> 'text/css',
							'href'	=> URL::from_file_path(_BASEPATH.'/'.$less_style)
						));
			}

			// add main javascript
			if (file_exists(_BASEPATH.'/'.$scripts_path.'main.js'))
				$head_tag->add_tag('script',
						array(
							'type'	=> 'text/javascript',
							'src'	=> URL::from_file_path(_BASEPATH.'/'.$scripts_path.'main.js')
						));
		}
	}

	/**
	 * Show settings form
	 */
	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
						'form_action'	=> backend_UrlMake($this->name, 'save'),
					);

		$template->register_tag_handler('cms:analytics_versions', $this, 'tag_AnalyticsVersions');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save settings
	 */
	private function save_settings() {
		$analytics = fix_chars($_REQUEST['analytics']);
		$analytics_domain = fix_chars($_REQUEST['analytics_domain']);
		$analytics_version = fix_chars($_REQUEST['analytics_version']);
		$wm_tools = fix_chars($_REQUEST['wm_tools']);
		$bing_wm_tools = fix_chars($_REQUEST['bing_wm_tools']);
		$optimizer = fix_chars($_REQUEST['optimizer']);
		$optimizer_key = fix_chars($_REQUEST['optimizer_key']);

		$this->save_setting('analytics', $analytics);
		$this->save_setting('analytics_domain', $analytics_domain);
		$this->save_setting('analytics_version', $analytics_version);
		$this->save_setting('wm_tools', $wm_tools);
		$this->save_setting('bing_wm_tools', $bing_wm_tools);
		$this->save_setting('optimizer', $optimizer);
		$this->save_setting('optimizer_key', $optimizer_key);

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message' => $this->get_language_constant('message_saved'),
					'button'  => $this->get_language_constant('close'),
					'action'  => window_Close('page_settings')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show list of available analytics versions.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_AnalyticsVersions($tag_params, $children) {
		global $system_module_path;

		// load template
		$template = $this->load_template($tag_params, 'analytics_version.xml');
		$template->set_template_params_from_array($children);
		$selected = isset($tag_params['selected']) ? $tag_params['selected'] : '0';

		// get available versions
		$versions = array();
		$files = scandir(_BASEPATH.'/'.$system_module_path.'head_tag/templates/');

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
				$template->set_local_params($params);
				$template->restore_xml();
				$template->parse();
			}
	}
}
