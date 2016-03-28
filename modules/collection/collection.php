<?php

/**
 * Script Collections Module
 *
 * This module is used to manage and organize collection of scripts. It provides
 * easy way to include scripts and their dependencies.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;


class collection extends Module {
	private static $_instance;

	// internal scripts
	const WINDOW_SYSTEM = 0;
	const NOTEBOOK = 1;
	const TOOLBAR = 2;
	const ANIMATION_CHAIN = 3;
	const DIALOG = 4;
	const SCROLLBAR = 5;
	const PAGE_CONTROL = 6;
	const MOBILE_MENU = 7;
	const COMMUNICATOR = 8;
	const DYNAMIC_PAGE_CONTENT = 9;
	const PROPERTY_EDITOR = 10;

	// jQuery and its extensions
	const JQUERY = 50;
	const JQUERY_EVENT_DRAG = 51;
	const JQUERY_EVENT_SCROLL = 52;
	const JQUERY_EVENT_OUTSIDE = 54;
	const JQUERY_EXTENSIONS = 53;

	// other scripts
	const LESS = 100;
	const SHOWDOWN = 101;
	const PREFIX_FREE = 102;

	// script files
	private $script_files = array(
				collection::WINDOW_SYSTEM        => array('window_system.js', 'window_system.css'),
				collection::NOTEBOOK             => array('notebook.js', 'notebook.css'),
				collection::TOOLBAR              => 'toolbar.js',
				collection::ANIMATION_CHAIN      => 'animation_chain.js',
				collection::DIALOG               => array('dialog.js', 'dialog.css'),
				collection::SCROLLBAR            => 'scrollbar.js',
				collection::PAGE_CONTROL         => 'page_control.js',
				collection::MOBILE_MENU          => array('mobile_menu.js', 'mobile_menu.css'),
				collection::COMMUNICATOR         => 'communicator.js',
				collection::DYNAMIC_PAGE_CONTENT => 'dynamic_page_content.js',
				collection::PROPERTY_EDITOR      => 'property_editor.js',
				collection::JQUERY               => 'jquery.js',
				collection::JQUERY_EVENT_DRAG    => 'jquery.event.drag.js',
				collection::JQUERY_EVENT_SCROLL  => 'jquery.mousewheel.js',
				collection::JQUERY_EVENT_OUTSIDE => 'jquery.event.outside.js',
				collection::JQUERY_EXTENSIONS    => 'jquery.extensions.js',
				collection::LESS                 => 'less.js',
				collection::SHOWDOWN             => 'showdown.js',
				collection::PREFIX_FREE          => 'prefixfree.js'
			);

	private $script_names = array(
				'window_system'        => collection::WINDOW_SYSTEM,
				'notebook'             => collection::NOTEBOOK,
				'toolbar'              => collection::TOOLBAR,
				'animation_chain'      => collection::ANIMATION_CHAIN,
				'dialog'               => collection::DIALOG,
				'scrollbar'            => collection::SCROLLBAR,
				'page_control'         => collection::PAGE_CONTROL,
				'mobile_menu'          => collection::MOBILE_MENU,
				'communicator'         => collection::COMMUNICATOR,
				'dynamic_page_content' => collection::DYNAMIC_PAGE_CONTENT,
				'property_editor'      => collection::PROPERTY_EDITOR,
				'jquery'               => collection::JQUERY,
				'jquery_event_drag'    => collection::JQUERY_EVENT_DRAG,
				'jquery_event_scroll'  => collection::JQUERY_EVENT_SCROLL,
				'jquery_event_outside' => collection::JQUERY_EVENT_OUTSIDE,
				'jquery_extensions'    => collection::JQUERY_EXTENSIONS,
				'less'                 => collection::LESS,
				'showdown'             => collection::SHOWDOWN,
				'prefix_free'          => collection::PREFIX_FREE
			);

	// list of included scripts
	private $included = array();

	// cached instance of head tag
	private $head_tag = null;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__, false);

		// get instance of head tag early on
		if (ModuleHandler::is_loaded('head_tag'))
			$this->head_tag = head_tag::getInstance();

		// include scripts by default
		$this->includeScriptById(collection::JQUERY);
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
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {}

	/**
	 * Get script id from its name.
	 *
	 * @param string $script_name
	 * @return integer
	 */
	private function getScriptId($script_name) {
		$result = null;

		if (in_array($script_name, $this->script_names))
			$result = $this->script_names[$script_name];

		return $result;
	}

	/**
	 * Include script by its id.
	 *
	 * @param integer $script
	 */
	private function includeScriptById($script) {
		if (is_null($this->head_tag)) {
			trigger_error('Required module "head_tag" is not present!', E_USER_WARNING);
			return;
		}

		// no need to include script two times
		if (in_array($script, $this->included))
			return;

		// get script files
		$data = $this->script_files[$script];

		// add script to the list of included
		$this->included []= $script;

		if (is_array($data)) {
			// script requires more than one file
			foreach($data as $file_name) {
				switch (pathinfo($file_name, PATHINFO_EXTENSION)) {
					case 'css':
						// include css
						$this->head_tag->addTag(
							'link',
							array(
								'href'	=> url_GetFromFilePath($this->path.'include/'.$file_name),
								'type'	=> 'text/css',
								'rel'	=> 'stylesheet'
							)
						);
						break;

					case 'js':
					default:
						// include javascript
						$this->head_tag->addTag(
							'script',
							array(
								'src'	=> url_GetFromFilePath($this->path.'include/'.$file_name),
								'type'	=> 'text/javascript'
							)
						);
						break;
				}
			}

		} else {
			// include single file
			$this->head_tag->addTag(
						'script',
						array(
							'src'	=> url_GetFromFilePath($this->path.'include/'.$data),
							'type'	=> 'text/javascript'
						)
					);

		}
	}

	/**
	 * Add script with specified name to head_tag.
	 *
	 * @param mixed $script
	 */
	public function includeScript($script) {
		if (is_int($script)) {
			// include script by its id
			if (array_key_exists($script, $this->script_files))
				$this->includeScriptById($script); else
				trigger_error('Missing: '.$script, E_USER_NOTICE);

		} else if (is_string($script)) {
			// include script by its name
			$script_id = $this->getScriptId($script);

			if (!is_null($script_id))
				$this->includeScriptById($script_id);

		} else if (is_array($script)) {
			// batch include
			foreach($script as $script_iter)
				$this->includeScript($script_iter);
		}
	}
}
