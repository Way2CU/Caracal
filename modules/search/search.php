<?php

/**
 * Search Module
 *
 * This module provides unified mechanism of collecting and presenting search
 * results from various modules. It operates through Events system to seamlessly
 * communicate with other modules without exlicitly them requiring to depend on
 * this module being enabled.
 *
 * Author: Mladen Mijatov
 */
use Core\Events;
use Core\Module;


class search extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);

		// create events
		Events::register('search', 'get-results', 3);  // module list, query, threshold
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
	public function transfer_control($params, $children) {
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
	public function on_init() {
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function on_disable() {
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
		$module_list = array();
		if (isset($tag_params['module_list']))
			$module_list = fix_chars(explode(',', $tag_params['module_list']));

		// get search results
		$results = array();
		$raw_results = Events::trigger('search', 'get-results', $module_list, $query_string, $threshold);
		foreach ($raw_results as $result)
			$results = array_merge($results, $result);

		// sort results
		usort($results, array($this, 'sort_results'));

		// apply limit
		if ($limit > 0)
			$results = array_slice($results, 0, $limit);

		// load template
		$template = $this->load_template($tag_params, 'result.xml');
		$template->setTemplateParamsFromArray($children);

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
	private function sort_results($item1, $item2) {
		$result = 0;

		if ($item1['score'] != $item2['score'])
			$result = $item1['score'] < $item2['score'] ? 1 : -1;

		return $result;
	}
}

