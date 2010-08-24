<?php

/**
 * BLANK MODULE
 *
 * @author MeanEYE
 * @copyright RCF Group,2008.
 */

class articles extends Module {

	/**
	 * Constructor
	 *
	 * @return _blank
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
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show':
					$this->tag_Article($level, $params, $children);
					break;

				case 'show_rating_image':
					$this->tag_ArticleRatingImage($level, $params, $children);
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'articles':
					$this->showArticles($level);
					break;

				case 'articles_new':
					$this->addArticle($level);
					break;

				case 'articles_change':
					$this->changeArticle($level);
					break;

				case 'articles_save':
					$this->saveArticle($level);
					break;

				case 'articles_delete':
					$this->deleteArticle($level);
					break;

				case 'articles_delete_commit':
					$this->deleteArticle_Commit($level);
					break;

				// ---

				case 'groups':
					$this->showGroups($level);
					break;

				case 'groups_new':
					$this->addGroup($level);
					break;

				case 'groups_change':
					$this->changeGroup($level);
					break;

				case 'groups_save':
					$this->saveGroup($level);
					break;

				case 'groups_delete':
					$this->deleteGroup($level);
					break;

				case 'groups_delete_commit':
					$this->deleteGroup_Commit($level);
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	function onInit() {
		global $LanguageHandler, $db_active, $db;

		$list = $LanguageHandler->getLanguages(false);

		$sql = "
			CREATE TABLE `articles` (
				`id` INT NOT NULL AUTO_INCREMENT ,
				`group` int(11) DEFAULT NULL ,
				`text_id` VARCHAR (32) NULL ,
				`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
			";

		foreach($list as $language)
			$sql .= "`title_{$language}` VARCHAR( 255 ) NOT NULL DEFAULT '',";

		foreach($list as $language)
			$sql .= "`content_{$language}` TEXT NOT NULL ,";

		$sql .= "
				`author` INT NOT NULL ,
				`visible` BOOLEAN NOT NULL DEFAULT '0',
				`views` INT NOT NULL DEFAULT '0',
				`votes_up` INT NOT NULL DEFAULT '0',
				`votes_down` INT NOT NULL DEFAULT '0',
				PRIMARY KEY ( `id` ),
				INDEX ( `author` ),
				INDEX ( `group` ),
				INDEX ( `text_id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";

		if ($db_active == 1) $db->query($sql);

		// article groups
		$sql = "
			CREATE TABLE `article_groups` (
				`id` INT NOT NULL AUTO_INCREMENT ,
				`text_id` VARCHAR (32) NULL ,
			";

		foreach($list as $language)
			$sql .= "`title_{$language}` VARCHAR( 255 ) NOT NULL DEFAULT '',";

		foreach($list as $language)
			$sql .= "`description_{$language}` TEXT NOT NULL ,";

		$sql .= "
				PRIMARY KEY ( `id` ),
				INDEX ( `text_id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";

		if ($db_active == 1) $db->query($sql);

	}

	/**
	 * Event triggered upon module deinitialization
	 */
	function onDisable() {
		global $db, $db_active;

		$sql = "DROP TABLE IF EXISTS `articles`, `article_group`;";
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler;

		// load module style and scripts
		if ($ModuleHandler->moduleExists('head_tag')) {
			$head_tag = $ModuleHandler->getObjectFromName('head_tag');
			//$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/_blank.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/_blank.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if ($ModuleHandler->moduleExists('backend')) {
			$backend = $ModuleHandler->getObjectFromName('backend');

			$articles_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_articles'),
					url_GetFromFilePath($this->path.'images/icon.png'),
					'javascript:void(0);',
					$level=5
				);

			$articles_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_articles_new'),
								url_GetFromFilePath($this->path.'images/new_article.png'),

								window_Open( // on click open window
											'articles_new',
											730,
											$this->getLanguageConstant('title_articles_new'),
											true, true,
											backend_UrlMake($this->name, 'articles_new')
										),
								$level=5
							));
			$articles_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_articles_manage'),
								url_GetFromFilePath($this->path.'images/manage.png'),

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
								url_GetFromFilePath($this->path.'images/groups.png'),

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
	 * Show administration form for articles
	 * @param integer $level
	 */
	function showArticles($level) {
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

		$template->registerTagHandler('_article_list', &$this, 'tag_ArticleList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Print input form for new article
	 * @param integer $level
	 */
	function addArticle($level) {
		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('_group_list', &$this, 'tag_GroupList');

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'articles_save'),
					'cancel_action'	=> window_Close('articles_new')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Display article for modification
	 * @param integer $level
	 */
	function changeArticle($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new ArticleManager();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('_group_list', &$this, 'tag_GroupList');

		$params = array(
					'id'			=> $item->id,
					'text_id'		=> $item->text_id,
					'group'			=> $item->group,
					'title'			=> unfix_chars($item->title),
					'content'		=> $item->content,
					'visible' 		=> $item->visible,
					'form_action'	=> backend_UrlMake($this->name, 'articles_save'),
					'cancel_action'	=> window_Close('articles_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Save article data
	 * @param integer $level
	 */
	function saveArticle($level) {
		$manager = new ArticleManager();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$text_id = escape_chars($_REQUEST['text_id']);
		$title = fix_chars($this->getMultilanguageField('title'));
		$content = escape_chars($this->getMultilanguageField('content'));
		$visible = fix_id($_REQUEST['visible']);
		$group = fix_id($_REQUEST['group']);

		$data = array(
					'text_id'	=> $text_id,
					'group'		=> $group,
					'title'		=> $title,
					'content'	=> $content,
					'visible'	=> $visible,
					'author'	=> $_SESSION['uid']
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
					'action'	=> window_Close($window).";".window_ReloadContent('articles'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Print confirmation dialog before deleting article
	 * @param integer $level
	 */
	function deleteArticle($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new ArticleManager();

		$item = $manager->getSingleItem(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_article_delete"),
					'name'			=> $item->title,
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
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
		$template->parse($level);
	}

	/**
	 * Delete article and print result message
	 * @param integer $level
	 */
	function deleteArticle_Commit($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new ArticleManager();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_article_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('articles_delete').";".window_ReloadContent('articles')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Show article groups
	 * @param integer $level
	 */
	function showGroups($level) {
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

		$template->registerTagHandler('_group_list', &$this, 'tag_GroupList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Print form for adding a new article group
	 * @param integer $level
	 */
	function addGroup($level) {
		$template = new TemplateHandler('group_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'groups_save'),
					'cancel_action'	=> window_Close('article_groups_new')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Print form for changing article group data
	 * @param integer $level
	 */
	function changeGroup($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new ArticleGroupManager();

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
		$template->parse($level);
	}

	/**
	 * Print confirmation dialog prior to group removal
	 * @param integer $level
	 */
	function deleteGroup($level) {
		global $language;

		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new ArticleGroupManager();

		$item = $manager->getSingleItem(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_group_delete"),
					'name'			=> $item->title[$language],
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
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
		$template->parse($level);
	}

	/**
	 * Perform removal of certain group
	 * @param integer $level
	 */
	function deleteGroup_Commit($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new ArticleGroupManager();
		$article_manager = new ArticleManager();

		$manager->deleteData(array('id' => $id));
		$article_manager->updateData(array('group' => null), array('group' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_group_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('article_groups_delete').";"
									.window_ReloadContent('articles').";"
									.window_ReloadContent('article_groups')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Save changed group data
	 * @param integer $level
	 */
	function saveGroup($level) {
		$manager = new ArticleGroupManager();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$text_id = escape_chars($_REQUEST['text_id']);
		$title = fix_chars($this->getMultilanguageField('title'));
		$description = escape_chars($this->getMultilanguageField('description'));

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
					'action'	=> window_Close($window).";".window_ReloadContent('article_groups'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Tag handler for printing article
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	function tag_Article($level, $tag_params, $children) {
		$id = isset($tag_params['id']) ? fix_id($tag_params['id']) : null;
		$text_id = isset($tag_params['text_id']) ? mysql_real_escape_string(strip_tags($tag_params['text_id'])) : null;

		// we need at least one of IDs in order to display article
		if (is_null($id) && is_null($text_id)) return;

		$manager = new ArticleManager();

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('article.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);
		$template->registerTagHandler('_article_rating_image', &$this, 'tag_ArticleRatingImage');

		if (!is_null($id))
			$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id)); else
			$item = $manager->getSingleItem($manager->getFieldNames(), array('text_id' => $text_id));

		if (is_object($item)) {
			$timestamp = strtotime($item->timestamp);
			$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
			$time = date($this->getLanguageConstant('format_time_short'), $timestamp);

			$params = array(
						'id'			=> $item->id,
						'text_id'		=> $item->text_id,
						'timestamp'		=> $item->timestamp,
						'date'			=> $date,
						'time'			=> $time,
						'title'			=> $item->title,
						'content'		=> $item->content,
						'author'		=> $item->author,
						'visible'		=> $item->visible,
						'views'			=> $item->views,
						'votes_up'		=> $item->votes_up,
						'votes_down' 	=> $item->votes_down,
						'rating'		=> $this->_getArticleRating($item, 10),
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}

	/**
	 * Tag handler for printing article list
	 *
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	function tag_ArticleList($level, $tag_params, $children) {
		$manager = new ArticleManager();
		$admin_manager = new AdministratorManager();  // session module is pre-load required module

		$only_visible = isset($tag_params['only_visible']) ? $tag_params['only_visible'] == 1 : false;
		$conditions = array();

		if ($only_visible) $conditions['visible'] = 1;

		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('list_item.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);
		$template->registerTagHandler('_article', &$this, 'tag_Article');
		$template->registerTagHandler('_article_rating_image', &$this, 'tag_ArticleRatingImage');

		// give the ability to limit number of links to display
		if (isset($tag_params['limit']))
			$items = array_slice($items, 0, $tag_params['limit'], true);

		if (count($items) > 0)
			foreach($items as $item) {
				$timestamp = strtotime($item->timestamp);
				$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
				$time = date($this->getLanguageConstant('format_time_short'), $timestamp);

				$params = array(
							'id'			=> $item->id,
							'text_id'		=> $item->text_id,
							'timestamp'		=> $item->timestamp,
							'date'			=> $date,
							'time'			=> $time,
							'title'			=> $item->title,
							'content'		=> $item->content,
							'author'		=> $admin_manager->getItemValue('fullname', array('id' => $item->author)),
							'visible'		=> $item->visible,
							'views'			=> $item->views,
							'votes_up'		=> $item->votes_up,
							'votes_down' 	=> $item->votes_down,
							'rating'		=> $this->_getArticleRating($item, 10),
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
							'item_read'		=> '',
						);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse($level);
			}
	}

	/**
	 * Tag handler for printing article list
	 *
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	function tag_ArticleRatingImage($level, $tag_params, $children) {
	}

	/**
	 * Tag handler for article group
	 *
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	function tag_Group($level, $tag_params, $children) {
		$id = isset($tag_params['id']) ? fix_id($tag_params['id']) : null;
		$text_id = isset($tag_params['text_id']) ? mysql_real_escape_string(strip_tags($tag_params['text_id'])) : null;

		// we need at least one of IDs in order to display article
		if (is_null($id) && is_null($text_id)) return;

		$manager = new ArticleGroupManager();

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('group.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);
		$template->registerTagHandler('_article_list', &$this, 'tag_ArticleList');

		if (!is_null($id))
			$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id)); else
			$item = $manager->getSingleItem($manager->getFieldNames(), array('text_id' => $text_id));

		if (is_object($item)) {
			$params = array(
						'id'			=> $item->id,
						'text_id'		=> $item->text_id,
						'title'			=> $item->title,
						'desciption'	=> $item->description,
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}

	/**
	 * Tag handler for article group list
	 *
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	function tag_GroupList($level, $tag_params, $children) {
		$manager = new ArticleGroupManager();

		$items = $manager->getItems($manager->getFieldNames(), array());

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('group_list_item.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);
		$template->registerTagHandler('_article_list', &$this, 'tag_ArticleList');

		// give the ability to limit number of links to display
		if (isset($tag_params['limit']))
			$items = array_slice($items, 0, $tag_params['limit'], true);

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
				$template->parse($level);
			}
	}

	/**
	 * Get article rating value based on max value specified
	 *
	 * @param resource $article
	 * @param integer $max
	 * @return integer
	 */
	function _getArticleRating($article, $max) {
		$total = $article->votes_up + $article->votes_down;

		if ($total == 0) {
			// no votes recorder, return 0
			$result = 0;

		} else {
			// we have some votes recorder
			$current = $article->votes_up - $article->votes_down;
			if ($current < 0) $current = 0;

			$result = ($current * $max) / $total;
		}

		return $result;
	}
}

class ArticleManager extends ItemManager {
	function __construct() {
		parent::__construct('articles');

		$this->addProperty('id', 'int');
		$this->addProperty('group', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('timestamp', 'timestamp');
		$this->addProperty('title', 'ml_varchar');
		$this->addProperty('content', 'ml_text');
		$this->addProperty('author', 'int');
		$this->addProperty('visible', 'boolean');
		$this->addProperty('views', 'int');
		$this->addProperty('votes_up', 'int');
		$this->addProperty('votes_down', 'int');
	}
}

class ArticleGroupManager extends ItemManager {
	function __construct() {
		parent::__construct('article_groups');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('title', 'ml_varchar');
		$this->addProperty('description', 'ml_text');
	}
}
