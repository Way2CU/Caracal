<?php

/**
 * News Module
 *
 * Support for news and RSS feeds.
 *
 * Author: Mladen Mijatov
 */
use Core\Events;
use Core\Module;


class news extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// connect events
		Events::connect('head-tag', 'before-print', 'add_tags', $this);
		Events::connect('backend', 'add-menu-items', 'add_menu_items', $this);
		Events::connect('search', 'get-results', 'get_search_results', $this);
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
	public function transfer_control($params, $children) {
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

				case 'add_to_title':
					$manager = NewsManager::get_instance();
					$manager->add_property_to_title('title', array('id'), $params);
					break;

				case 'add_group_to_title':
					$manager = NewsGroupManager::get_instance();
					$manager->add_property_to_title('title', array('id', 'text_id'), $params);
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
	public function initialize() {
		global $db;

		$list = Language::get_languages(false);

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
		$db->query($sql);

		// News Membership
		$sql = "
			CREATE TABLE IF NOT EXISTS `news_membership` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`news` int(11) NOT NULL,
				`group` int(11) NOT NULL,
				PRIMARY KEY (`id`),
				KEY `group` (`group`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

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
		$db->query($sql);

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
		$db->query($sql);

	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
		global $db;

		$tables = array('news', 'news_membership', 'news_groups', 'news_feeds');
		$db->drop_tables($tables);
	}

	/**
	 * Include scripts and styles.
	 */
	public function add_tags() {
		$head_tag = head_tag::get_instance();
		$head_tag->add_tag('script', array('src'=>URL::from_file_path($this->path.'include/news_system.js'), 'type'=>'text/javascript'));
		$this->createFeedLinks();
	}

	/**
	 * Add items to backend menu.
	 */
	public function add_menu_items() {
		$backend = backend::get_instance();

		$news_menu = new backend_MenuItem(
				$this->get_language_constant('menu_news'),
				$this->path.'images/icon.svg',
				'javascript:void(0);',
				5  // level
			);

		$news_menu->addChild(null, new backend_MenuItem(
							$this->get_language_constant('menu_add_news'),
							$this->path.'images/add_news.svg',
							window_Open( // on click open window
										'news_add',
										490,
										$this->get_language_constant('title_news_add'),
										true, true,
										backend_UrlMake($this->name, 'news_add')
									),
							5  // level
						));
		$news_menu->addSeparator(5);

		$news_menu->addChild(null, new backend_MenuItem(
				$this->get_language_constant('menu_manage_news'),
				$this->path.'images/manage_news.svg',
				window_Open( // on click open window
							'news',
							520,
							$this->get_language_constant('title_manage_news'),
							true, true,
							backend_UrlMake($this->name, 'news')
						),
				5  // level
			));

		$news_menu->addChild(null, new backend_MenuItem(
				$this->get_language_constant('menu_manage_groups'),
				$this->path.'images/manage_groups.svg',
				window_Open( // on click open window
							'news_groups',
							580,
							$this->get_language_constant('title_manage_groups'),
							true, true,
							backend_UrlMake($this->name, 'groups')
						),
				5  // level
			));

		$news_menu->addSeparator(5);

		$news_menu->addChild(null, new backend_MenuItem(
				$this->get_language_constant('menu_news_feeds'),
				$this->path.'images/rss.svg',
				window_Open( // on click open window
							'news_feeds',
							700,
							$this->get_language_constant('title_manage_feeds'),
							true, true,
							backend_UrlMake($this->name, 'feeds')
						),
				5  // level
			));

		$backend->addMenu($this->name, $news_menu);
	}

	/**
	 * Get search results when asked by search module
	 *
	 * @param array $module_list
	 * @param string $query
	 * @param integer $threshold
	 * @return array
	 */
	public function get_search_results($module_list, $query, $threshold) {
		global $language;

		// make sure shop is in list of modules requested
		if (!in_array($this->name, $module_list))
			return array();

		// don't bother searching for empty query string
		if (empty($query))
			return array();

		// initialize managers and data
		$manager = NewsManager::get_instance();
		$result = array();
		$conditions = array(
				'visible' => 1
			);
		$query = mb_strtolower($query);
		$query_words = mb_split('\s', $query);
		$query_count = count($query_words);

		// get all items and process them
		$items = $manager->get_items(array('id', 'title', 'content'), $conditions);

		// make sure we have items to search through
		if (count($items) == 0)
			return $result;

		// comparison function
		$compare = function($a, $b) {
			$score = String\Distance\Jaro::get($a, $b);

			if ($score >= 0.9)
				$result = 0; else
				$result = strcmp($a, $b);

			return $result;
		};

		foreach ($items as $item) {
			$title = mb_split('\s', mb_strtolower($item->title[$language]));
			$content = mb_split('\s', mb_strtolower($item->content[$language]));
			$score = 0;
			$title_matches = 0;
			$content_matches = 0;

			// count number of matching words
			$title_matches = count(array_uintersect($query_words, $title, $compare));
			$content_matches = count(array_uintersect($query_words, $content, 'strcmp'));

			// calculate individual scores according to their importance
			$title_score = 100 * ($title_matches / $query_count);
			$content_score = 50 * ($content_matches / $query_count);

			// calculate final score
			$score = (($title_score + $content_score) * 100) / (100 + 50);

			// add item to result list
			if ($score >= $threshold)
				$result[] = array(
					'score'			=> $score,
					'title'			=> $item->title,
					'description'	=> limit_words($item->content[$language], 200),
					'id'			=> $item->id,
					'type'			=> 'item',
					'module'		=> $this->name
				);
		}

		return $result;
	}

	/**
	 * Show news management form
	 */
	private function showNews() {
		$template = new TemplateHandler('news_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new'		=> URL::make_hyperlink(
										$this->get_language_constant('add_news'),
										window_Open( // on click open window
											'news_add',
											490,
											$this->get_language_constant('title_news_add'),
											true, true,
											backend_UrlMake($this->name, 'news_add')
										)
									),
					'link_groups'	=> URL::make_hyperlink(
										$this->get_language_constant('groups'),
										window_Open( // on click open window
											'news_groups',
											580,
											$this->get_language_constant('title_manage_news'),
											true, true,
											backend_UrlMake($this->name, 'groups')
										)
									)
					);

		$template->register_tag_handler('cms:news_list', $this, 'tag_NewsList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for adding news item
	 */
	private function addNews() {
		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'news_save'),
				);

		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Change news
	 */
	private function changeNews() {
		if (!isset($_REQUEST['id'])) return;

		$id = fix_id($_REQUEST['id']);
		$manager = NewsManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		$template = new TemplateHandler('change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'id'		=> $item->id,
					'title'		=> $item->title,
					'content'	=> $item->content,
					'visible'	=> $item->visible,
					'form_action'	=> backend_UrlMake($this->name, 'news_save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save changed or new data
	 */
	private function saveNews() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = NewsManager::get_instance();

		$data = array(
					'author'	=> $_SESSION['uid'],
					'title'		=> $this->get_multilanguage_field('title'),
					'content'	=> $this->get_multilanguage_field('content'),
					'visible'	=> fix_id($_REQUEST['visible'])
				);

		if (is_null($id)) {
			$manager->insert_item($data);
			$window = 'news_add';
		} else {
			$manager->update_items($data, array('id' => $id));
			$window = 'news_change';
		}

		// if group has been selected and field exists
		if (isset($_REQUEST['group']) && !empty($_REQUEST['group'])) {
			$membership_manager = NewsMembershipManager::get_instance();
			$group = fix_id($_REQUEST['group']);
			$news_id = $manager->get_inserted_id();

			if (!empty($news_id))
				$membership_manager->insert_item(array(
											'news'	=> $news_id,
											'group'	=> $group
										));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_news_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('news'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Print news confirmation dialog before deleting
	 */
	private function deleteNews() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = NewsManager::get_instance();

		$item = $manager->get_single_item(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant("message_news_delete"),
					'name'			=> $item->title[$language],
					'yes_text'		=> $this->get_language_constant("delete"),
					'no_text'		=> $this->get_language_constant("cancel"),
					'yes_action'	=> window_LoadContent(
											'news_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'news_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('news_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Delete news from database
	 */
	private function deleteNews_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = NewsManager::get_instance();
		$membership_manager = NewsMembershipManager::get_instance();

		$manager->delete_items(array('id' => $id));
		$membership_manager->delete_items(array('news' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant("message_news_deleted"),
					'button'	=> $this->get_language_constant("close"),
					'action'	=> window_Close('news_delete').";"
									.window_ReloadContent('news')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show group list
	 */
	private function showGroups() {
		$template = new TemplateHandler('group_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new'		=> URL::make_hyperlink(
										$this->get_language_constant('add_group'),
										window_Open( // on click open window
											'news_group_add',
											390,
											$this->get_language_constant('title_news_group_add'),
											true, true,
											backend_UrlMake($this->name, 'group_add')
										)
									),
					);

		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Add new news group
	 */
	private function addGroup() {
		$template = new TemplateHandler('group_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'group_save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Change group data
	 */
	private function changeGroup() {
		if (!isset($_REQUEST['id'])) return;

		$id = fix_id($_REQUEST['id']);
		$manager = NewsGroupManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		$template = new TemplateHandler('group_change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		if (is_object($item)) {
			$params = array(
						'id'			=> $item->id,
						'text_id'		=> $item->text_id,
						'title'			=> $item->title,
						'form_action'	=> backend_UrlMake($this->name, 'group_save'),
					);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Save group data
	 */
	private function saveGroup() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = NewsGroupManager::get_instance();

		$data = array(
					'text_id'	=> fix_chars($_REQUEST['text_id']),
					'title'		=> $this->get_multilanguage_field('title')
				);

		if (is_null($id)) {
			$manager->insert_item($data);
			$window = 'news_group_add';
		} else {
			$manager->update_items($data, array('id' => $id));
			$window = 'news_group_change';
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_group_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('news_groups'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Group removal confirmation dialog
	 */
	private function deleteGroup() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = NewsGroupManager::get_instance();

		$item = $manager->get_single_item(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant("message_news_delete"),
					'name'			=> $item->title[$language],
					'yes_text'		=> $this->get_language_constant("delete"),
					'no_text'		=> $this->get_language_constant("cancel"),
					'yes_action'	=> window_LoadContent(
											'news_group_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'group_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('news_group_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Remove group from database
	 */
	private function deleteGroup_Commit() {
		if (!isset($_REQUEST['id'])) return;

		$id = fix_chars($_REQUEST['id']);
		$manager = NewsGroupManager::get_instance();
		$membership_manager = NewsMembershipManager::get_instance();
		$feed_manager = NewsFeedManager::get_instance();

		$manager->delete_items(array('id' => $id));
		$membership_manager->delete_items(array('group' => $id));
		$feed_manager->delete_items(array('group' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant("message_group_deleted"),
					'button'	=> $this->get_language_constant("close"),
					'action'	=> window_Close('news_group_delete').";"
									.window_ReloadContent('news_groups')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show group items edit form
	 */
	private function groupItems() {
		$group_id = fix_id($_REQUEST['id']);

		$template = new TemplateHandler('group_news.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'group'			=> $group_id,
					'form_action'	=> backend_UrlMake($this->name, 'group_items_save'),
				);

		$template->register_tag_handler('_group_items', $this, 'tag_GroupItems');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save group items
	 */
	private function groupItems_Save() {
		$group = fix_id($_REQUEST['group']);
		$membership_manager = NewsMembershipManager::get_instance();

		// fetch all ids being set to specific group
		$news_ids = array();
		foreach ($_REQUEST as $key => $value) {
			if (substr($key, 0, 8) == 'news_id_' && $value == 1)
				$news_ids[] = fix_id(substr($key, 8));
		}

		// remove old memberships
		$membership_manager->delete_items(array('group' => $group));

		// save new memberships
		foreach ($news_ids as $id)
			$membership_manager->insert_item(array(
											'news'	=> $id,
											'group'	=> $group
										));

		// display message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant("message_group_items_updated"),
					'button'	=> $this->get_language_constant("close"),
					'action'	=> window_Close('news_group_items')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show news feed management form
	 */
	private function showFeeds() {
		$template = new TemplateHandler('feed_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new'		=> URL::make_hyperlink(
										$this->get_language_constant('add_feed'),
										window_Open( // on click open window
											'news_feeds_add',
											390,
											$this->get_language_constant('title_news_feed_add'),
											true, true,
											backend_UrlMake($this->name, 'feed_add')
										)
									),
					);

		$template->register_tag_handler('cms:feed_list', $this, 'tag_FeedList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Add new feed
	 */
	private function addFeed() {
		$template = new TemplateHandler('feed_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'feed_save'),
				);

		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Change feed data
	 */
	private function changeFeed() {
		if (!isset($_REQUEST['id'])) return;

		$id = fix_id($_REQUEST['id']);
		$manager = NewsFeedManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		$template = new TemplateHandler('feed_change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'id'			=> $item->id,
					'group'			=> $item->group,
					'news_count'	=> $item->news_count,
					'title'			=> $item->title,
					'description'	=> $item->description,
					'active'		=> $item->active,
					'form_action'	=> backend_UrlMake($this->name, 'feed_save'),
				);

		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save feed data
	 */
	private function saveFeed() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = NewsFeedManager::get_instance();

		$data = array(
					'group'			=> fix_id($_REQUEST['group']),
					'news_count'	=> empty($_REQUEST['news_count']) ? 10 : fix_id($_REQUEST['news_count']),
					'title'			=> $this->get_multilanguage_field('title'),
					'description'	=> $this->get_multilanguage_field('description'),
					'active'		=> fix_id($_REQUEST['active'])
				);

		if (is_null($id)) {
			$manager->insert_item($data);
			$window = 'news_feeds_add';
		} else {
			$manager->update_items($data, array('id' => $id));
			$window = 'news_feeds_change';
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_feed_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('news_feeds'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Feed removal confirmation form
	 */
	private function deleteFeed() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = NewsFeedManager::get_instance();

		$item = $manager->get_single_item(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant("message_feed_delete"),
					'name'			=> $item->title[$language],
					'yes_text'		=> $this->get_language_constant("delete"),
					'no_text'		=> $this->get_language_constant("cancel"),
					'yes_action'	=> window_LoadContent(
											'news_feeds_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'feed_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('news_feeds_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Perform feed removal
	 */
	private function deleteFeed_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = NewsFeedManager::get_instance();

		$manager->delete_items(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant("message_news_deleted"),
					'button'	=> $this->get_language_constant("close"),
					'action'	=> window_Close('news_feeds_delete').";"
									.window_ReloadContent('news')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Add feed links to site head tag
	 */
	private function createFeedLinks() {
		global $language;

		if (!ModuleHandler::is_loaded('head_tag'))
			return;

		$head = head_tag::get_instance();
		$manager = NewsFeedManager::get_instance();

		$items = $manager->get_items(
							$manager->get_field_names(),
							array('active' => 1)
						);

		if (count($items) > 0)
			foreach ($items as $item) {
				$url = URL::make_query(
							$this->name,
							'show_feed',
							array('id', $item->id),
							array('language', $language)
						);

				$head->add_tag(
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
		$manager = NewsManager::get_instance();
		$admin_manager = UserManager::get_instance();

		if (!is_null($id))
			$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id)); else
			$item = $manager->get_single_item($manager->get_field_names(), array(), array('timestamp'), False);

		$template = $this->load_template($tag_params, 'news.xml');
		$template->set_template_params_from_array($children);

		if (is_object($item)) {
			$timestamp = strtotime($item->timestamp);
			$date = date($this->get_language_constant('format_date_short'), $timestamp);
			$time = date($this->get_language_constant('format_time_short'), $timestamp);

			$params = array(
						'id'		=> $item->id,
						'time'		=> $time,
						'date'		=> $date,
						'author'	=> $admin_manager->get_item_value(
															'fullname',
															array('id' => $item->author)
														),
						'title'		=> $item->title,
						'content'	=> $item->content,
						'visible'	=> $item->visible
					);

			$template->restore_xml();
			$template->set_local_params($params);
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
		$group = isset($tag_params['group']) ? fix_chars($tag_params['group']) : null;
		$conditions = array();

		if (!isset($tag_params['show_invisible']))
			$conditions['visible'] = 1;

		$manager = NewsManager::get_instance();
		$membership_manager = NewsMembershipManager::get_instance();
		$group_manager = NewsGroupManager::get_instance();
		$admin_manager = UserManager::get_instance();

		if (!is_null($group)) {
			// group is set, get item ids and feed them to conditions list
			if (!is_numeric($group))
				$group_id = $group_manager->get_item_value('id', array('text_id' => $group)); else
				$group_id = $group;

			$item_list = $membership_manager->get_items(
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
		$items = $manager->get_items(
							$manager->get_field_names(),
							$conditions,
							array('timestamp'),
							false,
							$limit
						);

		// create template
		$template = $this->load_template($tag_params, 'news_list_item.xml');
		$template->set_template_params_from_array($children);

		// parse items
		if (count($items) > 0)
			foreach($items as $item) {
				$timestamp = strtotime($item->timestamp);
				$date = date($this->get_language_constant('format_date_short'), $timestamp);
				$time = date($this->get_language_constant('format_time_short'), $timestamp);

				$params = array(
							'id'			=> $item->id,
							'time'			=> $time,
							'date'			=> $date,
							'timestamp'		=> $timestamp,
							'author'		=> $admin_manager->get_item_value(
																'fullname',
																array('id' => $item->author)
															),
							'title'			=> $item->title,
							'content'		=> $item->content,
							'visible'		=> $item->visible,
							'item_change'	=> URL::make_hyperlink(
													$this->get_language_constant('change'),
													window_Open(
														'news_change', 	// window id
														490,			// width
														$this->get_language_constant('title_news_change'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'news_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> URL::make_hyperlink(
													$this->get_language_constant('delete'),
													window_Open(
														'news_delete', 	// window id
														390,			// width
														$this->get_language_constant('title_news_delete'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'news_delete'),
															array('id', $item->id)
														)
													)
												)
						);

				$template->restore_xml();
				$template->set_local_params($params);
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
		$manager = NewsGroupManager::get_instance();

		if (isset($_REQUEST['id'])) {
			// Id is specified
			$item = $manager->get_single_item(
									$manager->get_field_names(),
									array('id' => fix_id($_REQUEST['id']))
								);
		} else {
			// no Id was specified, select first group
			$item = $manager->get_single_item($manager->get_field_names(), array());
		}

		// create template
		$template = $this->load_template($tag_params, 'group.xml');
		$template->set_template_params_from_array($children);

		if (is_object($item)) {
			$params = array(
						'id'		=> $item->id,
						'text_id'	=> $item->text_id,
						'title'		=> $item->title,
					);

			$template->restore_xml();
			$template->set_local_params($params);
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
		$manager = NewsGroupManager::get_instance();

		// get items from database
		$items = $manager->get_items(
							$manager->get_field_names(),
							array(),
							array(),
							true,
							$limit
						);

		// create template
		$template = $this->load_template($tag_params, 'group_list_item.xml');
		$template->set_template_params_from_array($children);

		$selected = isset($tag_params['selected']) ? fix_chars($tag_params['selected']) : null;

		// parse items
		if (count($items) > 0)
			foreach($items as $item) {
				$params = array(
							'id'			=> $item->id,
							'text_id'		=> $item->text_id,
							'title'			=> $item->title,
							'selected'		=> $selected,
							'item_change'	=> URL::make_hyperlink(
													$this->get_language_constant('change'),
													window_Open(
														'news_group_change', 	// window id
														390,					// width
														$this->get_language_constant('title_news_group_change'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'group_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> URL::make_hyperlink(
													$this->get_language_constant('delete'),
													window_Open(
														'news_group_delete', 	// window id
														390,					// width
														$this->get_language_constant('title_news_group_delete'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'group_delete'),
															array('id', $item->id)
														)
													)
												),
							'item_members'	=> URL::make_hyperlink(
													$this->get_language_constant('items'),
													window_Open(
														'news_group_items', 	// window id
														390,					// width
														$this->get_language_constant('title_news_group_items'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'group_items'),
															array('id', $item->id)
														)
													)
												)
						);

				$template->restore_xml();
				$template->set_local_params($params);
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
		$news_manager = NewsManager::get_instance();
		$membership_manager = NewsMembershipManager::get_instance();

		$memberships = $membership_manager->get_items(
												array('news'),
												array('group' => $group)
											);

		$news_ids = array();
		if (count($memberships) > 0)
			foreach($memberships as $membership)
				$news_ids[] = $membership->news;

		$items = $news_manager->get_items(array('id', 'title'), array());

		// create template
		$template = new TemplateHandler('group_news_item.xml', $this->path.'templates/');

		if (count($items) > 0)
			foreach($items as $item) {
				$params = array(
								'id'		=> $item->id,
								'in_group'	=> in_array($item->id, $news_ids) ? 1 : 0,
								'title'		=> $item->title,
							);

				$template->restore_xml();
				$template->set_local_params($params);
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
		$id = isset($tag_params['id']) ? fix_id($tag_params['id']) : null;

		if (is_null($id))
			$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;

		if (is_null($id))
			return;

		$manager = NewsFeedManager::get_instance();
		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (is_object($item)) {
			if (!$item->active) return;  // if item is not active, just exit

			// create template parser
			$template = new TemplateHandler('feed_base.xml', $this->path.'templates/');

			// get build date
			$membership_manager = NewsMembershipManager::get_instance();
			$membership_list = $membership_manager->get_items(array('news'), array('group' => $item->group));

			// get guild date only if there are news items in group
			if (count($membership_list) > 0) {
				$id_list = array();

				foreach($membership_list as $membership)
					$id_list[] = $membership->news;

				$news_manager = NewsManager::get_instance();
				$news = $news_manager->get_single_item(
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

			$template->register_tag_handler('_news_list', $this, 'tag_NewsList');
			$template->restore_xml();
			$template->set_local_params($params);
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
		$manager = NewsFeedManager::get_instance();
		$group_manager = NewsGroupManager::get_instance();

		$items = $manager->get_items($manager->get_field_names(), array());

		// create template parser
		$template = new TemplateHandler('feed_list_item.xml', $this->path.'templates/');

		if (count($items) > 0)
			foreach($items as $item) {
				$group = $group_manager->get_single_item(array('title'), array('id' => $item->group));

				$params = array(
							'id'			=> $item->id,
							'group'			=> $item->group,
							'news_count'	=> $item->news_count,
							'title'			=> $item->title,
							'group_title'	=> $group->title,
							'description'	=> $item->description,
							'active'		=> $item->active,
							'active_char'	=> $item->active ? CHAR_CHECKED : CHAR_UNCHECKED,
							'item_change'	=> URL::make_hyperlink(
													$this->get_language_constant('change'),
													window_Open(
														'news_feeds_change', 	// window id
														390,					// width
														$this->get_language_constant('title_news_feed_change'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'feed_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> URL::make_hyperlink(
													$this->get_language_constant('delete'),
													window_Open(
														'news_feeds_delete', 	// window id
														390,					// width
														$this->get_language_constant('title_news_feed_delete'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'feed_delete'),
															array('id', $item->id)
														)
													)
												)
						);

				$template->restore_xml();
				$template->set_local_params($params);
				$template->parse();
			}
	}

	/**
	 * Print json object for specified news
	 */
	private function json_News() {
		$manager = NewsManager::get_instance();
		$admin_manager = UserManager::get_instance();

		if (isset($_REQUEST['id'])) {
			// id was specified, fetch the news
			$item = $manager->get_single_item(
								$manager->get_field_names(),
								array('id' => fix_id($_REQUEST['id']))
							);

		} else {
			// no news id has been specified, grab the latest
			$item = $manager->get_single_item(
								$manager->get_field_names(),
								array(),
								array('id'),
								False
							);
		}

		if (is_object($item)) {
			$timestamp = strtotime($item->timestamp);
			$date = date($this->get_language_constant('format_date_short'), $timestamp);
			$time = date($this->get_language_constant('format_time_short'), $timestamp);

			$result = array(
						'id'			=> $item->id,
						'time'			=> $time,
						'date'			=> $date,
						'author'		=> $admin_manager->get_item_value(
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
						'error_message'	=> $this->get_language_constant('message_json_error_object')
					);
		}

		print json_encode($result);
	}

	/**
	 * Print json object containing news list
	 */
	private function json_NewsList() {
	}

	/**
	 * Print json object containing news group
	 */
	private function json_Group() {
	}

	/**
	 * Print json object containing news group list
	 */
	private function json_GroupList() {
	}
}


class NewsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('news');

		$this->add_property('id', 'int');
		$this->add_property('timestamp', 'timestamp');
		$this->add_property('author', 'int');
		$this->add_property('title', 'ml_varchar');
		$this->add_property('content', 'ml_text');
		$this->add_property('visible', 'boolean');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
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

		$this->add_property('id', 'int');
		$this->add_property('news', 'int');
		$this->add_property('group', 'int');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
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

		$this->add_property('id', 'int');
		$this->add_property('text_id', 'varchar');
		$this->add_property('title', 'ml_varchar');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
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

		$this->add_property('id', 'int');
		$this->add_property('group', 'int');
		$this->add_property('news_count', 'int');
		$this->add_property('title', 'ml_varchar');
		$this->add_property('description', 'ml_text');
		$this->add_property('active', 'boolean');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}
