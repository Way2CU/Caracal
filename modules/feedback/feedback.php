<?php

/**
 * Feedback Module
 *
 * This module is used for storing and managing user generated feedback.
 * It provides a simple storage structure coupled with management functions.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;

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
		if (ModuleHandler::is_loaded('backend')) {
			$backend = backend::get_instance();

			$feedback_menu = new backend_MenuItem(
					$this->get_language_constant('menu_feedback'),
					URL::from_file_path($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);

			$feedback_menu->addChild('', new backend_MenuItem(
								$this->get_language_constant('menu_feedback_show'),
								URL::from_file_path($this->path.'images/feedback_list.svg'),

								window_Open( // on click open window
											'feedback_manage',
											730,
											$this->get_language_constant('title_feedback_show'),
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
	public function on_init() {
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
	public function on_disable() {
		global $db;

		$tables = array('feedback');
		$db->drop_tables($tables);
	}

	/**
	 * Show content of feedback form in backend.
	 */
	private function showFeedback() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:list', $this, 'tag_FeedbackList');

		$params = array();

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save feedback from AJAX request.
	 */
	private function json_SaveFeedback() {
		$manager = FeedbackManager::get_instance();
		$user = $_SESSION['logged'] ? $user = $_SESSION['uid'] : null;
		$message = fix_chars($_REQUEST['message']);
		$url = $_SERVER['QUERY_STRING'];

		$manager->insert_item(array(
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
		$manager = FeedbackManager::get_instance();
		$user_manager = UserManager::get_instance();
		$conditions = array();

		// load template
		$template = $this->load_template($tag_params, 'list_item.xml');
		$template->set_template_params_from_array($children);

		// get items from the database
		$items = $manager->get_items($manager->get_field_names(), $conditions);

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				$timestamp = strtotime($item->timestamp);
				$date = date($this->get_language_constant('format_date_short'), $timestamp);
				$time = date($this->get_language_constant('format_time_short'), $timestamp);
				$user = $user_manager->get_single_item(array('fullname'), array('id' => $item->user));
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
						'item_change'	=> URL::make_hyperlink(
											$this->get_language_constant('change'),
											window_Open(
												'feedback_change', 	// window id
												430,				// width
												$this->get_language_constant('title_feedback_change'), // title
												false, false,
												URL::make_query(
													'backend_module',
													'transfer_control',
													array('module', $this->name),
													array('backend_action', 'feedback_change'),
													array('id', $item->id)
												)
											)
										),
						'item_delete'	=> URL::make_hyperlink(
											$this->get_language_constant('delete'),
											window_Open(
												'feedback_delete', 	// window id
												400,				// width
												$this->get_language_constant('title_feedback_delete'), // title
												false, false,
												URL::make_query(
													'backend_module',
													'transfer_control',
													array('module', $this->name),
													array('backend_action', 'feedback_delete'),
													array('id', $item->id)
												)
											)
										)
					);

				$template->set_local_params($params);
				$template->restore_xml();
				$template->parse();
			}
	}
}
