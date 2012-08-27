<?php

/**
 * Articles Module
 *
 * @author MeanEYE.rcf
 */

class articles extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// register backend
		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

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
			$articles_menu->addSeparator(5);

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
		global $db, $db_active;

		$list = MainLanguageHandler::getInstance()->getLanguages(false);

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
				`visible` BOOLEAN NOT NULL DEFAULT '1',
				PRIMARY KEY ( `id` ),
				INDEX ( `text_id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		$sql = "CREATE TABLE `article_votes` (
					`id` INT NOT NULL AUTO_INCREMENT ,
					`address` VARCHAR( 15 ) NOT NULL ,
					`article` INT NOT NULL ,
				PRIMARY KEY (  `id` ),
				INDEX ( `address`, `article` )
				) ENGINE = MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db, $db_active;

		$sql = "DROP TABLE IF EXISTS `articles`, `article_group`, `article_votes`;";
		if ($db_active == 1) $db->query($sql);
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

		$template->registerTagHandler('_article_list', &$this, 'tag_ArticleList');
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
		$template->registerTagHandler('_group_list', &$this, 'tag_GroupList');

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
		$manager = ArticleManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('change.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);
			$template->registerTagHandler('_group_list', &$this, 'tag_GroupList');

			$params = array(
						'id'			=> $item->id,
						'text_id'		=> unfix_chars($item->text_id),
						'group'			=> $item->group,
						'title'			=> unfix_chars($item->title),
						'content'		=> $item->content,
						'visible' 		=> $item->visible,
						'form_action'	=> backend_UrlMake($this->name, 'articles_save'),
						'cancel_action'	=> window_Close('articles_change')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save article data
	 */
	private function saveArticle() {
		$manager = ArticleManager::getInstance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$text_id = escape_chars($_REQUEST['text_id']);
		$title = fix_chars($this->getMultilanguageField('title'));
		$content = escape_chars($this->getMultilanguageField('content'));
		$visible = isset($_REQUEST['visible']) && ($_REQUEST['visible'] == 'on' || $_REQUEST['visible'] == '1') ? 1 : 0;
		$group = !empty($_REQUEST['group']) ? fix_id($_REQUEST['group']) : 'null';

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
		$template->parse();
	}

	/**
	 * Print confirmation dialog before deleting article
	 */
	private function deleteArticle() {
		$id = fix_id($_REQUEST['id']);
		$manager = ArticleManager::getInstance();

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
		$template->parse();
	}

	/**
	 * Delete article and print result message
	 */
	private function deleteArticle_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ArticleManager::getInstance();

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

		$template->registerTagHandler('_group_list', &$this, 'tag_GroupList');
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
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = ArticleGroupManager::getInstance();

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

		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = ArticleGroupManager::getInstance();

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
		$template->parse();
	}

	/**
	 * Perform removal of certain group
	 */
	private function deleteGroup_Commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = ArticleGroupManager::getInstance();
		$article_manager = ArticleManager::getInstance();

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
		$template->parse();
	}

	/**
	 * Save changed group data
	 */
	private function saveGroup() {
		$manager = ArticleGroupManager::getInstance();

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
		$template->parse();
	}

	/**
	 * Tag handler for printing article
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Article($tag_params, $children) {
		$item = null;
		$manager = ArticleManager::getInstance();
		$admin_manager = AdministratorManager::getInstance();

		$id = isset($tag_params['id']) ? fix_id($tag_params['id']) : null;

		if (is_null($id)) {
			$id_list = array();
			$group_list = array();
			$conditions = array();

			if (isset($tag_params['text_id']))
				$conditions['text_id'] = explode(',', $tag_params['text_id']);

			if (isset($tag_params['group']))
				$group_list = explode(',', $tag_params['group']);

			// get item from specified parameters
			$list = $this->getArticleList(
									$manager->getFieldNames(),
									$conditions,
									isset($tag_params['random']) && ($tag_params['random'] == 1),
									1,
									$group_list
								);

			if (count($list) > 0)
				$item = $list[0];
		} else {
			$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));
		}

		$template = $this->loadTemplate($tag_params, 'article.xml');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('_article_rating_image', &$this, 'tag_ArticleRatingImage');

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
						'author'		=> $admin_manager->getItemValue(
																'fullname',
																array('id' => $item->author)
															),
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
		$manager = ArticleManager::getInstance();
		$admin_manager = AdministratorManager::getInstance();

		$conditions = array();
		$group_list = array();

		if (isset($tag_params['group']))
			$group_list = explode(',', $tag_params['group']);

		if (isset($tag_params['only_visible']) && $tag_params['only_visible'] == 1)
			$conditions['visible'] = 1;

		// give the ability to limit number of articles to display
		if (isset($tag_params['limit']))
			$limit = fix_id($tag_params['limit']); else
			$limit = null;

		// get items from manager
		$items = $this->getArticleList(
								$manager->getFieldNames(),
								$conditions,
								isset($tag_params['random']) && $tag_params['random'] == 1,
								$limit,
								$group_list
							);

		// load template
		$template = $this->loadTemplate($tag_params, 'list_item.xml');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('_article', &$this, 'tag_Article');
		$template->registerTagHandler('_article_rating_image', &$this, 'tag_ArticleRatingImage');

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
							'author'		=> $admin_manager->getItemValue(
																'fullname',
																array('id' => $item->author)
															),
							'visible'		=> $item->visible,
							'views'			=> $item->views,
							'votes_up'		=> $item->votes_up,
							'votes_down' 	=> $item->votes_down,
							'rating'		=> $this->getArticleRating($item, 10),
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
			$type = isset($tag_params['type']) ? $tag_params['type'] : ImageType::Stars;
			$manager = ArticleManager::getInstance();

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
			$manager = ArticleManager::getInstance();

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
		$text_id = isset($tag_params['text_id']) ? escape_chars($tag_params['text_id']) : null;

		// we need at least one of IDs in order to display article
		if (is_null($id) && is_null($text_id)) return;

		$manager = ArticleGroupManager::getInstance();

		// load template
		$template = $this->loadTemplate($tag_params, 'group.xml');
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
		$manager = ArticleGroupManager::getInstance();
		$conditions = array();

		if (isset($tag_params['only_visible']) && $tag_params['only_visible'] == 'yes')
			$conditions['visible'] = 1;

		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// load template
		$template = $this->loadTemplate($tag_params, 'group_list_item.xml');
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

		$manager = ArticleManager::getInstance();
		$admin_manager = AdministratorManager::getInstance();

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
								'content'		=> $all_languages ? $item->content : Markdown($item->content[$language]),
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

		$manager = ArticleManager::getInstance();
		$admin_manager = AdministratorManager::getInstance();

		$conditions = array();
		$group_list = array();

		if (isset($_REQUEST['group']))
			$group_list = explode(',', $_REQUEST['group']);

		if (isset($_REQUEST['only_visible']) && $_REQUEST['only_visible'] == 1)
			$conditions['visible'] = 1;

		$all_languages = isset($_REQUEST['all_languages']) && $_REQUEST['all_languages'] == 1;

		$rating_image_type = isset($_REQUEST['rating_image_type']) ? $_REQUEST['rating_image_type'] : ImageType::Stars;

		// give the ability to limit number of articles to display
		if (isset($_REQUEST['limit']))
			$limit = fix_id($_REQUEST['limit']); else
			$limit = null;

		// get items from manager
		$items = $this->getArticleList(
								$manager->getFieldNames(),
								$conditions,
								isset($_REQUEST['random']) && $_REQUEST['random'] == 1,
								$limit,
								$group_list
							);

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
		$manager = ArticleManager::getInstance();
		$vote_manager = ArticleVoteManager::getInstance();

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

	/**
	 * Function used to retrieve Id list (or single Id) from database based on parameters
	 *
	 * @param array $fields Specify field selection
	 * @param array $conditions Initial conditions for query
	 * @param boolean $random Should items be selected randomly
	 * @param integer $limit Limit results
	 * @param array $group_list If item should be member of any of specified group text_id's
	 * @return array
	 */
	private function getArticleList($fields=array(), $conditions=array(), $random=true, $limit=null, $group_list=array()) {
		$order_by = $random ? 'RAND()' : 'id';
		$manager = ArticleManager::getInstance();

		if (!empty($group_list)) {
			$group_id_list = array();
			$group_manager = ArticleGroupManager::getInstance();

			$items = $group_manager->getItems(array('id'), array('text_id' => $group_list));

			// no items were found in specified groups and id_list is empty
			if (empty($id_list) && empty($items)) return array();

			if (count($items) > 0)
				foreach($items as $item)
					$group_id_list[] = $item->id;

			$conditions['group'] = $group_id_list;
		}

		$items = $manager->getItems($fields, $conditions, array($order_by), false, $limit);

		return $items;
	}
}


class ArticleManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
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

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}


class ArticleGroupManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('article_groups');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('title', 'ml_varchar');
		$this->addProperty('description', 'ml_text');
		$this->addProperty('visible', 'boolean');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}

class ArticleVoteManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('article_votes');

		$this->addProperty('id', 'int');
		$this->addProperty('address', 'varchar');
		$this->addProperty('article', 'int');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}


class ImageType {
	const Stars = 1;
	const Circles = 2;

	private function __construct() {
	}
}
