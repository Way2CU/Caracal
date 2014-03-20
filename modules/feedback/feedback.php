<?php

/**
 * Feedback Module
 *
 * This module is used for storing and managing user generated feedback.
 * It provides a simple storage structure coupled with management functions.
 *
 * Author: Mladen Mijatov
 */

require_once('units/manager.php');


class feedback extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;
		
		parent::__construct(__FILE__);

		// register backend
		if (class_exists('backend')) {
			$backend = backend::getInstance();

			$feedback_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_feedback'),
					url_GetFromFilePath($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);

			$feedback_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_feedback_show'),
								url_GetFromFilePath($this->path.'images/feedback_list.svg'),

								window_Open( // on click open window
											'feedback_manage',
											730,
											$this->getLanguageConstant('title_feedback_show'),
											true, true,
											backend_UrlMake($this->name, 'show_feedback')
										),
								$level=5
							));

			$backend->addMenu($this->name, $feedback_menu);
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
				case 'save_from_ajax':
					$this->json_SaveFeedback();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'show_feedback':
					$this->showFeedback();
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
			CREATE TABLE `feedback` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`user` int(11) DEFAULT NULL,
				`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`message` TEXT NOT NULL,
				`url` VARCHAR(250) NOT NULL,
				`status` int(4) DEFAULT '0',
				PRIMARY KEY (`id`),
				INDEX (`user`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array('feedback');
		$db->drop_tables($tables);
	}

	/**
	 * Show content of feedback form in backend.
	 */
	private function showFeedback() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('cms:list', $this, 'tag_FeedbackList');

		$params = array();

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save feedback from AJAX request.
	 */
	private function json_SaveFeedback() {
		$manager = FeedbackManager::getInstance();
		$user = $_SESSION['logged'] ? $user = $_SESSION['uid'] : null;
		$message = fix_chars($_REQUEST['message']);
		$url = $_SERVER['QUERY_STRING'];

		$manager->insertData(array(
						'user'		=> $user,
						'message'	=> $message,
						'url'		=> $url
					));

		print json_encode(true);
	}

	/**
	 * Tag handler for feedback list.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_FeedbackList($tag_params, $children) {
		$manager = FeedbackManager::getInstance();
		$user_manager = UserManager::getInstance();
		$conditions = array();

		// load template
		$template = $this->loadTemplate($tag_params, 'list_item.xml');

		// get items from the database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				$timestamp = strtotime($item->timestamp);
				$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
				$time = date($this->getLanguageConstant('format_time_short'), $timestamp);
				$user = $user_manager->getSingleItem(array('fullname'), array('id' => $item->user));
				$user_name = $item->user;

				if (is_object($user))
					$user_name = $user->fullname;

				$params = array(
						'id'		=> $item->id,
						'user'		=> $item->user,
						'user_name'	=> $user_name,
						'timestamp'	=> $item->timestamp,
						'time'		=> $time,
						'date'		=> $date,
						'message'	=> $item->message,
						'url'		=> $item->url,
						'status'	=> $item->status,
						'item_change'	=> url_MakeHyperlink(
											$this->getLanguageConstant('change'),
											window_Open(
												'feedback_change', 	// window id
												430,				// width
												$this->getLanguageConstant('title_feedback_change'), // title
												false, false,
												url_Make(
													'transfer_control',
													'backend_module',
													array('module', $this->name),
													array('backend_action', 'feedback_change'),
													array('id', $item->id)
												)
											)
										),
						'item_delete'	=> url_MakeHyperlink(
											$this->getLanguageConstant('delete'),
											window_Open(
												'feedback_delete', 	// window id
												400,				// width
												$this->getLanguageConstant('title_feedback_delete'), // title
												false, false,
												url_Make(
													'transfer_control',
													'backend_module',
													array('module', $this->name),
													array('backend_action', 'feedback_delete'),
													array('id', $item->id)
												)
											)
										)
					);

				$template->setLocalParams($params);
				$template->restoreXML();
				$template->parse();
			}
	}
}
