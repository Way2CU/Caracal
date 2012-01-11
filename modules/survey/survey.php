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
	public function transferControl($params = array(), $children = array()) {
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
				) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db_active, $db;

		$sql = "DROP TABLE IF EXISTS `survey_entries`;";
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Save data from $_REQUEST specified by XML tag
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	private function saveFromXML($tag_params, $children) {
		
	}

	private function saveFromAJAX() {
	}
}
