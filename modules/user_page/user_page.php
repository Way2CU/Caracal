<?php

/**
 * User Page Module
 *
 * @author MeanEYE.rcf
 */

require_once('units/userpage_manager.php');

class user_page extends Module {
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

			$user_page_menu = new backend_MenuItem(
								$this->getLanguageConstant('menu_user_pages'),
								url_GetFromFilePath($this->path.'images/icon.png'),
								'javascript:void(0);',
								$level=5
							);

			$user_page_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_create_page'),
								url_GetFromFilePath($this->path.'images/create.png'),
								window_Open( // on click open window
											'user_pages_create',
											730,
											$this->getLanguageConstant('title_create_page'),
											true, true,
											backend_UrlMake($this->name, 'create_page')
										),
								$level=5
							));
			$user_page_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_manage_pages'),
								url_GetFromFilePath($this->path.'images/manage.png'),
								window_Open( // on click open window
											'user_pages',
											650,
											$this->getLanguageConstant('title_manage_pages'),
											true, true,
											backend_UrlMake($this->name, 'pages')
										),
								$level=5
							));

			$backend->addMenu($this->name, $user_page_menu);
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
				case 'show':
					$this->tag_Page($params, $children);
					break;

				case 'show_list':
					$this->tag_PageList($params, $children);
					break;

				case 'show_video':
				case 'show_gallery':
				case 'show_download':
				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'pages':
					$this->showPages();
					break;

				case 'create_page':
					$this->createPage();
					break;

				case 'edit_page':
					$this->editPage();
					break;

				case 'save_page':
					$this->savePage();
					break;

				case 'delete_page':
					$this->deletePage();
					break;

				case 'delete_page_commit':
					$this->deletePage_Commit();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db_active, $db;

		$list = MainLanguageHandler::getInstance()->getLanguages(false);

		// User pages
		$sql = "
			CREATE TABLE `user_pages` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`author` int(11) NOT NULL,
				`owner` int(11) NOT NULL,";

		foreach($list as $language)
			$sql .= "`title_{$language}` VARCHAR( 255 ) NOT NULL DEFAULT '',";

		foreach($list as $language)
			$sql .= "`content_{$language}` TEXT NOT NULL ,";

		$sql .= "`editable` BOOLEAN NOT NULL DEFAULT '1',
				`visible` BOOLEAN NOT NULL DEFAULT '1',
				PRIMARY KEY ( `id` ),
				KEY `author` (`author`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db_active, $db;

		$sql = "DROP TABLE IF EXISTS `user_pages`;";
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Show user pages list
	 */
	private function showPages() {
		$template = new TemplateHandler('page_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
				'link_new'	=> window_OpenHyperlink(
										$this->getLanguageConstant('create'),
										'user_pages_create',
										570,
										$this->getLanguageConstant('title_create_page'),
										true, true,
										$this->name,
										'create_page'
									)
			);

 		$template->registerTagHandler('_page_list', &$this, 'tag_PageList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for creating pages
	 */
	private function createPage() {
		$template = new TemplateHandler('create_page.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		// add tag handler from user manager
		$user_manager = UserManager::getInstance();
		$template->registerTagHandler('_user_list', &$user_manager, 'tag_UserList');

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'save_page'),
					'cancel_action'	=> window_Close('user_pages_create')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for editing pages
	 */
	private function editPage() {
		$id = fix_id($_REQUEST['id']);
		$manager = UserPageManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('edit_page.xml', $this->path.'templates/');

			// add tag handler from user manager
			$user_manager = UserManager::getInstance();
			$template->registerTagHandler('_user_list', &$user_manager, 'tag_UserList');

			$params = array(
						'id'			=> $item->id,
						'owner'			=> $item->owner,
						'title'			=> unfix_chars($item->title),
						'content'		=> $item->content,
						'editable' 		=> $item->editable,
						'visible' 		=> $item->visible,
						'form_action'	=> backend_UrlMake($this->name, 'save_page'),
						'cancel_action'	=> window_Close('user_pages_edit')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save new or changed page data
	 */
	private function savePage() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$title = fix_chars($this->getMultilanguageField('title'));
		$content = escape_chars($this->getMultilanguageField('content'));
		$visible = isset($_REQUEST['visible']) && ($_REQUEST['visible'] == 'on' || $_REQUEST['visible'] == '1') ? 1 : 0;

		if (isset($_REQUEST['owner']))
			$owner = fix_id($_REQUEST['owner']); else
			$owner = null;

		if (isset($_REQUEST['editable']))
			$editable = ($_REQUEST['editable'] == 'on' || $_REQUEST['editable'] == '1') ? 1 : 0; else
			$editable = null;

		$data = array(
				'title'		=> $title,
				'content'	=> $content,
				'visible'	=> $visible,
			);

		if (!is_null($owner))
			$data['owner'] = $owner;

		if (!is_null($editable))
			$data['editable'] = $editable;

		// store data
		$manager = UserPageManager::getInstance();

		if (is_null($id)) {
			$data['author'] = $_SESSION['uid'];
			$window = 'user_pages_create';

			$manager->insertData($data);

		} else {
			$window = 'user_pages_edit';

			$manager->updateData($data, array('id' => $id));
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_page_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('user_pages'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation dialog for page removal
	 */
	private function deletePage() {
	}

	/**
	 * Perform page removal
	 */
	private function deletePage_Commit() {
	}

	/**
	 * Handle tag for displaying user page
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Page($tag_params, $children) {
		$manager = UserPageManager::getInstance();
		$admin_manager = AdministratorManager::getInstance();
		$conditions = array();

		// prepare query parameters
		if (isset($tag_params['id']))
			$conditions['id'] = fix_id($tag_params['id']);

		if (isset($tag_params['owner']))
			$conditions['owner'] = fix_id($tag_params['owner']);

		// get item from database
		$page = $manager->getSingleItem($manager->getFieldNames(), $conditions);

		// create template
		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('page.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);

		// parse object
		if (is_object($page)) {
			$timestamp = strtotime($page->timestamp);
			$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
			$time = date($this->getLanguageConstant('format_time_short'), $timestamp);

			$params = array(
						'id'			=> $page->id,
						'text_id'		=> $page->text_id,
						'timestamp'		=> $page->timestamp,
						'date'			=> $date,
						'time'			=> $time,
						'title'			=> $page->title,
						'content'		=> $page->content,
						'author'		=> $admin_manager->getItemValue(
																'fullname',
																array('id' => $page->author)
															),
						'owner'			=> $admin_manager->getItemValue(
																'fullname',
																array('id' => $page->owner)
															),
						'visible'		=> $page->visible,
						'editable'		=> $page->editable,
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Handle tag for displaying user page list
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_PageList($tag_params, $children) {
		$manager = UserPageManager::getInstance();
		$admin_manager = AdministratorManager::getInstance();
		$conditions = array();

		// get items from database
		$pages = $manager->getItems($manager->getFieldNames(), $conditions);

		// create template
		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('page_list_item.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);

		// parse object
		if (count($pages) > 0)
			foreach ($pages as $page) {
				$timestamp = strtotime($page->timestamp);
				$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
				$time = date($this->getLanguageConstant('format_time_short'), $timestamp);

				$params = array(
							'id'			=> $page->id,
							'timestamp'		=> $page->timestamp,
							'date'			=> $date,
							'time'			=> $time,
							'title'			=> $page->title,
							'content'		=> $page->content,
							'author'		=> $admin_manager->getItemValue(
																	'fullname',
																	array('id' => $page->author)
																),
							'owner'			=> $admin_manager->getItemValue(
																	'fullname',
																	array('id' => $page->owner)
																),
							'visible'		=> $page->visible,
							'editable'		=> $page->editable,
							'item_change'	=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														'user_pages_edit', 	// window id
														730,				// width
														$this->getLanguageConstant('title_edit_page'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'edit_page'),
															array('id', $page->id)
														)
													)
												),
							'item_delete'	=> url_MakeHyperlink(
													$this->getLanguageConstant('delete'),
													window_Open(
														'user_pages_delete', // window id
														400,				// width
														$this->getLanguageConstant('title_delete_page'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'delete_page'),
															array('id', $page->id)
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
