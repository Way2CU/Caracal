<?php

/**
 * Downloads Module
 *
 * Module providing easy-to-manage downloads section.
 *
 * Author: Mladen Mijatov
 */
require_once('units/manager.php');
require_once('units/category_manager.php');

use Modules\Downloads\Manager as DownloadsManager;
use Modules\Downloads\CategoryManager;
use Core\Events;
use Core\Module;


class downloads extends Module {
	private static $_instance;

	public $file_path = null;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section, $site_path;

		parent::__construct(__FILE__);

		// create directories for storing files
		$this->file_path = _BASEPATH.'/'.$site_path.'downloads/';

		if (!file_exists($this->file_path))
			if (mkdir($this->file_path, 0775, true) === false) {
				trigger_error('Downloads: Error creating storage directory.', E_USER_WARNING);
				return;
			}

		// connect events
		Events::connect('head-tag', 'before-print', 'add_meta_tags', $this);
		Events::connect('backend', 'add-menu-items', 'add_menu_items', $this);
		Events::connect('backend', 'sprite-include', 'include_sprite', $this);
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
	public function transfer_control($params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'get':
					$this->redirectDownload();
					break;

				case 'show':
					$this->tag_Download($params, $children);
					break;

				case 'show_list':
					$this->tag_DownloadsList($params, $children);
					break;

				case 'json_list':
					$this->json_DownloadsList();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'upload':
					$this->uploadFile();
					break;

				case 'upload_save':
					$this->uploadFile_Save();
					break;

				case 'list':
					$this->showDownloads();
					break;

				case 'change':
					$this->changeData();
					break;

				case 'save':
					$this->saveData();
					break;

				case 'delete':
					$this->deleteDownload();
					break;

				case 'delete_commit':
					$this->deleteDownload_Commit();
					break;

				case 'categories':
					$this->show_categories();
					break;

				case 'categories_add':
					$this->add_category();
					break;

				case 'categories_change':
					$this->change_category();
					break;

				case 'categories_save':
					$this->save_category();
					break;

				case 'categories_delete':
					$this->delete_category();
					break;

				case 'categories_delete_commit':
					$this->delete_category_commit();
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
		$file_list = array('downloads.sql', 'categories.sql');
		foreach ($file_list as $file_name) {
			$sql = Query::load_file($file_name, $this);
			$db->query($sql);
		}

		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
		global $db;

		$tables = array('downloads', 'download_categories');
		$db->drop_tables($tables);
	}

	/**
	 * Add tags to the head of rendered page when requested.
	 */
	public function add_meta_tags() {
		global $section;

		// we only need to add these tags for backend
		if ($section != 'backend')
			return;

		$head_tag = head_tag::get_instance();
		$head_tag->add_tag('script', array(
				'src'  => URL::from_file_path($this->path.'include/toolbar.js'),
				'type' => 'text/javascript'
			));
	}

	/**
	 * Add menu items on backend event.
	 */
	public function add_menu_items() {
		$backend = backend::get_instance();

		$downloads_menu = new backend_MenuItem(
				$this->get_language_constant('menu_downloads'),
				$this->path.'images/icon.svg',
				'javascript:void(0);',
				$level=5
			);

		$downloads_menu->addChild(null, new backend_MenuItem(
							$this->get_language_constant('menu_upload_file'),
							$this->path.'images/upload.svg',
							window_Open( // on click open window
										'downloads_upload_file',
										400,
										$this->get_language_constant('title_upload_file'),
										true, true,
										backend_UrlMake($this->name, 'upload')
									),
							5  // level
						));

		$downloads_menu->addChild(null, new backend_MenuItem(
							$this->get_language_constant('menu_files'),
							$this->path.'images/manage.svg',
							window_Open( // on click open window
										'downloads',
										520,
										$this->get_language_constant('title_manage'),
										true, true,
										backend_UrlMake($this->name, 'list')
									),
							5  // level
						));

		$downloads_menu->addChild(null, new backend_MenuItem(
							$this->get_language_constant('menu_categories'),
							$this->path.'images/categories.svg',
							window_Open( // on click open window
										'download_categories',
										700,
										$this->get_language_constant('title_categories_manage'),
										true, true,
										backend_UrlMake($this->name, 'categories')
									),
							5  // level
						));

		$backend->addMenu($this->name, $downloads_menu);
	}

	/**
	 * Include backend sprites.
	 */
	public function include_sprite() {
		print file_get_contents($this->path.'images/sprite.svg');
	}

	/**
	 * Show downloads management form
	 */
	private function showDownloads() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->get_language_constant('menu_upload_file'),
										'downloads_upload_file', 400,
										$this->get_language_constant('title_upload_file'),
										true, false,
										$this->name,
										'upload'
									)
					);

		$template->register_tag_handler('cms:downloads_list', $this, 'tag_DownloadsList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Provides a form for uploading files
	 */
	private function uploadFile() {
		$template = new TemplateHandler('upload.xml', $this->path.'templates/');
		$template->register_tag_handler('cms:categories', $this, 'tag_CategoryList');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'upload_save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save uploaded file to database and rename it (if needed)
	 */
	private function uploadFile_Save() {
		$result = $this->saveUpload('file');

		if (!$result['error']) {
			$manager =  DownloadsManager::get_instance();

			$data = array(
					'name'        => $this->get_multilanguage_field('name'),
					'description' => $this->get_multilanguage_field('description'),
					'filename'    => $result['filename'],
					'size'        => $_FILES['file']['size'],
					'visible'     => $this->get_boolean_field('visible'),
					'category'    => fix_id($_REQUEST['category'])
				);

			$manager->insert_item($data);
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $result['message'],
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('downloads_upload_file').";".window_ReloadContent('downloads')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Print form for changing data
	 */
	private function changeData() {
		$id = fix_id($_REQUEST['id']);
		$manager = DownloadsManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		$template = new TemplateHandler('change.xml', $this->path.'templates/');
		$template->register_tag_handler('cms:categories', $this, 'tag_CategoryList');
		$template->set_mapped_module($this->name);

		$params = array(
					'id'          => $item->id,
					'name'        => unfix_chars($item->name),
					'description' => $item->description,
					'filename'    => $item->filename,
					'visible'     => $item->visible,
					'category'    => $item->category,
					'form_action' => backend_UrlMake($this->name, 'save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save changes of download file
	 */
	private function saveData() {
		$manager = DownloadsManager::get_instance();

		$id = fix_id($_REQUEST['id']);
		$data = array(
				'name'        => $this->get_multilanguage_field('name'),
				'description' => $this->get_multilanguage_field('description'),
				'visible'     => fix_id($_REQUEST['visible']),
				'category'    => fix_id($_REQUEST['category'])
			);

		$manager->update_items($data, array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_file_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('downloads_change').";".window_ReloadContent('downloads')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show confirmation dialog for delete
	 */
	private function deleteDownload() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = DownloadsManager::get_instance();

		$item = $manager->get_single_item(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant('message_file_delete'),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->get_language_constant('delete'),
					'no_text'		=> $this->get_language_constant('cancel'),
					'yes_action'	=> window_LoadContent(
											'downloads_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('downloads_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Complete removal of specified image
	 */
	private function deleteDownload_Commit() {
		$id = fix_id($_REQUEST['id']);

		$manager = DownloadsManager::get_instance();

		$manager->delete_items(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_file_deleted'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('downloads_delete').";".window_ReloadContent('downloads')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show categories management window.
	 */
	private function show_categories() {
		$template = new TemplateHandler('category_list.xml', $this->path.'templates/');
		$template->register_tag_handler('cms:category_list', $this, 'tag_CategoryList');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new' => window_OpenHyperlink(
										$this->get_language_constant('new'),
										'download_categories_add', 650,
										$this->get_language_constant('title_categories_add'),
										true, false,
										$this->name,
										'categories_add'
									),
					);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Render window content for adding new downloads category.
	 */
	private function add_category() {
		$template = new TemplateHandler('category_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:category_list', $this, 'tag_CategoryList');

		$params = array(
					'form_action' => backend_UrlMake($this->name, 'categories_save'),
					'parent'      => isset($_REQUEST['parent']) ? fix_id($_REQUEST['parent']) : '-1'
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Render window content for changing downloads category.
	 */
	private function change_category() {
		$id = fix_id($_REQUEST['id']);
		$manager = CategoryManager::get_instance();
		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (!is_object($item))
			return;

		$template = new TemplateHandler('category_change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:category_list', $this, 'tag_CategoryList');

		$params = array(
					'id'			=> $item->id,
					'text_id'		=> $item->text_id,
					'parent'		=> $item->parent,
					'name'			=> $item->name,
					'description'	=> $item->description,
					'form_action'	=> backend_UrlMake($this->name, 'categories_save')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
 	 * Save data for new or changed category.
	 */
	private function save_category() {
		$manager = CategoryManager::get_instance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$data = array(
					'text_id'     => fix_chars($_REQUEST['text_id']),
					'parent'      => fix_id($_REQUEST['parent']),
					'name'        => $this->get_multilanguage_field('name'),
					'description' => $this->get_multilanguage_field('description')
				);

		if (is_null($id)) {
			$window = 'download_categories_add';
			$manager->insert_item($data);

		} else {
			$window = 'download_categories_change';
			$manager->update_items($data, array('id' => $id));
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_category_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).';'.window_ReloadContent('download_categories'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Render confirmation dialog for category removal.
	 */
	private function delete_category() {
		global $language;

		// get item which we are trying to remove
		$id = fix_id($_REQUEST['id']);
		$manager = CategoryManager::get_instance();
		$item = $manager->get_single_item(array('name'), array('id' => $id));

		// load confirmation template
		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant('message_category_delete'),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->get_language_constant('delete'),
					'no_text'		=> $this->get_language_constant('cancel'),
					'yes_action'	=> window_LoadContent(
											'download_categories_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'categories_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('download_categories_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Perform category removal.
	 */
	private function delete_category_commit() {
		// prepare for removal
		$id = fix_id($_REQUEST['id']);
		$manager = CategoryManager::get_instance();

		// remove category
		$manager->delete_items(array('id' => $id));
		$manager->update_items(array('parent' => 0), array('parent' => $id));

		// display message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_category_deleted'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('download_categories_delete').';'.window_ReloadContent('download_categories')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Record download count and redirect to existing file
	 */
	private function redirectDownload() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = DownloadsManager::get_instance();

		if (!is_null($id)) {
			$item = $manager->get_single_item(array('count', 'filename'), array('id' => $id));

			// update count
			$manager->update_items(array('count' => $item->count + 1), array('id' => $id));

			// redirect
			$url = $this->_getDownloadURL($item);
			header('Location: '.$url, true, 302);

		} else {
			die('Invalid download ID!');
		}
	}

	/**
	 * Handle download tag
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Download($tag_params, $children) {
		$manager = DownloadsManager::get_instance();
		$conditions = array();
		$order_by = array();
		$order_asc = true;

		// prepare conditional parameters
		if (isset($tag_params['category'])) {
			$category_manager = CategoryManager::get_instance();
			$list = fix_chars(explode(',', $tag_params['category']));

			// get list of ids only
			$int_list = array_filter($list, 'is_numeric');

			// try to get list of ids from text_ids
			$string_list = array_diff($list, $int_list);
			$raw_list = $category_manager->get_items(
					array('id'),
					array('text_id' => $string_list)
				);

			if (count($raw_list) > 0)
				array_map(function ($category) use (&$int_list) { $int_list []= $category->id; }, $raw_list);

			// include categories in conditionals
			$conditions['category'] = $int_list;
		}

		// load template
		$template = $this->load_template($tag_params, 'download.xml');
		$template->set_template_params_from_array($children);

		if (isset($tag_params['latest']) && $tag_params['latest'] == 1) {
			$order_by = array('id');
			$order_asc = false;
		}

		$item = $manager->get_single_item($manager->get_field_names(), $conditions, $order_by, $order_asc);

		if (is_object($item)) {
			$params = array(
						'id'          => $item->id,
						'text_id'     => $item->text_id,
						'category'    => $item->category,
						'name'        => $item->name,
						'description' => $item->description,
						'filename'    => $item->filename,
						'size'        => $item->size,
						'count'       => $item->count,
						'visible'     => $item->visible,
						'timestamp'   => $item->timestamp,
						'url'         => URL::make_query($this->name, 'get', array('id', $item->id))
					);

			$template->set_local_params($params);
			$template->restore_xml();
			$template->parse();
		}
	}

	/**
	 * Render list of downloads.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_DownloadsList($tag_params, $children) {
		global $section;

		$manager = DownloadsManager::get_instance();
		$conditions = array();

		if (!isset($tag_params['show_invisible']))
			$conditions['visible'] = 1;

		if (isset($tag_params['category'])) {
			$category_manager = CategoryManager::get_instance();
			$list = fix_chars(explode(',', $tag_params['category']));

			// get list of ids only
			$int_list = array_filter($list, 'is_numeric');

			// try to get list of ids from text_ids
			$string_list = array_diff($list, $int_list);
			$raw_list = $category_manager->get_items(
					array('id'),
					array('text_id' => $string_list)
				);

			if (count($raw_list) > 0)
				array_map(function ($category) use (&$int_list) { $int_list []= $category->id; }, $raw_list);

			// include categories in conditionals
			$conditions['category'] = $int_list;
		}

		// get items from database
		$items = $manager->get_items($manager->get_field_names(), $conditions);

		$template = $this->load_template($tag_params, 'list_item.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:download', $this, 'tag_Download');

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
							'id'          => $item->id,
							'text_id'     => $item->text_id,
							'category'    => $item->category,
							'name'        => $item->name,
							'description' => $item->description,
							'filename'    => $item->filename,
							'size'        => $item->size,
							'count'       => $item->count,
							'visible'     => $item->visible,
							'timestamp'   => $item->timestamp,
							'url'         => URL::make_query($this->name, 'get', array('id', $item->id)),
					);

				// include backend options
				if ($section == 'backend' || $section == 'backend_module') {
					$params['item_change'] = URL::make_hyperlink(
									$this->get_language_constant('change'),
									window_Open(
										'downloads_change', 		// window id
										400,						// width
										$this->get_language_constant('title_change'), // title
										false, false,
										URL::make_query(
											'backend_module',
											'transfer_control',
											array('module', $this->name),
											array('backend_action', 'change'),
											array('id', $item->id)
										)
									));
					$params['item_delete'] = URL::make_hyperlink(
									$this->get_language_constant('delete'),
									window_Open(
										'downloads_delete', 	// window id
										400,						// width
										$this->get_language_constant('title_delete'), // title
										false, false,
										URL::make_query(
											'backend_module',
											'transfer_control',
											array('module', $this->name),
											array('backend_action', 'delete'),
											array('id', $item->id)
										)
									));
				}

				$template->restore_xml();
				$template->set_local_params($params);
				$template->parse();
			}
	}

	/**
	 * Render single category tag.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Category($tag_params, $children) {
		$id = isset($tag_params['id']) ? fix_id($tag_params['id']) : null;
		$text_id = isset($tag_params['text_id']) ? fix_chars($tag_params['text_id']) : null;
		$conditions = array();

		// we need at least one of ids in order to display category
		if (is_null($id) && is_null($text_id))
			return;

		// get item from the database
		if (!is_null($id))
			$conditions['id'] = $id; else
			$conditions['text_id'] = $text_id;

		$manager = CategoryManager::get_instance();
		$item = $manager->get_single_item($manager->get_field_names(), $conditions);

		// make sure we have a valid item to operate on
		if (!is_object($item))
			return;

		// load template
		$template = $this->load_template($tag_params, 'category.xml');
		$template->set_template_params_from_array($children);

		// prepare parameters
		$params = array(
				'id'          => $item->id,
				'text_id'     => $item->text_id,
				'name'        => $item->name,
				'description' => $item->description,
				'parent'      => $item->parent
			);

		// render template
		$template->set_local_params($params);
		$template->restore_xml();
		$template->parse();
	}

	/**
	 * Render specified list of categories.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CategoryList($tag_params, $children) {
		global $section;

		$level = 0;
		$selected = -1;
		$conditions = array();

		// prepare conditions
		if (isset($tag_params['id']))
			$conditions['id'] = fix_id(explode(',', $tag_params['id']));

		if (isset($tag_params['text_id']))
			$conditions['text_id'] = fix_chars(explode(',', $tag_params['text_id']));

		if (isset($tag_params['parent']))
			$conditions['parent'] = fix_id($tag_params['parent']);

		if (isset($tag_params['level']))
			$level = fix_id($tag_params['level']);

		if (isset($tag_params['selected']))
			$selected = fix_id($tag_params['selected']);

		// get items from database
		$manager = CategoryManager::get_instance();
		$items = $manager->get_items($manager->get_field_names(), $conditions);

		// make sure we have a valid item to operate on
		if (count($items) == 0)
			return;

		// load template
		$template = $this->load_template($tag_params, 'category_list_item.xml');
		$template->register_tag_handler('cms:children', $this, 'tag_CategoryList');
		$template->set_template_params_from_array($children);

		foreach ($items as $item) {
			// prepare parameters
			$params = array(
					'id'          => $item->id,
					'text_id'     => $item->text_id,
					'name'        => $item->name,
					'description' => $item->description,
					'parent'      => $item->parent,
					'level'       => $level,
					'selected'    => $selected == $item->id
				);

			if ($section == 'backend' || $section == 'backend_module') {
				$params['item_add'] = URL::make_hyperlink(
							$this->get_language_constant('add'),
							window_Open(
								'download_categories_add', 	// window id
								650,					// width
								$this->get_language_constant('title_categories_add'), // title
								false, false,
								URL::make_query(
									'backend_module',
									'transfer_control',
									array('module', $this->name),
									array('backend_action', 'categories_add'),
									array('parent', $item->id)
								)
							));
				$params['item_change'] = URL::make_hyperlink(
							$this->get_language_constant('change'),
							window_Open(
								'download_categories_change', 	// window id
								650,					// width
								$this->get_language_constant('title_categories_change'), // title
								false, false,
								URL::make_query(
									'backend_module',
									'transfer_control',
									array('module', $this->name),
									array('backend_action', 'categories_change'),
									array('id', $item->id)
								)
							));
				$params['item_delete'] = URL::make_hyperlink(
							$this->get_language_constant('delete'),
							window_Open(
								'download_categories_delete', 	// window id
								400,				// width
								$this->get_language_constant('title_categories_delete'), // title
								false, false,
								URL::make_query(
									'backend_module',
									'transfer_control',
									array('module', $this->name),
									array('backend_action', 'categories_delete'),
									array('id', $item->id)
								)
							));
			}

			// render template
			$template->set_local_params($params);
			$template->restore_xml();
			$template->parse();
		}
	}

	/**
	 * Handle JSON request for list of downloads.
	 */
	private function json_DownloadsList() {
		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'items'			=> array()
				);

		// valid license or local API requested data
		$manager = DownloadsManager::get_instance();
		$conditions = array();

		$items = $manager->get_items($manager->get_field_names(), $conditions);

		if (count($items) > 0)
			foreach($items as $item) {
				$result['items'][] = array(
							'id'			=> $item->id,
							'name'			=> $item->name,
							'description'	=> $item->description,
							'count'			=> $item->count,
							'filename'		=> $item->filename,
							'size'			=> $item->size,
							'visible'		=> $item->visible,
							'timestamp'		=> $item->timestamp,
							'download_url'	=> URL::make_query($this->name, 'get', array('id', $item->id))
						);
			}

		print json_encode($result);
	}

	/**
	 * Get apropriate file name from original
	 */
	private function get_file_name($filename) {
		$result = $filename;

		// check if file with the same name already exists
		if (file_exists($this->path.'files/'.$filename)) {
			$info = pathinfo($filename);
			$result = time().'_'.$info['basename'];
		}

		return $result;
	}

	/**
	 * Return absolute URL for file download
	 *
	 * @param resource $item
	 * @return string
	 */
	private function _getDownloadURL($item) {
		return URL::from_file_path($this->file_path.$item->filename);
	}

	/**
	 * Store file in new location
	 */
	private function saveUpload($field_name) {
		$result = array(
					'error'		=> false,
					'message'	=> '',
				);

		if (is_uploaded_file($_FILES[$field_name]['tmp_name'])) {
			// prepare data for recording
			$file_name = $this->get_file_name(fix_chars(basename($_FILES[$field_name]['name'])));

			if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $this->file_path.$file_name)) {
				// file was moved properly, record new data
				$result['filename'] = $file_name;
				$result['message'] = $this->get_language_constant('message_file_uploaded');

			} else {
				// error moving file to new location. folder permissions?
				$result['error'] = true;
				$result['message'] = $this->get_language_constant('message_file_save_error');
			}

		} else {
			// there was an error during upload, notify user
			$result['error'] = true;
			$result['message'] = $this->get_language_constant('message_file_upload_error');
		}

		return $result;
	}
}

?>
