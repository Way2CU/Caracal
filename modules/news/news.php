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
											$this->getLanguageConstant('title_add_news'),
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
								490,
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
								490,
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
								490,
								$this->getLanguageConstant('title_manage_feeds'),
								true, true,
								backend_UrlMake($this->name, 'feeds')
							),
					5  // level
				));

			$backend->addMenu($this->name, $news_menu);
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
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($level, $params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'news':
					$this->showNews($level);
					break;
					
				case 'news_add':
					$this->addNews($level);
					break;
					
				case 'news_save':
					$this->saveNews($level);
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
	 * @param integer $level
	 */
	private function showNews($level) {
		$template = new TemplateHandler('news_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> url_MakeHyperlink(
										$this->getLanguageConstant('add_news'),
										window_Open( // on click open window
											'news_add',
											490,
											$this->getLanguageConstant('title_add_news'),
											true, true,
											backend_UrlMake($this->name, 'news_add')
										)
									),
					'link_groups'	=> url_MakeHyperlink(
										$this->getLanguageConstant('groups'),
										window_Open( // on click open window
											'news_groups',
											490,
											$this->getLanguageConstant('title_groups'),
											true, true,
											backend_UrlMake($this->name, 'groups')
										)
									)
					);

		$template->registerTagHandler('_news_list', &$this, 'tag_NewsList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}
	
	/**
	 * Show form for adding news item
	 * 
	 * @param integer $level
	 */
	private function addNews($level) {
		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'news_save'),
					'cancel_action'	=> window_Close('news_add')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);		
	}
	
	/**
	 * Save changed or new data

	 * @param integer $level
	 */
	private function saveNews($level) {
		
	}
	
	/**
	 * News tag handler
	 * 
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_News($level, $tag_params, $children) {
		$id = isset($tag_params['id']) ? fix_id($tag_params['id']) : null;
		$manager = NewsManager::getInstance();
		$admin_manager = AdministratorManager::getInstance(); 
		
		if (!is_null($id))
			$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id)); else
			$item = $manager->getSingleItem($manager->getFieldNames(), array(), array('timestamp'), False);
		
		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('image.xml', $this->path.'templates/');
		}	

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
			$template->parse($level);					
		}
	}
	
	/**
	 * News list tag handler
	 * 
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_NewsList($level, $tag_params, $children) {
		$limit = isset($tag_params['limit']) ? fix_id($tag_params['limit']) : null;
		$group = isset($tag_params['group']) ? escape_chars($tag_params['group']) : null;
		$conditions = array();
		
		$manager = NewsManager::getInstance();
		$membership_manager = NewsMembershipManager::getInstance();
		$group_manager = NewsGroupManager::getInstance();
		$admin_manager = AdministratorManager::getInstance(); 
		
		if (!is_null($group)) {
			// group is set, get item ids and feed them to conditions list
			$group_id = $group_manager->getItemValue('id', array('text_id' => $group));
			
			$item_list = $membership_manager->getItems(
												array('news'), 
												array('group' => $group_id)
											);

			if (count($item_list) > 0) {
				$conditions['id'] = array();
				
				foreach($item_list as $item)
					$conditions['id'][] = $item->news;
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
		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('images_list_item.xml', $this->path.'templates/');
		}

		// parse items
		if (count($items) > 0) 
			foreach($items as $item) {
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
				$template->parse($level);	
			}			
	}
	
	/**
	 * News group tag handler
	 * 
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Group($level, $tag_params, $children) {
		
	}
	
	/**
	 * News group list tag handler
	 * 
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_GroupList($level, $tag_params, $children) {
		
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