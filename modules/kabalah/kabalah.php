<?php

/**
 * KABALAH MODULE
 *
 * @author MeanEYE.rcf (meaneye.rcf@gmail.com)
 * @copyright RCF Group, 2010.
 */

class kabalah extends Module {
	/**
	 * Field types
	 * @var array
	 */
	var $field_types = array(
							'0'	=> 'field_type_text',
							'1'	=> 'field_type_date',
							'2'	=> 'field_type_number'
						);

	/**
	 * Constructor
	 *
	 * @return kabalah
	 */
	function __construct() {
		$this->file = __FILE__;
		parent::__construct();
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param string $action
	 * @param integer $level
	 */
	function transferControl($level, $params = array(), $children = array()) {
		// global control actions
		switch ($params['action']) {
			case 'view':
				// print normal HTML page with
				$this->generateAnswerHTML();
				break;

			default:
				break;
		}

		// global control actions
		switch ($params['backend_action']) {
			case 'questions':
				$this->printQuestionsForm($level);
				break;

			case 'questions_new':
				$this->newQuestion($level);
				break;

			case 'questions_change':
				$this->changeQuestion($level);
				break;

			case 'questions_save':
				$this->saveQuestion($level);
				break;

			case 'questions_delete':
				$this->deleteQuestion($level);
				break;

			case 'questions_delete_commit':
				$this->deleteQuestionCommit($level);
				break;

			case 'question_xml':
				$this->generateQuestionXML();
				break;

			case 'question_list_xml':
				$this->generateQuestionListXML();
				break;

			// ------

			case 'fields':
				$this->printFieldsForm($level);
				break;

			case 'fields_new':
				$this->newField($level);
				break;

			case 'fields_change':
				$this->changeField($level);
				break;

			case 'fields_save':
				$this->saveField($level);
				break;

			case 'fields_delete':
				$this->deleteField($level);
				break;

			case 'fields_delete_commit':
				$this->deleteFieldCommit($level);
				break;

			// ------

			case 'answers':
				$this->printAnswersForm($level);
				break;

			case 'answers_new':
				$this->newAnswer($level);
				break;

			case 'answers_change':
				$this->changeAnswer($level);
				break;

			case 'answers_save':
				$this->saveAnswer($level);
				break;

			case 'answers_delete':
				$this->deleteAnswer($level);
				break;

			case 'answers_delete_commit':
				$this->deleteAnswerCommit($level);
				break;

			case 'answer_xml':
				$this->generateAnswerXML();
				break;

			default:
				break;
		}

	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler;

		// include functions file
		require_once($this->path.'units/shared_functions.php');

		// load module style and scripts
		if ($ModuleHandler->moduleExists('head_tag')) {
			$head_tag = $ModuleHandler->getObjectFromName('head_tag');
			//$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/kabalah.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/kabalah.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if ($ModuleHandler->moduleExists('backend')) {
			$backend = $ModuleHandler->getObjectFromName('backend');

			$window_questions = window_Open(
								$this->name.'_questions', 600,
								$this->getLanguageConstant('title_questions'),
								true, true,
								$this->name,
								'questions'
							);

			$window_answers = window_Open(
								$this->name.'_answers',	630,
								$this->getLanguageConstant('title_answers'),
								true, true,
								$this->name,
								'answers'
							);

			$menu = new backend_MenuItem($this->getLanguageConstant('menu_title'), '', $window_questions,	$level=0);

			$menu->addChild('', new backend_MenuItem($this->getLanguageConstant('menu_questions'), '', $window_questions, $level=0));
			$menu->addChild('', new backend_MenuItem($this->getLanguageConstant('menu_answers'), '', $window_answers, $level=0));

			$backend->addMenu($this->name, $menu);
		}

	}

	/**
	 * Display list of qestions
	 *
	 * @param integer $level
	 */
	function printQuestionsForm($level) {
		$template = new TemplateHandler('questions_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
						'link_new'	=> window_OpenHyperlink(
											$this->getLanguageConstant('new'),
											$this->name.'_questions_new', 400,
											$this->getLanguageConstant('title_questions_new'),
											true, false,
											$this->name,
											'questions_new'
										)
									);

		$template->registerTagHandler('_kabalah_questions', &$this, 'printQuestions');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Custom tag handler
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	function printQuestions($level, $params, $children) {
		$manager = new QuestionManager();
		$results = $manager->getItems(
								array('id', 'pid', 'title'),
								array(),
								array('pid')
							);
		$result_number = 0;

		$template = new TemplateHandler(isset($params['template']) ? $params['template'] : 'question.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		if (count($results) > 0)
		foreach($results as $result) {
			$result_number++;

			$params = array(
						'number'		=> $result_number,
						'id'			=> $result->id,
						'pid'			=> $result->pid,
						'title'			=> $result->title,
						'selected'		=> isset($params['selected']) ? $params['selected'] : '',
						'item_fields'	=> url_MakeHyperlink(
												$this->getLanguageConstant('fields'),
												window_Open(
													$this->name.'_fields', 	// window id
													450,					// width
													$this->getLanguageConstant('title_fields').$result->title, // title
													'false', 'false',
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'fields'),
														array('id', $result->pid)
													)
												)
											),
						'item_change'	=> url_MakeHyperlink(
												$this->getLanguageConstant('change'),
												window_Open(
													$this->name.'_questions_change', 	// window id
													400,					// width
													$this->getLanguageConstant('title_questions_change'), // title
													'false', 'false',
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'questions_change'),
														array('id', $result->id)
													)
												)
											),
						'item_delete'	=> url_MakeHyperlink(
												$this->getLanguageConstant('delete'),
												window_Open(
													$this->name.'_questions_delete', 	// window id
													300,					// width
													$this->getLanguageConstant('title_questions_delete'), // title
													'false', 'false',
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'questions_delete'),
														array('id', $result->id)
													)
												)
											),
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}

	/**
	 * Display form for creating a new questions
	 *
	 * @param integer $level
	 */
	function newQuestion($level) {
		$template = new TemplateHandler('questions_new.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'questions_save'),
					'cancel_action'	=> window_Close($this->name.'_questions_new')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Display form for changing a question
	 *
	 * @param integer $level
	 */
	function changeQuestion($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$obj = new QuestionManager();

		$question = $obj->getSingleItem(array('id', 'pid', 'title', 'description', 'formula'), array('id' => $id));

		$template = new TemplateHandler('questions_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $question->id,
					'pid'			=> $question->pid,
					'title'			=> unfix_chars($question->title),
					'description'	=> unfix_chars($question->description),
					'formula'		=> unfix_chars($question->formula),
					'form_action'	=> backend_UrlMake($this->name, 'questions_save'),
					'cancel_action'	=> window_Close($this->name.'_questions_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Save modified or newly created question
	 *
	 * @param integer $level
	 */
	function saveQuestion($level) {
		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;
		$pid = fix_id(fix_chars($_REQUEST['pid']));
		$title = fix_chars($_REQUEST['title']);
		$description = fix_chars($_REQUEST['description']);
		$formula = ($_REQUEST['formula']);

		$obj = new QuestionManager();
		$data = array(
					'pid'			=> $pid,
					'title'			=> $title,
					'description'	=> $description,
					'formula'		=> $formula
				);

		if (is_null($id))
			$obj->insertData($data); else
			$obj->updateData($data, array('id'	=> $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$window_name = $this->name.(is_null($id) ? '_questions_new' : '_questions_change');
		$params = array(
					'message'		=> $this->getLanguageConstant('message_question_saved'),
					'action'		=> window_Close($window_name).";".window_ReloadContent('kabalah_questions')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Delete specified question
	 *
	 * @param integer $level
	 */
	function deleteQuestion($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$obj = new QuestionManager();

		$question = $obj->getSingleItem(array('title'), array('id' => $id));

		$template = new TemplateHandler('questions_delete.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'title'			=> $question->title,
					'yes_action'	=> window_LoadContent(
											$this->name.'_questions_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'questions_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'	=> window_Close($this->name.'_questions_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Actually delete question
	 *
	 * @param integer $level
	 */
	function deleteQuestionCommit($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$obj = new QuestionManager();
		$answers = new AnswerManager();
		$fields = new FieldManager();

		$question = $obj->getSingleItem(array('pid'), array('id' => $id));
		$pid = $question->pid;

		// delete data
		$obj->deleteData(array('id'	=> $id));
		$answers->deleteData(array('question' => $pid));
		$fields->deleteData(array('question' => $pid));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$window_name = $this->name.'_questions_delete';
		$params = array(
					'message'		=> $this->getLanguageConstant('message_question_deleted'),
					'action'		=> window_Close($window_name).";".window_ReloadContent('kabalah_questions').";".
										window_ReloadContent('kabalah_answers').";".window_ReloadContent('kabalah_fields')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Draw answers list
	 *
	 * @param integer $level
	 */
	function printAnswersForm($level) {
		$template = new TemplateHandler('answers_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
						'link_new'	=> window_OpenHyperlink(
											$this->getLanguageConstant('new'),
											$this->name.'_answers_new', 400,
											$this->getLanguageConstant('title_answers_new'),
											true, false,
											$this->name,
											'answers_new'
										)
									);

		$template->registerTagHandler('_kabalah_answers', &$this, 'printAnswers');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Custom tag handler
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	function printAnswers($level, $params, $children) {
		$manager = new AnswerManager();
		$results = $manager->getItems(
								array(
									'id', 'question', 'number', 'title',
									'short_description', 'description'
								),
								array(),
								array('question', 'number')
							);
		$result_number = 0;

		$template = new TemplateHandler(isset($params['template']) ? $params['template'] : 'answer.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		if (count($results) > 0)
		foreach($results as $result) {
			$result_number++;

			$params = array(
						'number'			=> $result_number,
						'question'			=> $result->question,
						'answer'			=> $result->number,
						'title'				=> $result->title,
						'short_description'	=> $result->short_description,
						'description'		=> $result->description,
						'item_change'		=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														$this->name.'_answers_change', 	// window id
														400,							// width
														$this->getLanguageConstant('title_answers_change'), // title
														'false', 'false',
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'answers_change'),
															array('id', $result->id)
														)
													)
												),
						'item_delete'		=> url_MakeHyperlink(
													$this->getLanguageConstant('delete'),
													window_Open(
														$this->name.'_answers_delete', 	// window id
														300,							// width
														$this->getLanguageConstant('title_answers_delete'), // title
														'false', 'false',
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'answers_delete'),
															array('id', $result->id)
														)
													)
												),
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}

	/**
	 * Display form for creating a new questions
	 *
	 * @param integer $level
	 */
	function newAnswer($level) {
		$template = new TemplateHandler('answers_new.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'answers_save'),
					'cancel_action'	=> window_Close($this->name.'_answers_new')
				);

		$template->registerTagHandler('_kabalah_questions', &$this, 'printQuestions');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Display form for changing answer data
	 *
	 * @param integer $level
	 */
	function changeAnswer($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$obj = new AnswerManager();

		$answer = $obj->getSingleItem(
								array('id', 'number', 'question', 'title', 'short_description', 'description'),
								array('id' => $id)
							);

		$template = new TemplateHandler('answers_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'				=> $answer->id,
					'answer'			=> $answer->number,
					'question'			=> $answer->question,
					'title'				=> unfix_chars($answer->title),
					'short_description'	=> unfix_chars($answer->short_description),
					'description'		=> unfix_chars($answer->description),
					'form_action'		=> backend_UrlMake($this->name, 'answers_save'),
					'cancel_action'		=> window_Close($this->name.'_answers_change')
				);

		$template->registerTagHandler('_kabalah_questions', &$this, 'printQuestions');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Save modified or newly created answer
	 *
	 * @param integer $level
	 */
	function saveAnswer($level) {
		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;
		$number = fix_id(fix_chars($_REQUEST['number']));
		$question = fix_id(fix_chars($_REQUEST['question']));
		$title = fix_chars($_REQUEST['title']);
		$short_description = fix_chars($_REQUEST['short_description']);
		$description = fix_chars($_REQUEST['description']);

		$obj = new AnswerManager();
		$data = array(
					'number'			=> $number,
					'question'			=> $question,
					'title'				=> $title,
					'short_description'	=> $short_description,
					'description'		=> $description
				);

		if (is_null($id))
			$obj->insertData($data); else
			$obj->updateData($data, array('id'	=> $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$window_name = $this->name.(is_null($id) ? '_answers_new' : '_answers_change');
		$params = array(
					'message'		=> $this->getLanguageConstant('message_answer_saved'),
					'action'		=> window_Close($window_name).";".window_ReloadContent('kabalah_answers')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Delete specified answer
	 *
	 * @param integer $level
	 */
	function deleteAnswer($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$obj = new AnswerManager();

		$answer = $obj->getSingleItem(array('title'), array('id' => $id));

		$template = new TemplateHandler('answers_delete.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'title'			=> $answer->title,
					'yes_action'	=> window_LoadContent(
											$this->name.'_answers_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'answers_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'	=> window_Close($this->name.'_answers_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Actually delete question
	 *
	 * @param integer $level
	 */
	function deleteAnswerCommit($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$obj = new AnswerManager();

		$obj->deleteData(array('id'	=> $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$window_name = $this->name.'_answers_delete';
		$params = array(
					'message'		=> $this->getLanguageConstant('message_answer_deleted'),
					'action'		=> window_Close($window_name).";".window_ReloadContent('kabalah_answers')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Display list of fields
	 *
	 * @param integer $level
	 */
	function printFieldsForm($level) {
		$question = fix_id(fix_chars($_REQUEST['id']));

		$template = new TemplateHandler('fields_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
						'link_new'	=> url_MakeHyperlink(
										$this->getLanguageConstant('new'),
										window_Open(
											$this->name.'_fields_new',
											400,
											$this->getLanguageConstant('title_fields_new'),
											true, false,
											url_Make(
												'transfer_control',
												_BACKEND_SECTION_,
												array('backend_action', 'fields_new'),
												array('module', $this->name),
												array('question', $question)
											)
										),
										$this->getLanguageConstant('new')
									),
						'close_action'	=> window_Close($this->name.'_fields')
					);

		$template->registerTagHandler('_kabalah_fields', &$this, 'printFields');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Custom tag handler
	 *
	 * @param integer $level
	 * @param array $param
	 * @param array $children
	 */
	function printFields($level, $params, $children) {
		$question = fix_id(fix_chars($_REQUEST['id']));

		$manager = new FieldManager();
		$results = $manager->getItems(
									array('id', 'question', 'type', 'name', 'label', 'default', 'order'),
									array('question' => $question),
									array('order')
								);
		$result_number = 0;

		$template = new TemplateHandler(
							isset($params['template']) ? $params['template'] : 'field.xml',
							$this->path.'templates/'
						);

		$template->setMappedModule($this->name);

		if (count($results) > 0)
		foreach($results as $result) {
			$result_number++;

			$params = array(
						'number'			=> $result_number,
						'id'				=> $result->id,
						'question'			=> $result->question,
						'type'				=> $result->type,
						'type_text'			=> $this->getLanguageConstant($this->field_types[$result->type]),
						'name'				=> $result->name,
						'label'				=> $result->label,
						'default'			=> $result->default,
						'order'				=> $result->order,
						'item_change'		=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														$this->name.'_fields_change', 	// window id
														400,							// width
														$this->getLanguageConstant('title_fields_change'), // title
														'false', 'false',
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'fields_change'),
															array('id', $result->id)
														)
													)
												),
						'item_delete'		=> url_MakeHyperlink(
													$this->getLanguageConstant('delete'),
													window_Open(
														$this->name.'_fields_delete', 	// window id
														300,							// width
														$this->getLanguageConstant('title_fields_delete'), // title
														'false', 'false',
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'fields_delete'),
															array('id', $result->id)
														)
													)
												),

					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}

	/**
	 * Handle printing field types
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	function printFieldTypes($level, $params, $children) {
		$template = new TemplateHandler(isset($params['template']) ? $params['template'] : 'field_type.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		foreach($this->field_types as $number=>$constant) {
			$params = array(
						'number'			=> $number,
						'title'				=> $this->getLanguageConstant($constant)
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}

	/**
	 * Display form for creating a new field
	 *
	 * @param integer $level
	 */
	function newField($level) {
		$question = fix_id(fix_chars($_REQUEST['question']));

		$template = new TemplateHandler('fields_new.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'question'		=> $question,
					'form_action'	=> backend_UrlMake($this->name, 'fields_save'),
					'cancel_action'	=> window_Close($this->name.'_fields_new')
				);

		$template->registerTagHandler('_kabalah_field_types', &$this, 'printFieldTypes');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Display form for changing field data
	 *
	 * @param integer $level
	 */
	function changeField($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$obj = new FieldManager();

		$answer = $obj->getSingleItem(
								array('id', 'question', 'type', 'name', 'label', 'default', 'order'),
								array('id' => $id)
							);

		$template = new TemplateHandler('fields_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'				=> $answer->id,
					'question'			=> $answer->question,
					'type'				=> $answer->type,
					'name'				=> unfix_chars($answer->name),
					'label'				=> unfix_chars($answer->label),
					'default'			=> unfix_chars($answer->default),
					'order'				=> $answer->order,
					'form_action'		=> backend_UrlMake($this->name, 'fields_save'),
					'cancel_action'		=> window_Close($this->name.'_fields_change')
				);

		$template->registerTagHandler('_kabalah_field_types', &$this, 'printFieldTypes');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Save modified or newly created field
	 *
	 * @param integer $level
	 */
	function saveField($level) {
		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;
		$question = fix_id(fix_chars($_REQUEST['question']));
		$type = fix_id(fix_chars($_REQUEST['type']));
		$name = fix_chars($_REQUEST['name']);
		$label = fix_chars($_REQUEST['label']);
		$default = fix_chars($_REQUEST['default']);
		$order = isset($_REQUEST['order']) ? fix_id(fix_chars($_REQUEST['order'])) : 0;

		$obj = new FieldManager();
		$data = array(
					'question'			=> $question,
					'type'				=> $type,
					'name'				=> $name,
					'label'				=> $label,
					'default'			=> $default,
					'order'				=> $order,
				);

		if (is_null($id))
			$obj->insertData($data); else
			$obj->updateData($data, array('id'	=> $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$window_name = $this->name.(is_null($id) ? '_fields_new' : '_fields_change');
		$params = array(
					'message'		=> $this->getLanguageConstant('message_field_saved'),
					'action'		=> window_Close($window_name).";".window_ReloadContent('kabalah_fields')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Delete specified field
	 *
	 * @param integer $level
	 */
	function deleteField($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$obj = new FieldManager();

		$field = $obj->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('fields_delete.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'title'			=> $field->name,
					'yes_action'	=> window_LoadContent(
											$this->name.'_fields_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'fields_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'	=> window_Close($this->name.'_fields_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Actually delete field
	 *
	 * @param integer $level
	 */
	function deleteFieldCommit($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$obj = new FieldManager();

		$obj->deleteData(array('id'	=> $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$window_name = $this->name.'_fields_delete';
		$params = array(
					'message'		=> $this->getLanguageConstant('message_field_deleted'),
					'action'		=> window_Close($window_name).";".window_ReloadContent('kabalah_fields')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Generate and print out XML for specified question
	 */
	function generateQuestionXML() {
		$question = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;

		// no question was specified, print error XML and return
		if (is_null($question)) {
			$this->generateErrorXML();
			return;
		}

		$manager = new QuestionManager();
		$question = $manager->getSingleItem(
									array('title', 'description'),
									array('pid' => $question)
								);

		$template = new TemplateHandler('question_output.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'title'			=> unfix_chars($question->title),
					'description'	=> unfix_chars($question->description)
				);

		$template->registerTagHandler('_kabalah_fields', &$this, 'printFields');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse(0);
	}

	/**
	 * Generate list of questions.
	 */
	function generateQuestionListXML() {
		$template = new TemplateHandler('question_list_output.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array();

		$template->registerTagHandler('_kabalah_questions', &$this, 'printQuestions');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse(0);
	}

	/**
	 * Calculate and generate XML based on short answer description
	 */
	function generateAnswerXML() {
		$question = fix_id(fix_chars($_REQUEST['question']));
		$field_manager = new FieldManager();
		$answer_manager = new AnswerManager();

		// get raw data from flash in XML
		$parser = new XMLParser(file_get_contents("php://input"), "");
		$parser->parse();
		$data = array();

		// extract the values we need
		foreach($parser->document->tagChildren as $field)
			$data[$field->tagName] = $field->tagData;

		// get database defined fields
		$fields = $field_manager->getItems(
										array('name', 'type', 'default'),
										array('question' => $question),
										array('order')
									);

		$field_data = array();

		// this way we allow only certain fields to enter evaluation
		foreach($fields as $field) {
			// if field is not specified use default value
			$value = array_key_exists($field->name, $data) ?
										fix_chars($data[$field->name]) :
										$field->default;

			// ensure strict field data types
			switch ($field->type) {
				case 2:
					$field_data[$field->name] = fix_id($value);
					break;

				default:
					$field_data[$field->name] = "'{$value}'";
					break;
			}
		}

		$answer_number = $this->calculateAnswer($question, $field_data);
		$answer = $answer_manager->getSingleItem(
										array('id', 'title', 'short_description'),
										array(
											'question'	=> $question,
											'number'	=> $answer_number
										)
									);

		if (is_object($answer)) {
			// use template to display the result
			$template = new TemplateHandler('answer_output.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
						'debug'				=> $answer_number,
						'title'				=> unfix_chars($answer->title),
						'short_description'	=> unfix_chars($answer->short_description),
						'link'				=> url_Make(
												'show',
												'answers',
												array('question', $question),
												array('answer', $answer_number)
											)
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse(0);
		} else {
			// handle error with calcultion
			$this->generateErrorXML("Unhandled result: {$answer_number}");
		}
	}

	/**
	 * Generates HTML page containing answer.
	 */
	function generateAnswerHTML() {
		$question = fix_id(fix_chars($_REQUEST['question']));
		$answer_number = fix_id(fix_chars($_REQUEST['answer']));

		$answer_manager = new AnswerManager();
		$answer = $answer_manager->getSingleItem(
											array('id', 'title', 'description'),
											array(
												'question'	=> $question,
												'number'	=> $answer_number
											)
										);
		if (is_object($answer)) {
			// use template to display the result
			$template = new TemplateHandler('answer_output_html.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
						'debug'				=> $answer_number,
						'title'				=> unfix_chars($answer->title),
						'short_description'	=> unfix_chars($answer->short_description),
						'link'				=> url_Make(
												'show',
												'answers',
												array('question', $question),
												array('answer', $answer_number)
											)
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse(0);
		}
	}

	/**
	 * Generates XML that provides error message to the flash application
	 */
	function generateErrorXML($message="") {
		$template = new TemplateHandler('message_output.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $message,
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse(0);
	}

	/**
	 * Calculate answer number based on question and field data
	 *
	 * @param $question_number
	 * @param $field_data
	 * @return integer
	 */
	function calculateAnswer($question_number, $field_data) {
		$result = 0;

		$manager = new QuestionManager();
		$question = $manager->getSingleItem(array('formula'), array('pid' => $question_number));

		$data = $question->formula;

		foreach($field_data as $field => $value) {
			$data = str_replace('$'.$field, $value, $data);
		}

		$result = eval($data);

		return $result;
	}
}

class QuestionManager extends ItemManager {

	function __construct() {
		parent::__construct('questions');

		$this->addProperty('id', 'int');
		$this->addProperty('pid', 'int');
		$this->addProperty('title', 'varchar');
		$this->addProperty('description', 'text');
		$this->addProperty('formula', 'text');
	}
}

class FieldManager extends ItemManager {

	function __construct() {
		parent::__construct('fields');

		$this->addProperty('id', 'int');
		$this->addProperty('question', 'int');
		$this->addProperty('type', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('label', 'text');
		$this->addProperty('default', 'varchar');
	}
}

class AnswerManager extends ItemManager {

	function __construct() {
		parent::__construct('answers');

		$this->addProperty('id', 'int');
		$this->addProperty('question', 'int');
		$this->addProperty('number', 'int');
		$this->addProperty('title', 'varchar');
		$this->addProperty('short_description', 'text');
		$this->addProperty('description', 'text');
	}
}
