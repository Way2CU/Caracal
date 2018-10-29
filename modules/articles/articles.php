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

use Core\Events;
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

		// connect to search module
		Events::connect('search', 'get-results', 'get_search_results', $this);
		Events::connect('backend', 'add-menu-items', 'add_menu_items', $this);
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
	 * @param string $action
	 * @param integer $level
	 */
	public function transfer_control($params, $children) {
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

				case 'add_to_title':
					$manager = Modules\Articles\Manager::get_instance();
					$manager->add_property_to_title('title', array('id', 'text_id'), $params);
					break;

				case 'add_group_to_title':
					$manager = Modules\Articles\GroupManager::get_instance();
					$manager->add_property_to_title('title', array('id', 'text_id'), $params);
					break;

				case 'json_article':
					$this->json_Article();
					break;

				case 'json_article_list':
					$this->json_ArticleList();
					break;

				case 'json_group':
					$this->json_Group();
					break;

				case 'json_group_list':
					$this->json_GroupList();
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
	public function initialize() {
		global $db;

		// create tables
		$file_list = array('articles.sql', 'groups.sql', 'votes.sql');
		foreach ($file_list as $file_name) {
			$sql = Query::load_file($file_name, $this);
			$db->query($sql);
		}
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
		global $db;

		$tables = array('articles', 'article_group', 'article_votes');
		$db->drop_tables($tables);
	}

	/**
	 * Add items to backend menu.
	 */
	public function add_menu_items() {
		$backend = backend::get_instance();

		$articles_menu = new backend_MenuItem(
				$this->get_language_constant('menu_articles'),
				$this->path.'images/icon.svg',
				'javascript:void(0);',
				$level=5
			);

		$articles_menu->addChild('', new backend_MenuItem(
							$this->get_language_constant('menu_articles_new'),
							$this->path.'images/new_article.svg',

							window_Open( // on click open window
										'articles_new',
										730,
										$this->get_language_constant('title_articles_new'),
										true, true,
										backend_UrlMake($this->name, 'articles_new')
									),
							$level=5
						));
		$articles_menu->addSeparator(5);

		$articles_menu->addChild('', new backend_MenuItem(
							$this->get_language_constant('menu_articles_manage'),
							$this->path.'images/manage.svg',

							window_Open( // on click open window
										'articles',
										720,
										$this->get_language_constant('title_articles_manage'),
										true, true,
										backend_UrlMake($this->name, 'articles')
									),
							$level=5
						));
		$articles_menu->addChild('', new backend_MenuItem(
							$this->get_language_constant('menu_article_groups'),
							$this->path.'images/groups.svg',

							window_Open( // on click open window
										'article_groups',
										650,
										$this->get_language_constant('title_article_groups'),
										true, true,
										backend_UrlMake($this->name, 'groups')
									),
							$level=5
						));

		$backend->addMenu($this->name, $articles_menu);
	}

	/**
	 * Import provided data to system.
	 *
	 * @param array $data
	 * @param array $options
	 * @param object $export_file
	 */
	public function import_data(&$data, &$options, &$export_file) {
		$manager = Modules\Articles\Manager::get_instance();
		$group_manager = Modules\Articles\GroupManager::get_instance();

		// restore groups
		if (isset($data['groups'])) {
			// remove all groups
			$group_manager->delete_items(array());

			// insert groups from exports file
			foreach ($data['groups'] as $group) {
				$group_data = $group_manager->get_data_from_object($group);
				$group_manager->insert_item($group_data);
			}
		}

		// restore articles
		if (isset($data['articles'])) {
			// remove all articles
			$manager->delete_items(array());

			// insert articles from exports file
			foreach ($data['articles'] as $article) {
				$article_data = $manager->get_data_from_object($article);
				$manager->insert_item($article_data);
			}
		}
	}

	/**
	 * Prepare data for export.
	 *
	 * @param array $options
	 * @param object $export_file
	 * @return array
	 */
	public function export_data(&$options, &$export_file) {
		$result = array();
		$manager = Modules\Articles\Manager::get_instance();
		$group_manager = Modules\Articles\GroupManager::get_instance();

		// export data
		$result['articles'] = $manager->get_items($manager->get_field_names(), array());
		$result['groups'] = $group_manager->get_items($group_manager->get_field_names(), array());

		return $result;
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

		$result = array();
		$manager = Modules\Articles\Manager::get_instance();
		$conditions = array(
				'visible' => 1
			);
		$query = mb_strtolower($query);
		$query_words = mb_split('\s', $query);
		$query_count = count($query_words);

		// get all items
		$items = $manager->get_items(array('id', 'title', 'content'), $conditions);

		// make sure we have items to search through
		if (count($items) == 0)
			return array();

		// comparison function
		$compare = function($a, $b) {
			$score = String\Distance\Jaro::get($a, $b);

			if ($score >= 0.9)
				$result = 0; else
				$result = strcmp($a, $b);

			return $result;
		};

		// collect items and do preliminary preparations
		$maximums = array();
		$preliminary = array();

		foreach ($items as $item) {
			$title = mb_split('\s', mb_strtolower($item->title[$language]));
			$content_words = array_count_values(mb_split('\s', mb_strtolower($item->content[$language])));

			// collect matched content words
			$matched_words = array();
			foreach ($query_words as $word) {
				// store matched count for current query word
				if (array_key_exists($word, $content_words))
					$matched_words[$word] = $content_words[$word]; else
					$matched_words[$word] = 0;

				// store larger word count
				if ($maximums[$word] < $matched_words[$word])
					$maximums[$word] = $matched_words[$word];
			}

			// add item to result list
			$preliminary[] = array(
					'id'            => $item->id,
					'title'         => $item->title,
					'title_matches' => count(array_uintersect($query_words, $title, $compare)),
					'content'       => limit_words($item->content[$language], 200),
					'content_words' => $matched_words
				);
		}

		// maximum scores
		$title_max = 50;
		$content_max = 100;
		$max_score = $title_max + $content_max;
		$word_score = $content_max / $query_count;

		// prepare results
		foreach ($preliminary as $data) {
			// calculate individual scores according to their importance
			$title_score = $title_max * ($data['title_matches'] / $query_count);

			$content_score = 0;
			foreach ($data['content_words'] as $word => $count)
				$content_score += $word_score * ($count / $maximums[$word]);

			// calculate final score
			$score = (($title_score + $content_score) * 100) / $max_score;

			// add item to result list
			if ($score >= $threshold)
				$result[] = array(
						'score'   => $score,
						'id'      => $data['id'],
						'title'   => $data['title'],
						'content' => $data['content'],
						'type'    => 'article',
						'module'  => $this->name
					);
		}

		return $result;
	}

	/**
	 * Show administration form for articles
	 */
	private function showArticles() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->get_language_constant('new'),
										'articles_new', 730,
										$this->get_language_constant('title_articles_new'),
										true, false,
										$this->name,
										'articles_new'
									),
					);

		$template->register_tag_handler('cms:article_list', $this, 'tag_ArticleList');
		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Print input form for new article
	 */
	private function addArticle() {
		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');

		if (ModuleHandler::is_loaded('gallery')) {
			$gallery = gallery::get_instance();
			$template->register_tag_handler('cms:gallery_list', $gallery, 'tag_GroupList');
		}

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'articles_save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Display article for modification
	 */
	private function changeArticle() {
		$id = fix_id($_REQUEST['id']);
		$manager = Modules\Articles\Manager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (!is_object($item))
			return;

		$template = new TemplateHandler('change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');

		if (ModuleHandler::is_loaded('gallery')) {
			$gallery = gallery::get_instance();
			$template->register_tag_handler('cms:gallery_list', $gallery, 'tag_GroupList');
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
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save article data
	 */
	private function saveArticle() {
		$manager = Modules\Articles\Manager::get_instance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$text_id = escape_chars($_REQUEST['text_id']);
		$title = $this->get_multilanguage_field('title');
		$content = $this->get_multilanguage_field('content');
		$visible = $this->get_boolean_field('visible') ? 1 : 0;
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
			$manager->insert_item($data);
		} else {
			$window = 'articles_change';
			$manager->update_items($data,	array('id' => $id));
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_article_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).';'.window_ReloadContent('articles'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Print confirmation dialog before deleting article
	 */
	private function deleteArticle() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = Modules\Articles\Manager::get_instance();

		$item = $manager->get_single_item(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant('message_article_delete'),
					'name'			=> $item->title[$language],
					'yes_text'		=> $this->get_language_constant('delete'),
					'no_text'		=> $this->get_language_constant('cancel'),
					'yes_action'	=> window_LoadContent(
											'articles_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'articles_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('articles_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Delete article and print result message
	 */
	private function deleteArticle_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = Modules\Articles\Manager::get_instance();

		$manager->delete_items(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_article_deleted'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('articles_delete').';'.window_ReloadContent('articles')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show article groups
	 */
	private function showGroups() {
		$template = new TemplateHandler('group_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->get_language_constant('new'),
										'article_groups_new', 400,
										$this->get_language_constant('title_article_groups_new'),
										true, false,
										$this->name,
										'groups_new'
									),
					);

		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Print form for adding a new article group
	 */
	private function addGroup() {
		$template = new TemplateHandler('group_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'groups_save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Print form for changing article group data
	 */
	private function changeGroup() {
		$id = fix_id($_REQUEST['id']);
		$manager = Modules\Articles\GroupManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		$template = new TemplateHandler('group_change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'id'			=> $item->id,
					'text_id'		=> $item->text_id,
					'title'			=> unfix_chars($item->title),
					'description'	=> $item->description,
					'form_action'	=> backend_UrlMake($this->name, 'groups_save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Print confirmation dialog prior to group removal
	 */
	private function deleteGroup() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = Modules\Articles\GroupManager::get_instance();

		$item = $manager->get_single_item(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant('message_group_delete'),
					'name'			=> $item->title[$language],
					'yes_text'		=> $this->get_language_constant('delete'),
					'no_text'		=> $this->get_language_constant('cancel'),
					'yes_action'	=> window_LoadContent(
											'article_groups_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'groups_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('article_groups_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Perform removal of certain group
	 */
	private function deleteGroup_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = Modules\Articles\GroupManager::get_instance();
		$article_manager = Modules\Articles\Manager::get_instance();

		$manager->delete_items(array('id' => $id));
		$article_manager->update_items(array('group' => null), array('group' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_group_deleted'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('article_groups_delete').';'
									.window_ReloadContent('articles').';'
									.window_ReloadContent('article_groups')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save changed group data
	 */
	private function saveGroup() {
		$manager = Modules\Articles\GroupManager::get_instance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$text_id = escape_chars($_REQUEST['text_id']);
		$title = $this->get_multilanguage_field('title');
		$description = $this->get_multilanguage_field('description');

		$data = array(
					'text_id'		=> $text_id,
					'title'			=> $title,
					'description'	=> $description,
				);

		if (is_null($id)) {
			$window = 'article_groups_new';
			$manager->insert_item($data);
		} else {
			$window = 'article_groups_change';
			$manager->update_items($data,	array('id' => $id));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_group_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).';'.window_ReloadContent('article_groups'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Tag handler for printing article
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Article($tag_params, $children) {
		$manager = Modules\Articles\Manager::get_instance();
		$group_manager = Modules\Articles\GroupManager::get_instance();
		$admin_manager = UserManager::get_instance();
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
				$groups = $group_manager->get_items($group_manager->get_field_names(), array('text_id' => $group_names));

				if (count($groups) > 0)
					foreach ($groups as $group)
						$group_id_list []= $group->id;
			}

			if (count($group_id_list) > 0)
				$conditions['group'] = $group_id_list; else
				$conditions['group'] = -1;
		}

		// get single item from the database
		$item = $manager->get_single_item($manager->get_field_names(), $conditions, $order_by, $order_asc);

		// load template
		$template = $this->load_template($tag_params, 'article.xml');
		$template->set_template_params_from_array($children);
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:article_rating_image', $this, 'tag_ArticleRatingImage');

		// parse article
		if (is_object($item)) {
			$timestamp = strtotime($item->timestamp);
			$date = date($this->get_language_constant('format_date_short'), $timestamp);
			$time = date($this->get_language_constant('format_time_short'), $timestamp);

			$params = array(
						'id'			=> $item->id,
						'text_id'		=> $item->text_id,
						'group'			=> $item->group,
						'timestamp'		=> $item->timestamp,
						'date'			=> $date,
						'time'			=> $time,
						'title'			=> $item->title,
						'content'		=> $item->content,
						'author'		=> $admin_manager->get_item_value(
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

			$template->restore_xml();
			$template->set_local_params($params);
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
		global $section;

		$manager = Modules\Articles\Manager::get_instance();
		$group_manager = Modules\Articles\GroupManager::get_instance();
		$admin_manager = UserManager::get_instance();

		$conditions = array();
		$selected = -1;
		$order_by = array('id');
		$order_asc = true;
		$generate_sprite = false;

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
				$groups = $group_manager->get_items($group_manager->get_field_names(), array('text_id' => $group_names));

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

		if (isset($tag_params['generate_sprite']))
			$generate_sprite = $tag_params['generate_sprite'] == 1;

		// get items from manager
		$items = $manager->get_items($manager->get_field_names(), $conditions, $order_by, $order_asc, $limit);

		// load template
		$template = $this->load_template($tag_params, 'list_item.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:article', $this, 'tag_Article');
		$template->register_tag_handler('cms:article_rating_image', $this, 'tag_ArticleRatingImage');

		if (count($items) == 0)
			return;

		// collect associated images and generate sprite
		$sprite_image = '';

		if ($generate_sprite && ModuleHandler::is_loaded('gallery')) {
			$gallery = gallery::get_instance();
			$gallery_ids = array();

			// collect gallery ids
			foreach ($items as $item)
				$gallery_ids []= $item->id;

			// get image parameters
			$image_size = isset($tag_params['image_size']) ? fix_id($tag_params['image_size']) : null;
			$image_constraint = isset($tag_params['image_constraint']) ? fix_id($tag_params['image_constraint']) : null;
			$image_crop = isset($tag_params['image_crop']) ? fix_id($tag_params['image_crop']) : null;

			// generate sprite
			$sprite_image = $gallery->create_group_sprite_image(
					$gallery_ids,
					$image_size,
					$image_constraint,
					$image_crop
				);
		}

		// render template for each article
		foreach($items as $item) {
			$timestamp = strtotime($item->timestamp);
			$date = date($this->get_language_constant('format_date_short'), $timestamp);
			$time = date($this->get_language_constant('format_time_short'), $timestamp);

			$params = array(
						'id'          => $item->id,
						'text_id'     => $item->text_id,
						'group'       => $item->group,
						'timestamp'   => $item->timestamp,
						'date'        => $date,
						'time'        => $time,
						'title'       => $item->title,
						'content'     => $item->content,
						'author'      => $item->author,
						'gallery'     => $item->gallery,
						'visible'     => $item->visible,
						'views'       => $item->views,
						'votes_up'    => $item->votes_up,
						'votes_down'  => $item->votes_down,
						'rating'      => $this->getArticleRating($item, 10),
						'selected'    => $selected,
						'sprite'      => $sprite_image
					);

			if ($section == 'backend' || $section == 'backend_module') {
				$params['item_change'] = URL::make_hyperlink(
												$this->get_language_constant('change'),
												window_Open(
													'articles_change', 	// window id
													730,				// width
													$this->get_language_constant('title_articles_change'), // title
													false, false,
													URL::make_query(
														'backend_module',
														'transfer_control',
														array('module', $this->name),
														array('backend_action', 'articles_change'),
														array('id', $item->id)
													)
												));
				$params['item_delete'] = URL::make_hyperlink(
												$this->get_language_constant('delete'),
												window_Open(
													'articles_delete', 	// window id
													400,				// width
													$this->get_language_constant('title_articles_delete'), // title
													false, false,
													URL::make_query(
														'backend_module',
														'transfer_control',
														array('module', $this->name),
														array('backend_action', 'articles_delete'),
														array('id', $item->id)
													)
												));
			}

			// render template
			$template->restore_xml();
			$template->set_local_params($params);
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
			$manager = Modules\Articles\Manager::get_instance();

			$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

			$template = new TemplateHandler('rating_image.xml', $this->path.'templates/');
			$template->set_mapped_module($this->name);

			if (is_object($item)) {
				$url = URL::make_query(
							$this->name,
							'get_rating_image',
							array('type', $type),
							array('id', $id)
						);

				$params = array(
							'url'		=> $url,
							'rating'	=> round($this->getArticleRating($item, 5), 2)
						);

				$template->restore_xml();
				$template->set_local_params($params);
				$template->parse();
			}

		} else if (isset($_REQUEST['id'])) {
			// print image itself
			$id = fix_id($_REQUEST['id']);
			$type = isset($_REQUEST['type']) ? fix_id($_REQUEST['type']) : ImageType::Stars;
			$manager = Modules\Articles\Manager::get_instance();

			$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

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

		// we need at least one of ids in order to display article
		if (is_null($id) && is_null($text_id))
			return;

		$manager = Modules\Articles\GroupManager::get_instance();

		// load template
		$template = $this->load_template($tag_params, 'group.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:article_list', $this, 'tag_ArticleList');

		if (!is_null($id))
			$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id)); else
			$item = $manager->get_single_item($manager->get_field_names(), array('text_id' => $text_id));

		if (is_object($item)) {
			$params = array(
						'id'			=> $item->id,
						'text_id'		=> $item->text_id,
						'title'			=> $item->title,
						'description'	=> $item->description,
					);

			$template->restore_xml();
			$template->set_local_params($params);
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
		$manager = Modules\Articles\GroupManager::get_instance();
		$conditions = array();

		if (isset($tag_params['only_visible']) && $tag_params['only_visible'] == 'yes')
			$conditions['visible'] = 1;

		$items = $manager->get_items($manager->get_field_names(), $conditions);

		// load template
		$template = $this->load_template($tag_params, 'group_list_item.xml');
		$template->set_template_params_from_array($children);
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:article_list', $this, 'tag_ArticleList');

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
							'item_change'	=> URL::make_hyperlink(
													$this->get_language_constant('change'),
													window_Open(
														'article_groups_change', 	// window id
														400,						// width
														$this->get_language_constant('title_article_groups_change'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'groups_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> URL::make_hyperlink(
													$this->get_language_constant('delete'),
													window_Open(
														'article_groups_delete', 	// window id
														400,						// width
														$this->get_language_constant('title_article_groups_delete'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'groups_delete'),
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

	/**
	 * Generate JSON object for specified article
	 */
	private function json_Article() {
		global $language;

		$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : ImageType::Stars;
		$all_languages = isset($_REQUEST['all_languages']) && $_REQUEST['all_languages'] == 1;
		$order_by = array('id');
		$order_asc = true;
		$conditions = array();
		$result = array(
					'error'			=> false,
					'error_message'	=> ''
				);

		// collect query conditions
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
				$groups = $group_manager->get_items(
						$group_manager->get_field_names(),
						array('text_id' => $group_names)
					);

				if (count($groups) > 0)
					foreach ($groups as $group)
						$group_id_list []= $group->id;
			}

			if (count($group_id_list) > 0)
				$conditions['group'] = $group_id_list; else
				$conditions['group'] = -1;
		}

		// get managers
		$manager = Modules\Articles\Manager::get_instance();
		$admin_manager = UserManager::get_instance();

		// get item from the database
		$item = $manager->get_single_item($manager->get_field_names(), $conditions);

		$rating_image_url = URL::make_query(
					$this->name,
					'get_rating_image',
					array('type', $type),
					array('id', $id)
				);

		if (is_object($item)) {
			$timestamp = strtotime($item->timestamp);
			$date = date($this->get_language_constant('format_date_short'), $timestamp);
			$time = date($this->get_language_constant('format_time_short'), $timestamp);

			$result['item'] = array(
								'id'			=> $item->id,
								'text_id'		=> $item->text_id,
								'timestamp'		=> $item->timestamp,
								'date'			=> $date,
								'time'			=> $time,
								'title'			=> $all_languages ? $item->title : $item->title[$language],
								'content'		=> $all_languages ? $item->content : Markdown::parse($item->content[$language]),
								'author'		=> $admin_manager->get_item_value('fullname', array('id' => $item->author)),
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
			$result['error_message'] = $this->get_language_constant('message_json_article_not_found');
		}

		print json_encode($result);
	}

	/**
	 * Generate JSON object list for specified parameters
	 */
	private function json_ArticleList() {
		global $language;

		$manager = Modules\Articles\Manager::get_instance();
		$group_manager = Modules\Articles\GroupManager::get_instance();
		$admin_manager = UserManager::get_instance();

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
				$groups = $group_manager->get_items(
						$group_manager->get_field_names(),
						array('text_id' => $group_names)
					);

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
		$items = $manager->get_items($manager->get_field_names(), $conditions, $order_by, $order_asc, $limit);

		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'items'			=> array()
				);

		if (count($items) > 0) {
			foreach($items as $item) {
				$timestamp = strtotime($item->timestamp);
				$date = date($this->get_language_constant('format_date_short'), $timestamp);
				$time = date($this->get_language_constant('format_time_short'), $timestamp);
				$rating_image_url = URL::make_query(
							$this->name,
							'get_rating_image',
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
									'content'		=> $all_languages ? $item->content : $item->content[$language],
									'author'		=> $admin_manager->get_item_value(
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
			$result['error_message'] = $this->get_language_constant('message_json_articles_not_found');
		}

		print json_encode($result);
	}

	/**
	 * Return data for article group.
	 */
	private function json_Group() {
		$conditions = array();
		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'group'			=> null
				);

		// get parameters
		if (isset($_REQUEST['id']))
			$conditions['id'] = fix_id($_REQUEST['id']);

		$group_text_id = null;
		if (isset($_REQUEST['text_id']))
			$conditions['text_id'] = fix_chars($_REQUEST['text_id']);

		// make sure we have everything needed
		if (empty($conditions)) {
			$result['error'] = true;
			$result['error_message'] = 'Missing required parameters.';
			print json_encode($result);
			return;
		}

		// get group and prepare result
		$manager = Modules\Articles\GroupManager::get_instance();
		$group = $manager->get_single_item($manager->get_field_names(), $conditions);

		if (is_object($group)) {
			$result['group'] = array(
					'id'          => $group->id,
					'text_id'     => $group->text_id,
					'title'       => $group->title,
					'description' => $group->description,
					'visible'     => $group->visible
				);

		} else {
			$result['error'] = true;
			$result['error_message'] = 'Unable to find group with specified id.';
		}

		print json_encode($result);
	}

	/**
	 * Return all article groups.
	 */
	private function json_GroupList() {
		$conditions = array();
		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'items'			=> array()
				);

		// get parameters
		if (isset($_REQUEST['visible']))
			$conditions['visible'] = fix_id($_REQUEST['visible']);

		// get groups and prepare result
		$manager = Modules\Articles\GroupManager::get_instance();
		$group_list = $manager->get_items($manager->get_field_names(), $conditions);

		if (count($group_list) > 0) {
			foreach ($group_list as $group) {
				$result['items'][] = array(
						'id'          => $group->id,
						'text_id'     => $group->text_id,
						'title'       => $group->title,
						'description' => $group->description,
						'visible'     => $group->visible
					);
			}

		} else {
			$result['error_message'] = 'No groups with matching criteria found.';
		}

		print json_encode($result);
	}

	/**
	 * Function to record vote from AJAX call
	 */
	private function json_Vote() {
		$id = fix_id($_REQUEST['id']);
		$value = $_REQUEST['value'];
		$manager = Modules\Articles\Manager::get_instance();
		$vote_manager = Modules\Articles\VoteManager::get_instance();

		$vote = $vote_manager->get_single_item(
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
			$result['error_message'] = $this->get_language_constant('message_vote_already');

		} else {
			// stupid but we need to make sure article exists
			$article = $manager->get_single_item(array('id', 'votes_up', 'votes_down'), array('id' => $id));

			if (is_object($article)) {
				$vote_manager->insert_item(array(
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

					$manager->update_items($data, array('id' => $article->id));
				}

				$article = $manager->get_single_item(array('id', 'votes_up', 'votes_down'), array('id' => $id));
				$result['rating'] = $this->getArticleRating($article, 10);
			} else {
				$result['error'] = true;
				$result['error_message'] = $this->get_language_constant('message_vote_error');
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
