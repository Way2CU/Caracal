<?php

/**
 * Activity Tracker
 *
 * Provides ability to track activity over different windows and sessions.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;

require_once('units/activity_manager.php');
require_once('units/activity_log_manager.php');


class activity_tracker extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		if ($section == 'backend' && ModuleHandler::is_loaded('backend')) {
			$backend = backend::getInstance();

			$activities_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_activities'),
					url_GetFromFilePath($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);

			$activities_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_manage'),
								url_GetFromFilePath($this->path.'images/activities.svg'),

								window_Open( // on click open window
											'activities',
											730,
											$this->getLanguageConstant('title_manage'),
											true, true,
											backend_UrlMake($this->name, 'show')
										),
								$level=5
							));
			$activities_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_log'),
								url_GetFromFilePath($this->path.'images/log.svg'),

								window_Open( // on click open window
											'activities_log',
											730,
											$this->getLanguageConstant('title_log'),
											true, true,
											backend_UrlMake($this->name, 'show_log')
										),
								$level=5
							));
			$backend->addMenu($this->name, $activities_menu);
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
				case 'keep_alive':
					$this->keepAlive();
					break;

				case 'is_alive':
					$this->isAlive();
					break;

				case 'include_scripts':
					$this->includeScripts();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'show':
					$this->showActivities();
					break;

				case 'new':
					$this->addActivity();
					break;

				case 'change':
					$this->changeActivity();
					break;

				case 'save':
					$this->saveActivity();
					break;

				case 'delete':
					$this->deleteActivity();
					break;

				case 'delete_commit':
					$this->deleteActivity_Commit();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db;

		$sql = "
			CREATE TABLE `activities` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`activity` VARCHAR(32) NOT NULL,
				`function` VARCHAR(32) NOT NULL,
				`timeout` INT NOT NULL DEFAULT '900',
				`ignore_address` BOOLEAN NOT NULL DEFAULT '0',
				PRIMARY KEY (`id`),
				KEY `index_activity_and_function` (`activity`, `function`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		$sql = "
			CREATE TABLE `activity_log` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`activity` INT NOT NULL,
				`user` INT NULL,
				`address` VARCHAR (15) NULL,
				`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `index_by_user` (`activity`, `user`),
				KEY `index_by_address` (`activity`, `user`, `address`),
				KEY `index_without_user` (`activity`, `address`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array('activities', 'activity_log');
		$db->drop_tables($tables);
	}

	/**
	 * Show activity management window.
	 */
	private function showActivities() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
						'link_new' => window_OpenHyperlink(
										$this->getLanguageConstant('new'),
										'activities_new', 370,
										$this->getLanguageConstant('title_activity_new'),
										true, false,
										$this->name,
										'new'
									),
					);

		$template->registerTagHandler('cms:list', $this, 'tag_ActivityList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show all activity logs.
	 */
	private function showLogs() {
	}

	/**
	 * Create new activity.
	 */
	private function addActivity() {
		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'save'),
					'cancel_action'	=> window_Close('activities_new')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Change existing activity.
	 */
	private function changeActivity() {
		$manager = ActivityManager::getInstance();
		$id = fix_id($_REQUEST['id']);

		$activity = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($activity)) {
			$template = new TemplateHandler('change.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
						'id'				=> $activity->id,
						'activity'			=> $activity->activity,
						'function'			=> $activity->function,
						'timeout'			=> $activity->timeout,
						'ignore_address'	=> $activity->ignore_address,
						'form_action'		=> backend_UrlMake($this->name, 'save'),
						'cancel_action'		=> window_Close('activities_change')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save new or changed activity.
	 */
	private function saveActivity() {
		$manager = ActivityManager::getInstance();
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$ignore_address = $this->getBooleanField('ignore_address') ? 1 : 0;

		// collect data
		$data = array(
				'activity'			=> fix_chars($_REQUEST['activity']),
				'function'			=> fix_chars($_REQUEST['function']),
				'timeout'			=> fix_id($_REQUEST['timeout']),
				'ignore_address'	=> $ignore_address
			);

		// update or insert new data
		if (is_null($id)) {
			$window = 'activities_new';
			$manager->insertData($data);
		} else {
			$window = 'activities_change';
			$manager->updateData($data,	array('id' => $id));
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_activity_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).';'.window_ReloadContent('activities'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation form before removing activity.
	 */
	private function deleteActivity() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = ActivityManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant('message_activity_delete'),
					'name'			=> $item->activity.' - '.$item->function,
					'yes_text'		=> $this->getLanguageConstant('delete'),
					'no_text'		=> $this->getLanguageConstant('cancel'),
					'yes_action'	=> window_LoadContent(
											'activities_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('activities_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Remove activity.
	 */
	private function deleteActivity_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ActivityManager::getInstance();
		$log_manager = ActivityLogManager::getInstance();

		$manager->deleteData(array('id' => $id));
		$log_manager->deleteData(array('activity' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_activity_deleted'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('activities_delete').';'.window_ReloadContent('activities')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Mark activity as alive.
	 *
	 * @return boolean
	 */
	public function keepAlive() {
		global $db;

		$manager = ActivityManager::getInstance();
		$log_manager = ActivityLogManager::getInstance();

		// collect data
		$result = false;
		$activity_name = isset($_REQUEST['activity']) ? fix_chars($_REQUEST['activity']) : null;
		$function_name = isset($_REQUEST['function']) ? fix_chars($_REQUEST['function']) : null;

		// get activity
		$activity = $manager->getSingleItem(
								$manager->getFieldNames(),
								array(
									'activity'	=> $activity_name,
									'function'	=> $function_name,
								));

		// prepare log conditions
		$conditions = array();

		if (is_object($activity)) {
			$conditions['activity'] = $activity->id;

			if (!$activity->ignore_address)
				$conditions['address'] = $_SERVER['REMOTE_ADDR'];
		}

		if ($_SESSION['logged'])
			$conditions['user'] = $_SESSION['uid'];

		// make sure we have enough data
		if (count($conditions) == 0)
			return $result;

		// try to get log record
		$log = $log_manager->getSingleItem($log_manager->getFieldNames(), $conditions);

		if (is_object($log)) {
			// update existing log
			$log_manager->updateData(
						array('timestamp' => $db->format_timestamp(time())),
						array('id' => $log->id)
					);

			$result = true;

		} else {
			// create new log
			$data = array(
						'activity'	=> $activity->id,
						'address' 	=> $_SERVER['REMOTE_ADDR'],
						'timestamp'	=> $db->format_timestamp(time())
					);

			if ($_SESSION['logged'])
				$data['user'] = $_SESSION['uid'];

			$log_manager->insertData($data);
			$result = true;
		}

		if (_AJAX_REQUEST)
			print json_encode($result);

		return $result;
	}

	/**
	 * Check if specified activity is alive.
	 *
	 * @return boolean
	 */
	public function isAlive() {
		global $db;

		$manager = ActivityManager::getInstance();
		$log_manager = ActivityLogManager::getInstance();

		// collect data
		$result = false;
		$activity_name = isset($_REQUEST['activity']) ? fix_chars($_REQUEST['activity']) : null;
		$function_name = isset($_REQUEST['function']) ? fix_chars($_REQUEST['function']) : null;

		// get activity
		$activity = $manager->getSingleItem(
								$manager->getFieldNames(),
								array(
									'activity'	=> $activity_name,
									'function'	=> $function_name,
								));

		// prepare log conditions
		$conditions = array();

		if (is_object($activity)) {
			$conditions['activity'] = $activity->id;
			$conditions['timestamp'] = array(
								'operator'	=> '>=',
								'value'		=> $db->format_timestamp(time() - $activity->timeout)
							);

			if (!$activity->ignore_address)
				$conditions['address'] = $_SERVER['REMOTE_ADDR'];
		}

		if ($_SESSION['logged'])
			$conditions['user'] = $_SESSION['uid'];

		// get logs from database
		$logs = $log_manager->getItems(array('id'), $conditions);
		$result = count($logs) > 0;

		// show result
		if (_AJAX_REQUEST)
			print json_encode($result);

		return $result;
	}

	/**
	 * Include beacon JavaScript.
	 */
	private function includeScripts() {
		if (!ModuleHandler::is_loaded('head_tag'))
			return;

		$head_tag = head_tag::getInstance();
		$head_tag->addTag(
					'script',
					array(
						'src'	=> url_GetFromFilePath($this->path.'include/beacon.js'),
						'type'	=> 'text/javascript'
					));
	}

	/**
	 * Handle drawing list of activities.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ActivityList($tag_params, $children) {
		$manager = ActivityManager::getInstance();
		$conditions = array();

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// load template
		$template = $this->loadTemplate($tag_params, 'list_item.xml');
		$template->setTemplateParamsFromArray($children);

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
						'id'					=> $item->id,
						'activity'				=> $item->activity,
						'function'				=> $item->function,
						'timeout'				=> $item->timeout,
						'ignore_address'		=> $item->ignore_address,
						'ignore_address_char'	=> $item->ignore_address ? CHAR_CHECKED : CHAR_UNCHECKED,
						'item_change'			=> url_MakeHyperlink(
												$this->getLanguageConstant('change'),
												window_Open(
													'activities_change', 	// window id
													400,				// width
													$this->getLanguageConstant('title_activity_change'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'change'),
														array('id', $item->id)
													)
												)
											),
						'item_delete'			=> url_MakeHyperlink(
												$this->getLanguageConstant('delete'),
												window_Open(
													'activities_delete', 	// window id
													400,				// width
													$this->getLanguageConstant('title_activity_delete'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'delete'),
														array('id', $item->id)
													)
												)
											),
					);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}
}

