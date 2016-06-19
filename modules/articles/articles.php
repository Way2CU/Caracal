<?php

/**
 * Articles Module
 *
 * Module for managing articles. This module supports multiple languages
 * as well as many article-related properties.
 *
 * Author: Mladen Mijatov
 */
require_once('units/manager.php');
require_once('units/vote_manager.php');
require_once('units/group_manager.php');

use Core\Module;
use Core\Markdown;



final class ImageType {
	const Stars = 1;
	const Circles = 2;
}


class articles extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// register backend
		if ($section == 'backend' && ModuleHandler::is_loaded('backend')) {
			$backend = backend::getInstance();

			$articles_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_articles'),
					url_GetFromFilePath($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);

			$articles_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_articles_new'),
								url_GetFromFilePath($this->path.'images/new_article.svg'),

								window_Open( // on click open window
											'articles_new',
											730,
											$this->getLanguageConstant('title_articles_new'),
											true, true,
											backend_UrlMake($this->name, 'articles_new')
										),
								$level=5
							));
			$articles_menu->addSeparator(5);

			$articles_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_articles_manage'),
								url_GetFromFilePath($this->path.'images/manage.svg'),

								window_Open( // on click open window
											'articles',
											720,
											$this->getLanguageConstant('title_articles_manage'),
											true, true,
											backend_UrlMake($this->name, 'articles')
										),
								$level=5
							));
			$articles_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_article_groups'),
								url_GetFromFilePath($this->path.'images/groups.svg'),

								window_Open( // on click open window
											'article_groups',
											650,
											$this->getLanguageConstant('title_article_groups'),
											true, true,
											backend_UrlMake($this->name, 'groups')
										),
								$level=5
							));

			$backend->addMenu($this->name, $articles_menu);
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
	 * @param string $action
	 * @param integer $level
	 */
	public function transferControl($params, $children) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show':
					$this->tag_Article($params, $children);
					break;

				case 'show_list':
					$this->tag_ArticleList($params, $children);
					break;

				case 'show_group':
					$this->tag_Group($params, $children);
					break;

				case 'show_group_list':
					$this->tag_GroupList($params, $children);
					break;

				case 'get_rating_image':
				case 'show_rating_image':
					$this->tag_ArticleRatingImage($params, $children);
					break;

				case 'json_article':
					$this->json_Article();
					break;

				case 'json_article_list':
					$this->json_ArticleList();
					break;

				case 'json_group':
					break;

				case 'json_group_list':
					break;

				case 'json_rating_image':
					break;

				case 'json_vote':
					$this->json_Vote();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'articles':
					$this->showArticles();
					break;

				case 'articles_new':
					$this->addArticle();
					break;

				case 'articles_change':
					$this->changeArticle();
					break;

				case 'articles_save':
					$this->saveArticle();
					break;

				case 'articles_delete':
					$this->deleteArticle();
					break;

				case 'articles_delete_commit':
					$this->deleteArticle_Commit();
					break;

				// ---

				case 'groups':
					$this->showGroups();
					break;

				case 'groups_new':
					$this->addGroup();
					break;

				case 'groups_change':
					$this->changeGroup();
					break;

				case 'groups_save':
					$this->saveGroup();
					break;

				case 'groups_delete':
					$this->deleteGroup();
					break;

				case 'groups_delete_commit':
					$this->deleteGroup_Commit();
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

		$list = Language::getLanguages(false);

		$sql = "
			CREATE TABLE `articles` (
				`id` INT NOT NULL AUTO_INCREMENT ,
				`group` int(11) DEFAULT NULL ,
				`text_id` VARCHAR (32) NULL ,
				`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
			";

		foreach($list as $language) {
			$sql .= "`title_{$language}` VARCHAR( 255 ) NOT NULL DEFAULT '',";
			$sql .= "`content_{$language}` TEXT NOT NULL ,";
		}

		$sql .= "
				`author` INT NOT NULL ,
				`gallery` INT NOT NULL ,
				`visible` BOOLEAN NOT NULL DEFAULT '0',
				`views` INT NOT NULL DEFAULT '0',
				`votes_up` INT NOT NULL DEFAULT '0',
				`votes_down` INT NOT NULL DEFAULT '0',
				PRIMARY KEY ( `id` ),
				INDEX ( `author` ),
				INDEX ( `group` ),
				INDEX ( `text_id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// article groups
		$sql = "
			CREATE TABLE `article_groups` (
				`id` INT NOT NULL AUTO_INCREMENT ,
				`text_id` VARCHAR (32) NULL ,
			";

		foreach($list as $language) {
			$sql .= "`title_{$language}` VARCHAR( 255 ) NOT NULL DEFAULT '',";
			$sql .= "`description_{$language}` TEXT NOT NULL ,";
		}

		$sql .= "
				`visible` BOOLEAN NOT NULL DEFAULT '1',
				PRIMARY KEY ( `id` ),
				INDEX ( `text_id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		$sql = "CREATE TABLE `article_votes` (
					`id` INT NOT NULL AUTO_INCREMENT ,
					`address` VARCHAR( 15 ) NOT NULL ,
					`article` INT NOT NULL ,
				PRIMARY KEY (  `id` ),
				INDEX ( `address`, `article` )
				) ENGINE = MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array('articles', 'article_group', 'article_votes');
		$db->drop_tables($tables);
	}

	/**
	 * Show administration form for articles
	 */
	private function showArticles() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('new'),
										'articles_new', 730,
										$this->getLanguageConstant('title_articles_new'),
										true, false,
										$this->name,
										'articles_new'
									),
					);

		$template->registerTagHandler('cms:article_list', $this, 'tag_ArticleList');
		$template->registerTagHandler('cms:group_list', $this, 'tag_GroupList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Print input form for new article
	 */
	private function addArticle() {
		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('cms:group_list', $this, 'tag_GroupList');

		if (ModuleHandler::is_loaded('gallery')) {
			$gallery = gallery::getInstance();
			$template->registerTagHandler('cms:gallery_list', $gallery, 'tag_GroupList');
		}

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'articles_save'),
					'cancel_action'	=> window_Close('articles_new')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Display article for modification
	 */
	private function changeArticle() {
		$id = fix_id($_REQUEST['id']);
		$manager = Modules\Articles\Manager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (!is_object($item))
			return;

		$template = new TemplateHandler('change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('cms:group_list', $this, 'tag_GroupList');

		if (ModuleHandler::is_loaded('gallery')) {
			$gallery = gallery::getInstance();
			$template->registerTagHandler('cms:gallery_list', $gallery, 'tag_GroupList');
		}

		$params = array(
					'id'			=> $item->id,
					'text_id'		=> unfix_chars($item->text_id),
					'group'			=> $item->group,
					'title'			=> unfix_chars($item->title),
					'content'		=> $item->content,
					'visible' 		=> $item->visible,
					'gallery'		=> $item->gallery,
					'form_action'	=> backend_UrlMake($this->name, 'articles_save'),
					'cancel_action'	=> window_Close('articles_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save article data
	 */
	private function saveArticle() {
		$manager = Modules\Articles\Manager::getInstance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$text_id = escape_chars($_REQUEST['text_id']);
		$title = $this->getMultilanguageField('title');
		$content = $this->getMultilanguageField('content');
		$visible = isset($_REQUEST['visible']) && ($_REQUEST['visible'] == 'on' || $_REQUEST['visible'] == '1') ? 1 : 0;
		$group = !empty($_REQUEST['group']) ? fix_id($_REQUEST['group']) : 'null';

		$data = array(
					'text_id'	=> $text_id,
					'group'		=> $group,
					'title'		=> $title,
					'content'	=> $content,
					'visible'	=> $visible,
					'author'	=> $_SESSION['uid'],
					'gallery'	=> fix_id($_REQUEST['gallery'])
				);

		if (is_null($id)) {
			$window = 'articles_new';
			$manager->insertData($data);
		} else {
			$window = 'articles_change';
			$manager->updateData($data,	array('id' => $id));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_article_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).';'.window_ReloadContent('articles'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Print confirmation dialog before deleting article
	 */
	private function deleteArticle() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = Modules\Articles\Manager::getInstance();

		$item = $manager->getSingleItem(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant('message_article_delete'),
					'name'			=> $item->title[$language],
					'yes_text'		=> $this->getLanguageConstant('delete'),
					'no_text'		=> $this->getLanguageConstant('cancel'),
					'yes_action'	=> window_LoadContent(
											'articles_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'articles_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('articles_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Delete article and print result message
	 */
	private function deleteArticle_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = Modules\Articles\Manager::getInstance();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_article_deleted'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('articles_delete').';'.window_ReloadContent('articles')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show article groups
	 */
	private function showGroups() {
		$template = new TemplateHandler('group_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('new'),
										'article_groups_new', 400,
										$this->getLanguageConstant('title_article_groups_new'),
										true, false,
										$this->name,
										'groups_new'
									),
					);

		$template->registerTagHandler('cms:group_list', $this, 'tag_GroupList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Print form for adding a new article group
	 */
	private function addGroup() {
		$template = new TemplateHandler('group_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'groups_save'),
					'cancel_action'	=> window_Close('article_groups_new')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Print form for changing article group data
	 */
	private function changeGroup() {
		$id = fix_id($_REQUEST['id']);
		$manager = Modules\Articles\GroupManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('group_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $item->id,
					'text_id'		=> $item->text_id,
					'title'			=> unfix_chars($item->title),
					'description'	=> $item->description,
					'form_action'	=> backend_UrlMake($this->name, 'groups_save'),
					'cancel_action'	=> window_Close('article_groups_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Print confirmation dialog prior to group removal
	 */
	private function deleteGroup() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = Modules\Articles\GroupManager::getInstance();

		$item = $manager->getSingleItem(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant('message_group_delete'),
					'name'			=> $item->title[$language],
					'yes_text'		=> $this->getLanguageConstant('delete'),
					'no_text'		=> $this->getLanguageConstant('cancel'),
					'yes_action'	=> window_LoadContent(
											'article_groups_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'groups_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('article_groups_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Perform removal of certain group
	 */
	private function deleteGroup_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = Modules\Articles\GroupManager::getInstance();
		$article_manager = Modules\Articles\Manager::getInstance();

		$manager->deleteData(array('id' => $id));
		$article_manager->updateData(array('group' => null), array('group' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_group_deleted'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('article_groups_delete').';'
									.window_ReloadContent('articles').';'
									.window_ReloadContent('article_groups')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save changed group data
	 */
	private function saveGroup() {
		$manager = Modules\Articles\GroupManager::getInstance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$text_id = escape_chars($_REQUEST['text_id']);
		$title = $this->getMultilanguageField('title');
		$description = $this->getMultilanguageField('description');

		$data = array(
					'text_id'		=> $text_id,
					'title'			=> $title,
					'description'	=> $description,
				);

		if (is_null($id)) {
			$window = 'article_groups_new';
			$manager->insertData($data);
		} else {
			$window = 'article_groups_change';
			$manager->updateData($data,	array('id' => $id));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_group_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).';'.window_ReloadContent('article_groups'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Tag handler for printing article
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Article($tag_params, $children) {
		$manager = Modules\Articles\Manager::getInstance();
		$group_manager = Modules\Articles\GroupManager::getInstance();
		$admin_manager = UserManager::getInstance();
		$conditions = array();
		$order_by = array('id');
		$order_asc = true;

		// get parameters
		if (isset($tag_params['id']))
			$conditions['id'] = fix_id($tag_params['id']);

		if (isset($tag_params['text_id']))
			$conditions['text_id'] = fix_chars(explode(',', $tag_params['text_id']));

		if (isset($tag_params['order_by']))
			$order_by = explode(',', fix_chars($tag_params['order_by']));

		if (isset($tag_params['random']) && $tag_params['random'] == 1)
			$order_by = array('RAND()');

		if (isset($tag_params['order_asc']))
			$order_asc = $tag_params['order_asc'] == 1 ? true : false;

		if (isset($tag_params['group'])) {
			$group_id_list = array();
			$group_names = fix_chars(explode(',', $tag_params['group']));

			if (count($group_names) > 0 && is_numeric($group_names[0])) {
				// specified group is a number, treat it as group id
				$group_id_list = $group_names;

			} else {
				// get id's from specitifed text_id
				$groups = $group_manager->getItems($group_manager->getFieldNames(), array('text_id' => $group_names));

				if (count($groups) > 0)
					foreach ($groups as $group)
						$group_id_list []= $group->id;
			}

			if (count($group_id_list) > 0)
				$conditions['group'] = $group_id_list; else
				$conditions['group'] = -1;
		}

		// get single item from the database
		$item = $manager->getSingleItem($manager->getFieldNames(), $conditions, $order_by, $order_asc);

		// load template
		$template = $this->loadTemplate($tag_params, 'article.xml');
		$template->setTemplateParamsFromArray($children);
		$template->setMappedModule($this->name);
		$template->registerTagHandler('cms:article_rating_image', $this, 'tag_ArticleRatingImage');

		// parse article
		if (is_object($item)) {
			$timestamp = strtotime($item->timestamp);
			$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
			$time = date($this->getLanguageConstant('format_time_short'), $timestamp);

			$params = array(
						'id'			=> $item->id,
						'text_id'		=> $item->text_id,
						'group'			=> $item->group,
						'timestamp'		=> $item->timestamp,
						'date'			=> $date,
						'time'			=> $time,
						'title'			=> $item->title,
						'content'		=> $item->content,
						'author'		=> $admin_manager->getItemValue(
																'fullname',
																array('id' => $item->author)
															),
						'gallery'		=> $item->gallery,
						'visible'		=> $item->visible,
						'views'			=> $item->views,
						'votes_up'		=> $item->votes_up,
						'votes_down' 	=> $item->votes_down,
						'rating'		=> $this->getArticleRating($item, 5),
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Tag handler for printing article list
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ArticleList($tag_params, $children) {
		$manager = Modules\Articles\Manager::getInstance();
		$group_manager = Modules\Articles\GroupManager::getInstance();
		$admin_manager = UserManager::getInstance();

		$conditions = array();
		$selected = -1;
		$order_by = array('id');
		$order_asc = true;

		// give the ability to limit number of articles to display
		if (isset($tag_params['limit']))
			$limit = fix_id($tag_params['limit']); else
			$limit = null;

		// get parameters
		if (isset($tag_params['id']))
			$conditions['id'] = fix_id($tag_params['id']);

		if (isset($tag_params['text_id']))
			$conditions['text_id'] = fix_chars(explode(',', $tag_params['text_id']));

		if (isset($tag_params['order_by']))
			$order_by = explode(',', fix_chars($tag_params['order_by']));

		if (isset($tag_params['random']) && $tag_params['random'] == 1)
			$order_by = array('RAND()');

		if (isset($tag_params['order_asc']))
			$order_asc = $tag_params['order_asc'] == 1 ? true : false;

		if (isset($tag_params['only_visible']) && $tag_params['only_visible'] == 1)
			$conditions['visible'] = 1;

		if (isset($tag_params['selected']))
			$selected = fix_id($tag_params['selected']);

		if (isset($tag_params['group'])) {
			$group_id_list = array();
			$group_names = fix_chars(explode(',', $tag_params['group']));

			if (count($group_names) > 0 && is_numeric($group_names[0])) {
				// specified group is a number, treat it as group id
				$group_id_list = $group_names;

			} else {
				// get id's from specitifed text_id
				$groups = $group_manager->getItems($group_manager->getFieldNames(), array('text_id' => $group_names));

				if (count($groups) > 0)
					foreach ($groups as $group)
						$group_id_list []= $group->id;
			}

			if (count($group_id_list) > 0)
				$conditions['group'] = $group_id_list; else
				$conditions['group'] = -1;
		}

		if (isset($tag_params['without_group']) && $tag_params['without_group'] == 1)
			$conditions['group'] = array(
					'operator' => 'is',
					'value'    => 'NULL'
				);

		// get items from manager
		$items = $manager->getItems($manager->getFieldNames(), $conditions, $order_by, $order_asc, $limit);

		// load template
		$template = $this->loadTemplate($tag_params, 'list_item.xml');
		$template->setTemplateParamsFromArray($children);
		$template->setMappedModule($this->name);
		$template->registerTagHandler('cms:article', $this, 'tag_Article');
		$template->registerTagHandler('cms:article_rating_image', $this, 'tag_ArticleRatingImage');

		if (count($items) > 0)
			foreach($items as $item) {
				$timestamp = strtotime($item->timestamp);
				$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
				$time = date($this->getLanguageConstant('format_time_short'), $timestamp);

				$params = array(
							'id'			=> $item->id,
							'text_id'		=> $item->text_id,
							'group'			=> $item->group,
							'timestamp'		=> $item->timestamp,
							'date'			=> $date,
							'time'			=> $time,
							'title'			=> $item->title,
							'content'		=> $item->content,
							'author'		=> $admin_manager->getItemValue(
																'fullname',
																array('id' => $item->author)
															),
							'gallery'		=> $item->gallery,
							'visible'		=> $item->visible,
							'views'			=> $item->views,
							'votes_up'		=> $item->votes_up,
							'votes_down' 	=> $item->votes_down,
							'rating'		=> $this->getArticleRating($item, 10),
							'selected'		=> $selected,
							'item_change'	=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														'articles_change', 	// window id
														730,				// width
														$this->getLanguageConstant('title_articles_change'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'articles_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> url_MakeHyperlink(
													$this->getLanguageConstant('delete'),
													window_Open(
														'articles_delete', 	// window id
														400,				// width
														$this->getLanguageConstant('title_articles_delete'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'articles_delete'),
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

	/**
	 * Tag handler for printing article list
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ArticleRatingImage($tag_params, $children) {
		if (isset($tag_params['id'])) {
			// print image tag with specified URL
			$id = fix_id($tag_params['id']);
			$type = isset($tag_params['type']) ? fix_id($tag_params['type']) : ImageType::Stars;
			$manager = Modules\Articles\Manager::getInstance();

			$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

			$template = new TemplateHandler('rating_image.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			if (is_object($item)) {
				$url = url_Make(
							'get_rating_image',
							$this->name,
							array('type', $type),
							array('id', $id)
						);

				$params = array(
							'url'		=> $url,
							'rating'	=> round($this->getArticleRating($item, 5), 2)
						);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}

		} else if (isset($_REQUEST['id'])) {
			// print image itself
			$id = fix_id($_REQUEST['id']);
			$type = isset($_REQUEST['type']) ? fix_id($_REQUEST['type']) : ImageType::Stars;
			$manager = Modules\Articles\Manager::getInstance();

			$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

			switch ($type) {
				case ImageType::Stars:
					$background_image = 'stars_bg.png';
					$foreground_image = 'stars.png';
					break;

				case ImageType::Circles:
					$background_image = 'circles_bg.png';
					$foreground_image = 'circles.png';
					break;

				default:
					$background_image = 'stars_bg.png';
					$foreground_image = 'stars.png';
					break;
			}

			$img_bg = imagecreatefrompng($this->path.'images/'.$background_image);
			$img_fg = imagecreatefrompng($this->path.'images/'.$foreground_image);

			// get rating based on image width
			if (is_object($item))
				$rating = $this->getArticleRating($item, imagesx($img_bg)); else
				$rating = 0;

			$img = imagecreatetruecolor(imagesx($img_bg), imagesy($img_bg));
			imagesavealpha($img, true);

			// make image transparent
			$transparent_color = imagecolorallocatealpha($img, 0, 0, 0, 127);
			imagefill($img, 0, 0, $transparent_color);

			// draw background image
			imagecopy($img, $img_bg, 0, 0, 0, 0, imagesx($img_bg), imagesy($img_bg));

			// draw foreground images
			imagecopy($img, $img_fg, 0, 0, 0, 0, $rating, imagesy($img_bg));

			header('Content-type: image/png');
			imagepng($img);
			imagedestroy($img);
		}
	}

	/**
	 * Tag handler for article group
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Group($tag_params, $children) {
		$id = isset($tag_params['id']) ? fix_id($tag_params['id']) : null;
		$text_id = isset($tag_params['text_id']) ? fix_chars($tag_params['text_id']) : null;

		// we need at least one of IDs in order to display article
		if (is_null($id) && is_null($text_id)) return;

		$manager = Modules\Articles\GroupManager::getInstance();

		// load template
		$template = $this->loadTemplate($tag_params, 'group.xml');
		$template->setTemplateParamsFromArray($children);
		$template->setMappedModule($this->name);
		$template->registerTagHandler('cms:article_list', $this, 'tag_ArticleList');

		if (!is_null($id))
			$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id)); else
			$item = $manager->getSingleItem($manager->getFieldNames(), array('text_id' => $text_id));

		if (is_object($item)) {
			$params = array(
						'id'			=> $item->id,
						'text_id'		=> $item->text_id,
						'title'			=> $item->title,
						'description'	=> $item->description,
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Tag handler for article group list
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_GroupList($tag_params, $children) {
		$manager = Modules\Articles\GroupManager::getInstance();
		$conditions = array();

		if (isset($tag_params['only_visible']) && $tag_params['only_visible'] == 'yes')
			$conditions['visible'] = 1;

		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// load template
		$template = $this->loadTemplate($tag_params, 'group_list_item.xml');
		$template->setTemplateParamsFromArray($children);
		$template->setMappedModule($this->name);
		$template->registerTagHandler('cms:article_list', $this, 'tag_ArticleList');

		// give the ability to limit number of links to display
		if (isset($tag_params['limit']))
			$items = array_slice($items, 0, fix_id($tag_params['limit']), true);

		$selected = isset($tag_params['selected']) ? fix_id($tag_params['selected']) : -1;

		if (count($items) > 0)
			foreach($items as $item) {
				$params = array(
							'id'			=> $item->id,
							'text_id'		=> $item->text_id,
							'title'			=> $item->title,
							'description'	=> $item->description,
							'selected'		=> $selected,
							'item_change'	=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														'article_groups_change', 	// window id
														400,						// width
														$this->getLanguageConstant('title_article_groups_change'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'groups_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> url_MakeHyperlink(
													$this->getLanguageConstant('delete'),
													window_Open(
														'article_groups_delete', 	// window id
														400,						// width
														$this->getLanguageConstant('title_article_groups_delete'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'groups_delete'),
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

	/**
	 * Generate JSON object for specified article
	 */
	private function json_Article() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : ImageType::Stars;
		$all_languages = isset($_REQUEST['all_languages']) && $_REQUEST['all_languages'] == 1;

		$manager = Modules\Articles\Manager::getInstance();
		$admin_manager = UserManager::getInstance();

		$result = array(
					'error'			=> false,
					'error_message'	=> ''
				);


		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$rating_image_url = url_Make(
					'get_rating_image',
					$this->name,
					array('type', $type),
					array('id', $id)
				);

		if (is_object($item)) {
			$timestamp = strtotime($item->timestamp);
			$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
			$time = date($this->getLanguageConstant('format_time_short'), $timestamp);

			$result['item'] = array(
								'id'			=> $item->id,
								'text_id'		=> $item->text_id,
								'timestamp'		=> $item->timestamp,
								'date'			=> $date,
								'time'			=> $time,
								'title'			=> $all_languages ? $item->title : $item->title[$language],
								'content'		=> $all_languages ? $item->content : Markdown::parse($item->content[$language]),
								'author'		=> $admin_manager->getItemValue(
																	'fullname',
																	array('id' => $item->author)
																),
								'visible'		=> $item->visible,
								'views'			=> $item->views,
								'votes_up'		=> $item->votes_up,
								'votes_down' 	=> $item->votes_down,
								'rating'		=> $this->getArticleRating($item, 10),
								'rating_image'	=> $rating_image_url
							);
		} else {
			// no item was found
			$result['error'] = true;
			$result['error_message'] = $this->getLanguageConstant('message_json_article_not_found');
		}

		print json_encode($result);
	}

	/**
	 * Generate JSON object list for specified parameters
	 */
	private function json_ArticleList() {
		global $language;

		$manager = Modules\Articles\Manager::getInstance();
		$group_manager = Modules\Articles\GroupManager::getInstance();
		$admin_manager = UserManager::getInstance();

		$conditions = array();
		$order_by = array('id');
		$order_asc = true;

		// give the ability to limit number of articles to display
		if (isset($_REQUEST['limit']))
			$limit = fix_id($_REQUEST['limit']); else
			$limit = null;

		// get parameters
		if (isset($_REQUEST['id']))
			$conditions['id'] = fix_id($_REQUEST['id']);

		if (isset($_REQUEST['text_id']))
			$conditions['text_id'] = explode(',', $_REQUEST['text_id']);

		if (isset($_REQUEST['order_by']))
			$order_by = explode(',', fix_chars($_REQUEST['order_by']));

		if (isset($_REQUEST['random']) && $_REQUEST['random'] == 1)
			$order_by = array('RAND()');

		if (isset($_REQUEST['order_asc']))
			$order_asc = $_REQUEST['order_asc'] == 1 ? true : false;

		if (isset($_REQUEST['only_visible']) && $_REQUEST['only_visible'] == 1)
			$conditions['visible'] = 1;

		if (isset($_REQUEST['group'])) {
			$group_id_list = array();
			$group_names = explode(',', $_REQUEST['group']);

			if (count($group_names) > 0 && is_numeric($group_names[0])) {
				// specified group is a number, treat it as group id
				$group_id_list = $group_names;

			} else {
				// get id's from specitifed text_id
				$groups = $group_manager->getItems($group_manager->getFieldNames(), array('text_id' => $group_names));

				if (count($groups) > 0)
					foreach ($groups as $group)
						$group_id_list []= $group->id;
			}

			if (count($group_id_list) > 0)
				$conditions['group'] = $group_id_list; else
				$conditions['group'] = -1;
		}

		$all_languages = isset($_REQUEST['all_languages']) && $_REQUEST['all_languages'] == 1;
		$rating_image_type = isset($_REQUEST['rating_image_type']) ? $_REQUEST['rating_image_type'] : ImageType::Stars;

		// get items from manager
		$items = $manager->getItems($manager->getFieldNames(), $conditions, $order_by, $order_asc, $limit);

		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'items'			=> array()
				);

		if (count($items) > 0) {
			foreach($items as $item) {
				$timestamp = strtotime($item->timestamp);
				$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
				$time = date($this->getLanguageConstant('format_time_short'), $timestamp);
				$rating_image_url = url_Make(
							'get_rating_image',
							$this->name,
							array('type', $rating_image_type),
							array('id', $item->id)
						);

				$result['items'][] = array(
									'id'			=> $item->id,
									'text_id'		=> $item->text_id,
									'timestamp'		=> $item->timestamp,
									'date'			=> $date,
									'time'			=> $time,
									'title'			=> $all_languages ? $item->title : $item->title[$language],
									'author'		=> $admin_manager->getItemValue(
																		'fullname',
																		array('id' => $item->author)
																	),
									'visible'		=> $item->visible,
									'views'			=> $item->views,
									'votes_up'		=> $item->votes_up,
									'votes_down' 	=> $item->votes_down,
									'rating'		=> $this->getArticleRating($item, 10),
									'rating_image'	=> $rating_image_url
								);
			}

		} else {
			// no articles were found for specified cirteria
			$result['error'] = true;
			$result['error_message'] = $this->getLanguageConstant('message_json_articles_not_found');
		}

		print json_encode($result);
	}

	/**
	 * Function to record vote from AJAX call
	 */
	private function json_Vote() {
		$id = fix_id($_REQUEST['id']);
		$value = $_REQUEST['value'];
		$manager = Modules\Articles\Manager::getInstance();
		$vote_manager = Modules\Articles\VoteManager::getInstance();

		$vote = $vote_manager->getSingleItem(
									array('id'),
									array(
										'article'	=> $id,
										'address'	=> $_SERVER['REMOTE_ADDR']
										)
									);

		$result = array(
					'error'			=> false,
					'error_message'	=> ''
				);

		if (is_object($vote)) {
			// that address already voted
			$result['error'] = true;
			$result['error_message'] = $this->getLanguageConstant('message_vote_already');

		} else {
			// stupid but we need to make sure article exists
			$article = $manager->getSingleItem(array('id', 'votes_up', 'votes_down'), array('id' => $id));

			if (is_object($article)) {
				$vote_manager->insertData(array(
										'article'	=> $article->id,
										'address'	=> $_SERVER['REMOTE_ADDR']
									));

				if (is_numeric($value)) {
					$data = array(
								'votes_up'		=> $article->votes_up,
								'votes_down'	=> $article->votes_down
							);

					if ($value == -1)
						$data['votes_down']++;

					if ($value == 1)
						$data['votes_up']++;

					$manager->updateData($data, array('id' => $article->id));
				}

				$article = $manager->getSingleItem(array('id', 'votes_up', 'votes_down'), array('id' => $id));
				$result['rating'] = $this->getArticleRating($article, 10);
			} else {
				$result['error'] = true;
				$result['error_message'] = $this->getLanguageConstant('message_vote_error');
			}
		}

		print json_encode($result);
	}

	/**
	 * Get article rating value based on max value specified
	 *
	 * @param resource $article
	 * @param integer $max
	 * @return integer
	 */
	public function getArticleRating($article, $max) {
		$total = $article->votes_up + $article->votes_down;

		if ($total == 0)
			$result = 0; else
			$result = ($article->votes_up * $max) / $total;

		return $result;
	}
}

?>
