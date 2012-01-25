<?php

/**
 * Search Module
 */

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
		global $db_active, $db;

		$sql = "";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db_active, $db;

		$sql = "";

		if ($db_active == 1) $db->query($sql);
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
	 *				'description	=> '',		// short description, if exists
	 *				'id'			=> 0,		// id of containing item
	 *				'custom'		=> ''		// module or item custom field
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
		$query = array(
				'words'		=> array(),
				'exclude'	=> array()
			);

		// get search query
		$query_string = null;

		if (isset($tag_params['query'])) 
			$query_string = fix_chars($tag_params['query']);

		if (isset($_REQUEST['query']) && is_null($query_string))
			$query_string = fix_chars($_REQUEST['query']);

		if (is_null($query_string))
			return;

		$raw_query = split(' ', $query_string);
		foreach ($raw_query as $raw)
			if ($raw[0] != '-')
				$query['words'][] = $raw; else
				$query['exclude'][] = substr($raw, 1);

		// get list of modules to search on
		$module_list = null;
	
		if (isset($tag_params['module_list']))
			$module_list = fix_chars(split($params['module_list']));

		if (isset($_REQUEST['module_list']) && is_null($module_list))
			$module_list = fix_chars(split($_REQUEST['module_list'));

		if (is_null($module_list))
			$module_list = array_keys($this->modules);

		// get results from modules
		$results = array();
		if (count($this->modules) > 0)
			foreach ($this->modules as $name => $module) 
				if ($name in $module_list)
					$results = array_merge($results, $module->getSearchResults($query));

		// sort results
		$results = uasort($results, array($this, 'sortResults'));

		// load template
		$template = $this->loadTemplate($tag_params, 'result.xml');

		// parse results
		if (count($results) > 0)
			foreach ($results as $result) {
				$template->restoreXML();
				$template->setLocalParams($result);
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
	private function sortResults($item1, $item1) {
		$result = 0;

		if ($item1['score'] != $item2['score'])
			$result = $item1['score'] < $item2['score'] ? -1 : 1;

		return $result;
	}
}

