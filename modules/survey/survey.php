<?php

/**
 * Survey Module
 *
 * General survey module that can be used to gain response from users
 * or gather general data about them. 
 *
 * @author Mladen Mijatov
 */

require_once('units/entries_manager.php');
require_once('units/entry_data_manager.php');

class survey extends Module {
	private static $_instance;

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
				case 'save_from_xml':
					$this->saveFromXML($params, $children);
					break;

				case 'save_from_ajax':
					$this->saveFromAJAX();
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

		$sql = "CREATE TABLE IF NOT EXISTS `survey_entries` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`address` varchar(50) NOT NULL,
					`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
		if ($db_active == 1) $db->query($sql);

		$sql = "CREATE TABLE IF NOT EXISTS `survey_entry_data` (
					`entry` int(11) NOT NULL,
					`name` varchar(30) NOT NULL,
					`value` varchar(255) NOT NULL
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db_active, $db;

		$sql = "DROP TABLE IF EXISTS `survey_entries`, `survey_entry_data`;";
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Save data from $_REQUEST specified by XML tag
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	private function saveFromXML($tag_params, $children) {
		$manager = SurveyEntriesManager::getInstance();
		$data_manager = SurveyEntryDataManager::getInstance();

		// get existing entry from database 
		$entry = $manager->getSingleItem(
								$manager->getFieldNames(),
								array('address' => $_SERVER['REMOTE_ADDR'])
							);

		// if entry doesn't exist, create new one
		if (!is_object($entry)) {
			$manager->insertData(array(
							'address'	=> $_SERVER['REMOTE_ADDR']
						));
			$id = $manager->getInsertedID();
			$entry = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));
		}

		// parse children and see what we need to save
		foreach ($children as $child) {
		}
	}

	/**
	 * Save data from AJAX request
	 */
	private function saveFromAJAX() {
		$manager = SurveyEntriesManager::getInstance();
		$data_manager = SurveyEntryDataManager::getInstance();

		// get existing entry from database 
		$entry = $manager->getSingleItem(
								$manager->getFieldNames(),
								array('address' => $_SERVER['REMOTE_ADDR'])
							);

		// if entry doesn't exist, create new one
		if (!is_object($entry)) {
			$manager->insertData(array(
							'address'	=> $_SERVER['REMOTE_ADDR']
						));
			$id = $manager->getInsertedID();
			$entry = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));
		}
		
		$data = $_REQUEST;
		unset($data['section']);
		unset($data['action']);

		foreach ($data as $key => $value) {
			$data_manager->insertData(array(
								'entry'	=> $entry->id,
								'name'	=> $key,
								'value'	=> fix_chars($value)
							));
		}

		define('_OMIT_STATS', 1);
		print json_encode(true);
	}
}
