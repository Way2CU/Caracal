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
											570,
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
				case 'edit_page':
				case 'save_page':
				case 'delete_page':
				case 'delete_page_commit':
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
	 * Handle tag for displaying user page
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	private function tag_Page($tag_params, $children) {
		$manager = UserPageManager::getInstance();
		$admin_manager = AdministratorManager::getInstance();
		$conditions = array();

		// get item from database
		$id = isset($tag_params['id']) ? fix_id($tag_params['id']) : null;

		if (!is_null($id)
			$page = $manager->getSingleItem($manager->getFieldNames(), $conditions); else
			$page = null;

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
	private function tag_PageList($tag_params, $children) {

	}
}
