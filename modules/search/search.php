<?php

/**
 * Search Module
 */
use Core\Module;


class search extends Module {
	private static $_instance;
	private $modules = array();

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;
		
		parent::__construct(__FILE__);

		// load module style and scripts
		if (class_exists('head_tag')) {
			$head_tag = head_tag::getInstance();
			//$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/_blank.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/_blank.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if (class_exists('backend')) {
			$backend = backend::getInstance();
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
				case 'show_results':
					$this->tag_ResultList($params, $children);
					break;

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
	public function onInit() {
		global $db;

		/* $sql = ""; */
		/* $db->query($sql); */
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		/* $sql = ""; */
		/* $db->query($sql); */
	}

	/**
	 * Register module to be included in search
	 *
	 * @param string $name
	 * @param object $module
	 */
	public function registerModule($name, $module) {
		$this->modules[$name] = $module;
	}

	/**
	 * Handle printing search results
	 *
	 * Modules need to return results in following format:
	 * array(
	 *			array(
	 * 				'score'			=> 0..100	// score for this result
	 * 				'title'			=> '',		// title to be shown in list
	 *				'description'	=> '',		// short description, if exists
	 *				'id'			=> 0,		// id of containing item
	 *				'type'			=> '',		// type of item
	 *				'module'		=> ''		// module name
	 *			),
	 *			...
	 * 		);
	 * 
	 * Resulting array doesn't need to be sorted.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ResultList($tag_params, $children) {
		// get search query
		$query_string = null;
		$threshold = 25;
		$limit = 30;

		// get query
		if (isset($tag_params['query'])) 
			$query_string = mb_strtolower(fix_chars($tag_params['query']));

		if (isset($_REQUEST['query']) && is_null($query_string))
			$query_string = mb_strtolower(fix_chars($_REQUEST['query']));

		if (is_null($query_string))
			return;

		// get threshold
		if (isset($tag_params['threshold'])) 
			$threshold = fix_chars($tag_params['threshold']);

		if (isset($_REQUEST['threshold']) && is_null($threshold))
			$threshold = fix_chars($_REQUEST['threshold']);

		// get limit
		if (isset($tag_params['limit']))
			$limit = fix_id($tag_params['limit']);

		// get list of modules to search on
		$module_list = null;
	
		if (isset($tag_params['module_list']))
			$module_list = fix_chars(split(',', $tag_params['module_list']));

		if (isset($_REQUEST['module_list']) && is_null($module_list))
			$module_list = fix_chars(split(',', $_REQUEST['module_list']));

		if (is_null($module_list))
			$module_list = array_keys($this->modules);

		// get intersection of available and specified modules
		$available_modules = array_keys($this->modules);
		$module_list = array_intersect($available_modules, $module_list);

		// get results from modules
		$results = array();
		if (count($module_list) > 0)
			foreach ($module_list as $name) {
				$module = $this->modules[$name];
				$results = array_merge($results, $module->getSearchResults($query_string, $threshold));
			}

		// sort results
		usort($results, array($this, 'sortResults'));
		
		// apply limit
		if ($limit > 0) 
			$results = array_slice($results, 0, $limit);

		// load template
		$template = $this->loadTemplate($tag_params, 'result.xml');

		// parse results
		if (count($results) > 0)
			foreach ($results as $params) {
				$template->setLocalParams($params);
				$template->restoreXML();
				$template->parse();
			}
	}

	/**
	 * Function that compares two results
	 *
	 * @param array $item1
	 * @param array $item2
	 * @return integer
	 */
	private function sortResults($item1, $item2) {
		$result = 0;

		if ($item1['score'] != $item2['score'])
			$result = $item1['score'] < $item2['score'] ? 1 : -1;

		return $result;
	}
}

