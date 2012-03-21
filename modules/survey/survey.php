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
require_once('units/types_manager.php');

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
		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			$survey_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_survey'),
					url_GetFromFilePath($this->path.'images/icon.png'),
					'javascript:void(0);',
					$level=5
				);

			$survey_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_results'),
								url_GetFromFilePath($this->path.'images/results.png'),

								window_Open( // on click open window
											'survey_results',
											730,
											$this->getLanguageConstant('title_results'),
											true, true,
											backend_UrlMake($this->name, 'results')
										),
								$level=5
							));

			$survey_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_types'),
								url_GetFromFilePath($this->path.'images/types.png'),

								window_Open( // on click open window
											'survey_types',
											350,
											$this->getLanguageConstant('title_types'),
											true, true,
											backend_UrlMake($this->name, 'types')
										),
								$level=5
							));

			$backend->addMenu($this->name, $survey_menu);
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

		// global control action
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

		// backend control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'results':
					break;

				case 'types':
				default:
					$this->showTypes();
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db_active, $db;

		$list = MainLanguageHandler::getInstance()->getLanguages(false);

		$sql = "CREATE TABLE IF NOT EXISTS `survey_entries` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`type` int(11) NOT NULL DEFAULT 0,
					`address` varchar(50) NOT NULL,
					`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (`id`),
					KEY `type` (`type`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
		if ($db_active == 1) $db->query($sql);

		$sql = "CREATE TABLE IF NOT EXISTS `survey_types` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`name` varchar(30) NOT NULL,
					`fields` varchar(255) NOT NULL,
					PRIMARY KEY (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
		if ($db_active == 1) $db->query($sql);

		$sql = "CREATE TABLE IF NOT EXISTS `survey_entry_data` (
					`entry` int(11) NOT NULL,
					`name` varchar(30) NOT NULL,
					`value` varchar(255) NOT NULL,
					KEY `entry` (`entry`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db_active, $db;

		$sql = "DROP TABLE IF EXISTS `survey_entries`, `survey_entry_data`, `survey_types`;";
		if ($db_active == 1) $db->query($sql);
	}
	
	/**
	 * Show survey types form
	 */
	private function showTypes() {
		$template = new TemplateHandler('types_list.xml', $this->path.'templates/');

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('new'),
										'survey_types_new', 350,
										$this->getLanguageConstant('title_types_new'),
										true, false,
										$this->name,
										'types_new'
									),
					);

		$template->registerTagHandler('_types_list', &$this, 'tag_TypesList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
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
		$allow_only_one = false;

		if ($allow_only_one) {
			// get existing entry from database 
			$entry = $manager->getSingleItem(
									$manager->getFieldNames(),
									array('address' => $_SERVER['REMOTE_ADDR'])
								);

			// if entry doesn't exist, create new one
			if (!is_object($entry)) {
				$manager->insertData(array(
								'type'		=> isset($tag_params['type']) ? fix_id($tag_params['type']) : 0,
								'address'	=> $_SERVER['REMOTE_ADDR']
							));
				$id = $manager->getInsertedID();
				$entry = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));
			}

		} else {
			// create new entry anyway
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
		$allow_only_one = false;

		if ($allow_only_one) {
			// get existing entry from database 
			$entry = $manager->getSingleItem(
									$manager->getFieldNames(),
									array('address' => $_SERVER['REMOTE_ADDR'])
								);

			// if entry doesn't exist, create new one
			if (!is_object($entry)) {
				$manager->insertData(array(
								'type'		=> isset($_REQUEST['type']) ? fix_id($_REQUEST['type']) : 0,
								'address'	=> $_SERVER['REMOTE_ADDR']
							));
				$id = $manager->getInsertedID();
				$entry = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));
			}

		} else {
			// create new entry anyway
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

		print json_encode(true);
	}

	/**
	 * Handle drawing types tag
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_TypesList($tag_params, $children) {
	}
}
