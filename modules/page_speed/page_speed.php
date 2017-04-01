<?php

/**
 * Google PageSpeed Implementation
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */
use Core\Module;


class page_speed extends Module {
	private static $_instance;

	private $url = 'https://www.googleapis.com/pagespeedonline/v1/runPagespeed?url=%url%&key=%key%';
	private $data_cache = null;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// register backend
		if (ModuleHandler::is_loaded('backend')) {
			$backend = backend::get_instance();
			$menu = $backend->getMenu($backend->name);

			if (!is_null($menu))
				$menu->insertChild(new backend_MenuItem(
										$this->get_language_constant('menu_page_speed'),
										$this->path.'images/icon.svg',
										window_Open( // on click open window
													'page_speed',
													670,
													$this->get_language_constant('title_page_speed'),
													true, false, // disallow minimize, safety feature
													backend_UrlMake($this->name, 'show')
												),
										$level=5
									), 1);

			// add style for backend
			if (ModuleHandler::is_loaded('head_tag') && $section == 'backend') {
				$head_tag = head_tag::get_instance();
				$head_tag->addTag('link', array('href'=>URL::from_file_path($this->path.'include/page_speed.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			}
		}
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
	public function transfer_control($params = array(), $children = array()) {
		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'show':
					$this->showPageSpeed();
					break;

				case 'check':
					$this->checkPageSpeed();
					break;

				case 'set_api_key':
					$this->setApiKey();
					break;

				case 'save_api_key':
					$this->saveApiKey();
					break;

				default:
					break;
			}
	}

	/**
	 * Show main form.
	 */
	private function showPageSpeed() {
		// populate cache for later use
		$this->data_cache = json_decode(file_get_contents($this->path.'data/page_speed.json'));

		// show page content
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
						'check_page_speed' => window_OpenHyperlink(
											$this->get_language_constant('check_page_speed'),
											'page_speed_check', 260,
											$this->get_language_constant('title_check_page_speed'),
											true, false,
											$this->name,
											'check'
										),
						'set_api_key' => window_OpenHyperlink(
											$this->get_language_constant('set_api_key'),
											'page_speed_set_api_key', 400,
											$this->get_language_constant('title_set_api_key'),
											true, false,
											$this->name,
											'set_api_key'
										),
					);

		// add tag handlers
		$template->register_tag_handler('_general_information', $this, 'tag_GeneralInformation');
		$template->register_tag_handler('_detailed_information', $this, 'tag_DetailedInformation');

		$template->set_local_params($params);
		$template->restore_xml();
		$template->parse();
	}

	/**
	 * Check page speed and show message.
	 */
	private function checkPageSpeed() {
		$url = isset($_REQUEST['url']) ? fix_chars($_REQUEST['url']) : _BASEURL;
		$api_key = isset($this->settings['api_key']) ? $this->settings['api_key'] : null;
		$request = $this->url;

		if (!is_null($api_key)) {
			$request = str_replace('%url%', $url, $request);
			$request = str_replace('%key%', $api_key, $request);
			$data = file_get_contents($request);
			file_put_contents($this->path.'data/page_speed.json', $data);

			$message = $this->get_language_constant('message_check_page_speed_done');

		} else {
			$message = $this->get_language_constant('message_check_page_speed_error');
		}

		// prepare and parse result message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $message,
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('page_speed_check').';'.window_ReloadContent('page_speed')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for setting/changing API key.
	 */
	private function setApiKey() {
		$template = new TemplateHandler('set_api_key.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'api_key'		=> isset($this->settings['api_key']) ? $this->settings['api_key'] : '',
					'form_action'	=> backend_UrlMake($this->name, 'save_api_key'),
					'cancel_action'	=> window_Close('page_speed_set_api_key')
				);

		$template->register_tag_handler('_module_list', $this, 'tag_ModuleList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save new or changed API key.
	 */
	private function saveApiKey() {
		$api_key = fix_chars($_REQUEST['api_key']);
		$this->save_setting('api_key', $api_key);

		// prepare and parse result message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_api_key_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('page_speed_set_api_key')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function initialize() {
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
	}

	/**
	 * Handle drawing general information.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_GeneralInformation($tag_params, $children) {
		$template = $this->load_template($tag_params, 'general_information.xml');
		$template->set_template_params_from_array($children);

		$page_stats = $this->data_cache->pageStats;

		$params = array(
				'score'				=> $this->data_cache->score,
				'title'				=> $this->data_cache->title,

				'bytes_request' 	=> number_format($page_stats->totalRequestBytes / 1000, 2),
				'bytes_html'		=> number_format($page_stats->htmlResponseBytes / 1000, 2),
				'bytes_image'		=> number_format($page_stats->imageResponseBytes / 1000, 2),
				'bytes_css'			=> number_format($page_stats->cssResponseBytes / 1000, 2),
				'bytes_javascript'	=> number_format($page_stats->javascriptResponseBytes / 1000, 2),
				'bytes_other'		=> number_format($page_stats->otherResponseBytes / 1000, 2),

				'number_hosts'		=> $page_stats->numberHosts,
				'number_resources'	=> $page_stats->numberResources,
				'number_css'		=> $page_stats->numberCssResources,
				'number_javascript'	=> $page_stats->numberJsResources,
				'number_static'		=> $page_stats->numberStaticResources,
				'number_problems'	=> 0,

				'image_bytes'		=> 'http://chart.apis.google.com/chart?'.
								'chs=410x150'.
								'&chdlp=l'.
								'&chtt=Data Distribution'.
								'&chdl=HTTP|HTML|Images|CSS|JavaScript|Other'.
								'&chd=t:'.
								($page_stats->totalRequestBytes/1000).','.
								($page_stats->htmlResponseBytes/1000).','.
								($page_stats->imageResponseBytes/1000).','.
								($page_stats->cssResponseBytes/1000).','.
								($page_stats->javascriptResponseBytes/1000).','.
								($page_stats->otherResponseBytes/1000).
								'&cht=p3'.
								'&chco=c44448|30845c|f0e848|bc306c|2874c4|ff9620',
				'image_score'		=> 'http://chart.apis.google.com/chart?'.
								'chtt=PageSpeed Score'.
								'&chs=200x150'.
								'&cht=gom'.
								'&chd=t:'.$this->data_cache->score.
								'&chxt=x,y'.
								'&chxl=0:|'.$this->data_cache->score
			);

		$template->set_local_params($params);
		$template->restore_xml();
		$template->parse();
	}

	/**
	 * Handle drawing detail PageSpeed information.
	 *
	 * @param array $tag_params
	 * @param array $chidren
	 */
	public function tag_DetailedInformation($tag_params, $children) {
	}
}
