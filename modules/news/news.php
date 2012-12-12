<?php

/**
 * News Module
 *
 * @author MeanEYE.rcf
 */

class news extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		if (class_exists('head_tag')) {
			$head_tag = head_tag::getInstance();

			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/news_system.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			$news_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_news'),
					url_GetFromFilePath($this->path.'images/icon.png'),
					'javascript:void(0);',
					5  // level
				);

			$news_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_add_news'),
								url_GetFromFilePath($this->path.'images/add_news.png'),
								window_Open( // on click open window
											'news_add',
											490,
											$this->getLanguageConstant('title_news_add'),
											true, true,
											backend_UrlMake($this->name, 'news_add')
										),
								5  // level
							));
			$news_menu->addSeparator(5);

			$news_menu->addChild(null, new backend_MenuItem(
					$this->getLanguageConstant('menu_manage_news'),
					url_GetFromFilePath($this->path.'images/manage_news.png'),
					window_Open( // on click open window
								'news',
								520,
								$this->getLanguageConstant('title_manage_news'),
								true, true,
								backend_UrlMake($this->name, 'news')
							),
					5  // level
				));

			$news_menu->addChild(null, new backend_MenuItem(
					$this->getLanguageConstant('menu_manage_groups'),
					url_GetFromFilePath($this->path.'images/manage_groups.png'),
					window_Open( // on click open window
								'news_groups',
								580,
								$this->getLanguageConstant('title_manage_groups'),
								true, true,
								backend_UrlMake($this->name, 'groups')
							),
					5  // level
				));

			$news_menu->addSeparator(5);

			$news_menu->addChild(null, new backend_MenuItem(
					$this->getLanguageConstant('menu_news_feeds'),
					url_GetFromFilePath($this->path.'images/rss.png'),
					window_Open( // on click open window
								'news_feeds',
								700,
								$this->getLanguageConstant('title_manage_feeds'),
								true, true,
								backend_UrlMake($this->name, 'feeds')
							),
					5  // level
				));

			$backend->addMenu($this->name, $news_menu);

		} else {
			// if we are not in backend section, create feed links
			$this->createFeedLinks();
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
	public function transferControl($params, $children) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show':
					$this->tag_News($params, $children);
					break;

				case 'show_list':
					$this->tag_NewsList($params, $children);
					break;

				case 'show_group':
					$this->tag_Group($params, $children);
					break;

				case 'show_group_list':
					$this->tag_GroupList($params, $children);
					break;

				case 'show_feed':
					$this->tag_Feed($params, $children);
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'news':
					$this->showNews();
					break;

				case 'news_add':
					$this->addNews();
					break;

				case 'news_change':
					$this->changeNews();
					break;

				case 'news_save':
					$this->saveNews();
					break;

				case 'news_delete':
					$this->deleteNews();
					break;

				case 'news_delete_commit':
					$this->deleteNews_Commit();
					break;

				// ---

				case 'groups':
					$this->showGroups();
					break;

				case 'group_add':
					$this->addGroup();
					break;

				case 'group_change':
					$this->changeGroup();
					break;

				case 'group_save':
					$this->saveGroup();
					break;

				case 'group_delete':
					$this->deleteGroup();
					break;

				case 'group_delete_commit':
					$this->deleteGroup_Commit();
					break;

				case 'group_items':
					$this->groupItems();
					break;

				case 'group_items_save':
					$this->groupItems_Save();
					break;

				// ---

				case 'feeds':
					$this->showFeeds();
					break;

				case 'feed_add':
					$this->addFeed();
					break;

				case 'feed_change':
					$this->changeFeed();
					break;

				case 'feed_save':
					$this->saveFeed();
					break;

				case 'feed_delete':
					$this->deleteFeed();
					break;

				case 'feed_delete_commit':
					$this->deleteFeed_Commit();
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

		// News
		$sql = "
			CREATE TABLE `news` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`author` int(11) NOT NULL,";

		foreach($list as $language)
			$sql .= "`title_{$language}` VARCHAR( 255 ) NOT NULL DEFAULT '',";

		foreach($list as $language)
			$sql .= "`content_{$language}` TEXT NOT NULL ,";

		$sql .= "`visible` BOOLEAN NOT NULL DEFAULT '1',
				PRIMARY KEY ( `id` ),
				KEY `author` (`author`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		// News Membership
		$sql = "
			CREATE TABLE IF NOT EXISTS `news_membership` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`news` int(11) NOT NULL,
				`group` int(11) NOT NULL,
				PRIMARY KEY (`id`),
				KEY `group` (`group`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		// News Groups
		$sql = "
			CREATE TABLE IF NOT EXISTS `news_groups` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`text_id` varchar(32) COLLATE utf8_bin NULL,";

		foreach($list as $language)
			$sql .= "`title_{$language}` VARCHAR( 255 ) NOT NULL,";

		$sql .= "PRIMARY KEY (`id`),
				KEY `text_id` (`text_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		// News Feeds
		$sql = "
			CREATE TABLE IF NOT EXISTS `news_feeds` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`group` int(11) NOT NULL,
				`news_count` int(11) NOT NULL,";

		foreach($list as $language)
			$sql .= "`title_{$language}` VARCHAR( 255 ) NOT NULL,";

		foreach($list as $language)
			$sql .= "`description_{$language}` TEXT NOT NULL,";

		$sql .= "`active` BOOLEAN NOT NULL DEFAULT '1',
				PRIMARY KEY (`id`),
				KEY `active` (`active`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db_active, $db;

		$sql = "DROP TABLE IF EXISTS `news`, `news_membership`, `news_groups`, `news_feeds`;";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Show news management form
	 */
	private function showNews() {
		$template = new TemplateHandler('news_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> url_MakeHyperlink(
										$this->getLanguageConstant('add_news'),
										window_Open( // on click open window
											'news_add',
											490,
											$this->getLanguageConstant('title_news_add'),
											true, true,
											backend_UrlMake($this->name, 'news_add')
										)
									),
					'link_groups'	=> url_MakeHyperlink(
										$this->getLanguageConstant('groups'),
										window_Open( // on click open window
											'news_groups',
											580,
											$this->getLanguageConstant('title_manage_news'),
											true, true,
											backend_UrlMake($this->name, 'groups')
										)
									)
					);

		$template->registerTagHandler('_news_list', $this, 'tag_NewsList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for adding news item
	 */
	private function addNews() {
		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'news_save'),
					'cancel_action'	=> window_Close('news_add')
				);

		$template->registerTagHandler('_group_list', $this, 'tag_GroupList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Change news
	 */
	private function changeNews() {
		if (!isset($_REQUEST['id'])) return;

		$id = fix_id($_REQUEST['id']);
		$manager = NewsManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'		=> $item->id,
					'title'		=> $item->title,
					'content'	=> $item->content,
					'visible'	=> $item->visible,
					'form_action'	=> backend_UrlMake($this->name, 'news_save'),
					'cancel_action'	=> window_Close('news_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save changed or new data
	 */
	private function saveNews() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = NewsManager::getInstance();

		$data = array(
					'author'	=> $_SESSION['uid'],
					'title'		=> fix_chars($this->getMultilanguageField('title')),
					'content'	=> escape_chars($this->getMultilanguageField('content')),
					'visible'	=> fix_id($_REQUEST['visible'])
				);

		if (is_null($id)) {
			$manager->insertData($data);
			$window = 'news_add';
		} else {
			$manager->updateData($data, array('id' => $id));
			$window = 'news_change';
		}

		// if group has been selected and field exists
		if (isset($_REQUEST['group']) && !empty($_REQUEST['group'])) {
			$membership_manager = NewsMembershipManager::getInstance();
			$group = fix_id($_REQUEST['group']);
			$news_id = $manager->getInsertedID();

			if (!empty($news_id))
				$membership_manager->insertData(array(
											'news'	=> $news_id,
											'group'	=> $group
										));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_news_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('news'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Print news confirmation dialog before deleting
	 */
	private function deleteNews() {
		global $language;

		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = NewsManager::getInstance();

		$item = $manager->getSingleItem(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_news_delete"),
					'name'			=> $item->title[$language],
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'news_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'news_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('news_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Delete news from database
	 */
	private function deleteNews_Commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = NewsManager::getInstance();
		$membership_manager = NewsMembershipManager::getInstance();

		$manager->deleteData(array('id' => $id));
		$membership_manager->deleteData(array('news' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_news_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('news_delete').";"
									.window_ReloadContent('news')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show group list
	 */
	private function showGroups() {
		$template = new TemplateHandler('group_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> url_MakeHyperlink(
										$this->getLanguageConstant('add_group'),
										window_Open( // on click open window
											'news_group_add',
											390,
											$this->getLanguageConstant('title_news_group_add'),
											true, true,
											backend_UrlMake($this->name, 'group_add')
										)
									),
					);

		$template->registerTagHandler('_group_list', $this, 'tag_GroupList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Add new news group
	 */
	private function addGroup() {
		$template = new TemplateHandler('group_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'group_save'),
					'cancel_action'	=> window_Close('news_group_add')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Change group data
	 */
	private function changeGroup() {
		if (!isset($_REQUEST['id'])) return;

		$id = fix_id($_REQUEST['id']);
		$manager = NewsGroupManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('group_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		if (is_object($item)) {
			$params = array(
						'id'			=> $item->id,
						'text_id'		=> $item->text_id,
						'title'			=> $item->title,
						'form_action'	=> backend_UrlMake($this->name, 'group_save'),
						'cancel_action'	=> window_Close('news_group_change')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save group data
	 */
	private function saveGroup() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = NewsGroupManager::getInstance();

		$data = array(
					'text_id'	=> fix_chars($_REQUEST['text_id']),
					'title'		=> fix_chars($this->getMultilanguageField('title'))
				);

		if (is_null($id)) {
			$manager->insertData($data);
			$window = 'news_group_add';
		} else {
			$manager->updateData($data, array('id' => $id));
			$window = 'news_group_change';
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_group_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('news_groups'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Group removal confirmation dialog
	 */
	private function deleteGroup() {
		global $language;

		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = NewsGroupManager::getInstance();

		$item = $manager->getSingleItem(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_news_delete"),
					'name'			=> $item->title[$language],
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'news_group_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'group_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('news_group_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Remove group from database
	 */
	private function deleteGroup_Commit() {
		if (!isset($_REQUEST['id'])) return;

		$id = fix_chars($_REQUEST['id']);
		$manager = NewsGroupManager::getInstance();
		$membership_manager = NewsMembershipManager::getInstance();
		$feed_manager = NewsFeedManager::getInstance();

		$manager->deleteData(array('id' => $id));
		$membership_manager->deleteData(array('group' => $id));
		$feed_manager->deleteData(array('group' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_group_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('news_group_delete').";"
									.window_ReloadContent('news_groups')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show group items edit form
	 */
	private function groupItems() {
		$group_id = fix_id(fix_chars($_REQUEST['id']));

		$template = new TemplateHandler('group_news.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'group'			=> $group_id,
					'form_action'	=> backend_UrlMake($this->name, 'group_items_save'),
					'cancel_action'	=> window_Close('news_group_items')
				);

		$template->registerTagHandler('_group_items', $this, 'tag_GroupItems');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save group items
	 */
	private function groupItems_Save() {
		$group = fix_id(fix_chars($_REQUEST['group']));
		$membership_manager = NewsMembershipManager::getInstance();

		// fetch all ids being set to specific group
		$news_ids = array();
		foreach ($_REQUEST as $key => $value) {
			if (substr($key, 0, 8) == 'news_id_' && $value == 1)
				$news_ids[] = fix_id(substr($key, 8));
		}

		// remove old memberships
		$membership_manager->deleteData(array('group' => $group));

		// save new memberships
		foreach ($news_ids as $id)
			$membership_manager->insertData(array(
											'news'	=> $id,
											'group'	=> $group
										));

		// display message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_group_items_updated"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('news_group_items')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show news feed management form
	 */
	private function showFeeds() {
		$template = new TemplateHandler('feed_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> url_MakeHyperlink(
										$this->getLanguageConstant('add_feed'),
										window_Open( // on click open window
											'news_feeds_add',
											390,
											$this->getLanguageConstant('title_news_feed_add'),
											true, true,
											backend_UrlMake($this->name, 'feed_add')
										)
									),
					);

		$template->registerTagHandler('_feed_list', $this, 'tag_FeedList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Add new feed
	 */
	private function addFeed() {
		$template = new TemplateHandler('feed_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'feed_save'),
					'cancel_action'	=> window_Close('news_feeds_add')
				);

		$template->registerTagHandler('_group_list', $this, 'tag_GroupList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Change feed data
	 */
	private function changeFeed() {
		if (!isset($_REQUEST['id'])) return;

		$id = fix_id($_REQUEST['id']);
		$manager = NewsFeedManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('feed_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $item->id,
					'group'			=> $item->group,
					'news_count'	=> $item->news_count,
					'title'			=> $item->title,
					'description'	=> $item->description,
					'active'		=> $item->active,
					'form_action'	=> backend_UrlMake($this->name, 'feed_save'),
					'cancel_action'	=> window_Close('news_feeds_change')
				);

		$template->registerTagHandler('_group_list', $this, 'tag_GroupList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save feed data
	 */
	private function saveFeed() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = NewsFeedManager::getInstance();

		$data = array(
					'group'			=> fix_id($_REQUEST['group']),
					'news_count'	=> empty($_REQUEST['news_count']) ? 10 : fix_id($_REQUEST['news_count']),
					'title'			=> escape_chars($this->getMultilanguageField('title')),
					'description'	=> escape_chars($this->getMultilanguageField('description')),
					'active'		=> fix_id($_REQUEST['active'])
				);

		if (is_null($id)) {
			$manager->insertData($data);
			$window = 'news_feeds_add';
		} else {
			$manager->updateData($data, array('id' => $id));
			$window = 'news_feeds_change';
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_feed_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('news_feeds'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Feed removal confirmation form
	 */
	private function deleteFeed() {
		global $language;

		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = NewsFeedManager::getInstance();

		$item = $manager->getSingleItem(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_feed_delete"),
					'name'			=> $item->title[$language],
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'news_feeds_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'feed_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('news_feeds_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Perform feed removal
	 */
	private function deleteFeed_Commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = NewsFeedManager::getInstance();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_news_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('news_feeds_delete').";"
									.window_ReloadContent('news')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Add feed links to site head tag
	 */
	private function createFeedLinks() {
		global $language;

		if (!class_exists('head_tag')) return;

		$head = head_tag::getInstance();
		$manager = NewsFeedManager::getInstance();

		$items = $manager->getItems(
							$manager->getFieldNames(),
							array('active' => 1)
						);

		if (count($items) > 0)
			foreach ($items as $item) {
				$url = url_Make(
							'show_feed',
							$this->name,
							array('id', $item->id),
							array('language', $language)
						);

				$head->addTag(
							'link',
							array(
								'href'	=> $url,
								'title'	=> $item->title[$language],
								'rel'	=> 'alternate',
								'type'	=> 'application/rss+xml'
							)
						);
			}
	}

	/**
	 * News tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_News($tag_params, $children) {
		$id = isset($tag_params['id']) ? fix_id($tag_params['id']) : null;
		$manager = NewsManager::getInstance();
		$admin_manager = AdministratorManager::getInstance();

		if (!is_null($id))
			$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id)); else
			$item = $manager->getSingleItem($manager->getFieldNames(), array(), array('timestamp'), False);

		$template = $this->loadTemplate($tag_params, 'news.xml');

		if (is_object($item)) {
			$timestamp = strtotime($item->timestamp);
			$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
			$time = date($this->getLanguageConstant('format_time_short'), $timestamp);

			$params = array(
						'id'		=> $item->id,
						'time'		=> $time,
						'date'		=> $date,
						'author'	=> $admin_manager->getItemValue(
															'fullname',
															array('id' => $item->author)
														),
						'title'		=> $item->title,
						'content'	=> $item->content,
						'visible'	=> $item->visible
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * News list tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_NewsList($tag_params, $children) {
		$limit = isset($tag_params['limit']) ? fix_id($tag_params['limit']) : null;
		$group = isset($tag_params['group']) ? escape_chars($tag_params['group']) : null;
		$conditions = array();

		if (!isset($tag_params['show_invisible']))
			$conditions['visible'] = 1;

		$manager = NewsManager::getInstance();
		$membership_manager = NewsMembershipManager::getInstance();
		$group_manager = NewsGroupManager::getInstance();
		$admin_manager = AdministratorManager::getInstance();

		if (!is_null($group)) {
			// group is set, get item ids and feed them to conditions list
			if (!is_numeric($group))
				$group_id = $group_manager->getItemValue('id', array('text_id' => $group)); else
				$group_id = $group;

			$item_list = $membership_manager->getItems(
												array('news'),
												array('group' => $group_id)
											);

			if (count($item_list) > 0) {
				$conditions['id'] = array();

				foreach($item_list as $item)
					$conditions['id'][] = $item->news;
			} else {
				$conditions['id'] = '-1';
			}
		}

		// get items from database
		$items = $manager->getItems(
							$manager->getFieldNames(),
							$conditions,
							array('timestamp'),
							false,
							$limit
						);

		// create template
		$template = $this->loadTemplate($tag_params, 'news_list_item.xml');

		// parse items
		if (count($items) > 0)
			foreach($items as $item) {
				$timestamp = strtotime($item->timestamp);
				$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
				$time = date($this->getLanguageConstant('format_time_short'), $timestamp);

				$params = array(
							'id'			=> $item->id,
							'time'			=> $time,
							'date'			=> $date,
							'timestamp'		=> $timestamp,
							'author'		=> $admin_manager->getItemValue(
																'fullname',
																array('id' => $item->author)
															),
							'title'			=> $item->title,
							'content'		=> $item->content,
							'visible'		=> $item->visible,
							'item_change'	=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														'news_change', 	// window id
														490,			// width
														$this->getLanguageConstant('title_news_change'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'news_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> url_MakeHyperlink(
													$this->getLanguageConstant('delete'),
													window_Open(
														'news_delete', 	// window id
														390,			// width
														$this->getLanguageConstant('title_news_delete'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'news_delete'),
															array('id', $item->id)
														)
													)
												)
						);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * News group tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Group($tag_params, $children) {
		$manager = NewsGroupManager::getInstance();

		if (isset($_REQUEST['id'])) {
			// Id is specified
			$item = $manager->getSingleItem(
									$manager->getFieldNames(),
									array('id' => fix_id($_REQUEST['id']))
								);
		} else {
			// no Id was specified, select first group
			$item = $manager->getSingleItem($manager->getFieldNames(), array());
		}

		// create template
		$template = $this->loadTemplate($tag_params, 'group.xml');

		if (is_object($item)) {
			$params = array(
						'id'		=> $item->id,
						'text_id'	=> $item->text_id,
						'title'		=> $item->title,
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * News group list tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_GroupList($tag_params, $children) {
		$limit = isset($tag_params['limit']) ? fix_id($tag_params['limit']) : null;
		$manager = NewsGroupManager::getInstance();

		// get items from database
		$items = $manager->getItems(
							$manager->getFieldNames(),
							array(),
							array(),
							true,
							$limit
						);

		// create template
		$template = $this->loadTemplate($tag_params, 'group_list_item.xml');

		// parse items
		if (count($items) > 0)
			foreach($items as $item) {
				$params = array(
							'id'			=> $item->id,
							'text_id'		=> $item->text_id,
							'title'			=> $item->title,
							'selected'		=> isset($tag_params['selected']) ? $tag_params['selected'] : null,
							'item_change'	=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														'news_group_change', 	// window id
														390,					// width
														$this->getLanguageConstant('title_news_group_change'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'group_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> url_MakeHyperlink(
													$this->getLanguageConstant('delete'),
													window_Open(
														'news_group_delete', 	// window id
														390,					// width
														$this->getLanguageConstant('title_news_group_delete'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'group_delete'),
															array('id', $item->id)
														)
													)
												),
							'item_members'	=> url_MakeHyperlink(
													$this->getLanguageConstant('items'),
													window_Open(
														'news_group_items', 	// window id
														390,					// width
														$this->getLanguageConstant('title_news_group_items'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'group_items'),
															array('id', $item->id)
														)
													)
												)
						);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Tag handler used to display items within a certain group
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function tag_GroupItems($params, $children) {
		if (!isset($params['group'])) return;

		$group = fix_id($params['group']);
		$news_manager = NewsManager::getInstance();
		$membership_manager = NewsMembershipManager::getInstance();

		$memberships = $membership_manager->getItems(
												array('news'),
												array('group' => $group)
											);

		$news_ids = array();
		if (count($memberships) > 0)
			foreach($memberships as $membership)
				$news_ids[] = $membership->news;

		$items = $news_manager->getItems(array('id', 'title'), array());

		// create template
		$template = new TemplateHandler('group_news_item.xml', $this->path.'templates/');

		if (count($items) > 0)
			foreach($items as $item) {
				$params = array(
								'id'		=> $item->id,
								'in_group'	=> in_array($item->id, $news_ids) ? 1 : 0,
								'title'		=> $item->title,
							);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Tag handler for feed tag
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Feed($tag_params, $children) {
		$id = isset($tag_params['id']) ? $tag_params['id'] : null;
		if (is_null($id)) $id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		if (is_null($id)) return;

		$manager = NewsFeedManager::getInstance();
		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			if (!$item->active) return;  // if item is not active, just exit

			// create template parser
			$template = new TemplateHandler('feed_base.xml', $this->path.'templates/');

			// get build date
			$membership_manager = NewsMembershipManager::getInstance();
			$membership_list = $membership_manager->getItems(array('news'), array('group' => $item->group));

			// get guild date only if there are news items in group
			if (count($membership_list) > 0) {
				$id_list = array();

				foreach($membership_list as $membership)
					$id_list[] = $membership->news;

				$news_manager = NewsManager::getInstance();
				$news = $news_manager->getSingleItem(
													array('timestamp'),
													array('id' => $id_list),
													array('timestamp'),
													false
												);

				if (is_object($news))
					$build_date = strtotime($news->timestamp);
			} else {
				// drop to default build date, 1970 ^^
				$build_date = 0;
			}

			// prepare params
			$params = array(
						'title'			=> $item->title,
						'description'	=> $item->description,
						'group'			=> $item->group,
						'news_count'	=> $item->news_count,
						'build_date'	=> $build_date
					);

			$template->registerTagHandler('_news_list', $this, 'tag_NewsList');
			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Tag handler for feed list
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function tag_FeedList($params, $children) {
		$manager = NewsFeedManager::getInstance();
		$group_manager = NewsGroupManager::getInstance();

		$items = $manager->getItems($manager->getFieldNames(), array());

		// create template parser
		$template = new TemplateHandler('feed_list_item.xml', $this->path.'templates/');

		if (count($items) > 0)
			foreach($items as $item) {
				$group = $group_manager->getSingleItem(array('title'), array('id' => $item->group));

				$params = array(
							'id'			=> $item->id,
							'group'			=> $item->group,
							'news_count'	=> $item->news_count,
							'title'			=> $item->title,
							'group_title'	=> $group->title,
							'description'	=> $item->description,
							'active'		=> $item->active,
							'active_char'	=> $item->active ? CHAR_CHECKED : CHAR_UNCHECKED,
							'item_change'	=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														'news_feeds_change', 	// window id
														390,					// width
														$this->getLanguageConstant('title_news_feed_change'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'feed_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> url_MakeHyperlink(
													$this->getLanguageConstant('delete'),
													window_Open(
														'news_feeds_delete', 	// window id
														390,					// width
														$this->getLanguageConstant('title_news_feed_delete'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'feed_delete'),
															array('id', $item->id)
														)
													)
												)
						);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Print json object for specified news
	 */
	private function json_News() {
		define('_OMIT_STATS', 1);

		$manager = NewsManager::getInstance();
		$admin_manager = AdministratorManager::getInstance();

		if (isset($_REQUEST['id'])) {
			// id was specified, fetch the news
			$item = $manager->getSingleItem(
								$manager->getFieldNames(),
								array('id' => fix_id($_REQUEST['id']))
							);

		} else {
			// no news id has been specified, grab the latest
			$item = $manager->getSingleItem(
								$manager->getFieldNames(),
								array(),
								array('id'),
								False
							);
		}

		if (is_object($item)) {
			$timestamp = strtotime($item->timestamp);
			$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
			$time = date($this->getLanguageConstant('format_time_short'), $timestamp);

			$result = array(
						'id'			=> $item->id,
						'time'			=> $time,
						'date'			=> $date,
						'author'		=> $admin_manager->getItemValue(
															'fullname',
															array('id' => $item->author)
														),
						'title'			=> $item->title,
						'content'		=> $item->content,
						'visible'		=> $item->visible,
						'error'			=> false,
						'error_message'	=> ''
					);
		} else {
			$result = array(
						'error'			=> true,
						'error_message'	=> $this->getLanguageConstant('message_json_error_object')
					);
		}

		print json_encode($result);
	}

	/**
	 * Print json object containing news list
	 */
	private function json_NewsList() {
		define('_OMIT_STATS', 1);

	}

	/**
	 * Print json object containing news group
	 */
	private function json_Group() {
		define('_OMIT_STATS', 1);

	}

	/**
	 * Print json object containing news group list
	 */
	private function json_GroupList() {
		define('_OMIT_STATS', 1);

	}
}


class NewsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('news');

		$this->addProperty('id', 'int');
		$this->addProperty('timestamp', 'timestamp');
		$this->addProperty('author', 'int');
		$this->addProperty('title', 'ml_varchar');
		$this->addProperty('content', 'ml_text');
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


class NewsMembershipManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('news_membership');

		$this->addProperty('id', 'int');
		$this->addProperty('news', 'int');
		$this->addProperty('group', 'int');
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


class NewsGroupManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('news_groups');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('title', 'ml_varchar');
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


class NewsFeedManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('news_feeds');

		$this->addProperty('id', 'int');
		$this->addProperty('group', 'int');
		$this->addProperty('news_count', 'int');
		$this->addProperty('title', 'ml_varchar');
		$this->addProperty('description', 'ml_text');
		$this->addProperty('active', 'boolean');
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
