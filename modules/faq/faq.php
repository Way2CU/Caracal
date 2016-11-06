<?php

/**
 * Frequently Asked Questions Module
 * @author Mladen Mijatov
 */
use Core\Module;

require_once('units/manager.php');


class faq extends Module {
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

			$faq_menu = new backend_MenuItem(
					$this->get_language_constant('menu_faq'),
					URL::from_file_path($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=4
				);

			$faq_menu->addChild('', new backend_MenuItem(
								$this->get_language_constant('menu_manage_questions'),
								URL::from_file_path($this->path.'images/questions.svg'),

								window_Open( // on click open window
											'faq',
											700,
											$this->get_language_constant('title_questions'),
											true, true,
											backend_UrlMake($this->name, 'manage')
										),
								$level=4
							));

			$backend->addMenu($this->name, $faq_menu);
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
				case 'show':
					$this->tag_Question($params, $children);
					break;

				case 'show_list':
					$this->tag_QuestionList($params, $children);
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'add':
					$this->addQuestion();
					break;

				case 'change':
					$this->changeQuestion();
					break;

				case 'save':
					$this->saveQuestion();
					break;

				case 'delete':
					$this->deleteQuestion();
					break;

				case 'delete_commit':
					$this->deleteQuestion_Commit();
					break;

				default:
					$this->showQuestions();
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function initialize() {
		global $db;

		$list = Language::get_languages(false);

		$sql = "
			CREATE TABLE `faq` (
				`id` INT NOT NULL AUTO_INCREMENT,";

		foreach($list as $language) {
			$sql .= "`question_{$language}` TEXT NOT NULL,";
			$sql .= "`answer_{$language}` TEXT NOT NULL,";
		}

		$sql .= "
				`visible` BOOLEAN NOT NULL DEFAULT '1',
				PRIMARY KEY ( `id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
		global $db;

		$db->drop_tables(array('faq'));
	}

	/**
	 * Show list of questions for management.
	 */
	private function showQuestions() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->get_language_constant('new_question'),
										'faq_new', 500,
										$this->get_language_constant('title_question_new'),
										true, false,
										$this->name,
										'add'
									),
					);

		$template->register_tag_handler('_questions', $this, 'tag_QuestionList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for adding new question.
	 */
	private function addQuestion() {
		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'save'),
					'cancel_action'	=> window_Close('faq_new')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for changing existing question.
	 */
	private function changeQuestion() {
		$id = fix_id($_REQUEST['id']);
		$manager = QuestionManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('change.xml', $this->path.'templates/');
			$template->set_mapped_module($this->name);

			$params = array(
						'id'			=> $item->id,
						'question'		=> $item->question,
						'answer'		=> $item->answer,
						'visible' 		=> $item->visible,
						'form_action'	=> backend_UrlMake($this->name, 'save'),
						'cancel_action'	=> window_Close('faq_change')
					);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Save new or changed question.
	 */
	private function saveQuestion() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$question = $this->get_multilanguage_field('question');
		$answer = $this->get_multilanguage_field('answer');
		$visible = $this->get_boolean_field('visible') ? 1 : 0;

		$manager = QuestionManager::get_instance();

		$data = array(
				'question'	=> $question,
				'answer'	=> $answer,
				'visible'	=> $visible
			);

		if (is_null($id)) {
			$window = 'faq_new';
			$manager->insert_item($data);
		} else {
			$window = 'faq_change';
			$manager->update_items($data,	array('id' => $id));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_question_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('faq'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show confirmation form before deleting question.
	 */
	private function deleteQuestion() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = QuestionManager::get_instance();

		$item = $manager->get_single_item(array('question'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant("message_question_delete"),
					'name'			=> $item->question[$language],
					'yes_text'		=> $this->get_language_constant("delete"),
					'no_text'		=> $this->get_language_constant("cancel"),
					'yes_action'	=> window_LoadContent(
											'faq_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('faq_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Perform question removal and notify user of success.
	 */
	private function deleteQuestion_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = QuestionManager::get_instance();

		$manager->delete_items(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant("message_question_deleted"),
					'button'	=> $this->get_language_constant("close"),
					'action'	=> window_Close('faq_delete').";".window_ReloadContent('faq')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Tag handler for single question.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Question($tag_params, $children) {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = QuestionManager::get_instance();

		if (is_null($id))
			return;

		// get item from database
		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (is_object($id)) {
			$template = $this->load_template($tag_params, 'list_item.xml');
			$template->set_template_params_from_array($children);

			$params = array(
					'id'		=> $item->id,
					'question'	=> $item->question,
					'answer'	=> $item->answer,
				);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Tag handler for all questions.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_QuestionList($tag_params, $children) {
		$manager = QuestionManager::get_instance();
		$conditions = array();

		// get items from database
		$items = $manager->get_items($manager->get_field_names(), $conditions);

		// load template
		$template = $this->load_template($tag_params, 'list_item.xml');
		$template->set_template_params_from_array($children);

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
						'id'		=> $item->id,
						'question'	=> $item->question,
						'answer'	=> $item->answer,
						'item_change'	=> URL::make_hyperlink(
												$this->get_language_constant('change'),
												window_Open(
													'faq_change', 	// window id
													730,				// width
													$this->get_language_constant('title_question_change'), // title
													false, false,
													URL::make_query(
														'backend_module',
														'transfer_control',
														array('module', $this->name),
														array('backend_action', 'change'),
														array('id', $item->id)
													)
												)
											),
						'item_delete'	=> URL::make_hyperlink(
												$this->get_language_constant('delete'),
												window_Open(
													'faq_delete', 	// window id
													400,				// width
													$this->get_language_constant('title_question_delete'), // title
													false, false,
													URL::make_query(
														'backend_module',
														'transfer_control',
														array('module', $this->name),
														array('backend_action', 'delete'),
														array('id', $item->id)
													)
												)
											),
					);

				$template->restore_xml();
				$template->set_local_params($params);
				$template->parse();
			}
	}
}
