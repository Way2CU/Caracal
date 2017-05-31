<?php

/**
 * Gallery Module
 *
 * Simple gallery with support for group and containers. This gallery
 * module also includes JavaScript Lightbox.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;
use Core\Markdown;


require_once('units/gallery_manager.php');
require_once('units/gallery_group_manager.php');
require_once('units/gallery_container_manager.php');
require_once('units/gallery_group_membership_manager.php');


class Thumbnail {
	const CONSTRAIN_WIDTH = 0;
	const CONSTRAIN_HEIGHT = 1;
	const CONSTRAIN_BOTH = 2;
}


class gallery extends Module {
	private static $_instance;

	public $image_path = null;
	public $optimized_path = null;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section, $site_path;

		parent::__construct(__FILE__);

		// make paths absolute so function can easily convert them to URL
		$this->image_path = _BASEPATH.'/'.$site_path.'gallery/images/';
		$this->optimized_path = _BASEPATH.'/'.$site_path.'gallery/optimized/';

		// make sure storage path exists
		if (!file_exists($this->image_path))
			if (mkdir($this->image_path, 0775, true) === false) {
				trigger_error('Gallery: Error creating storage directory.', E_USER_WARNING);
				return;
			}

		// make sure storage path exists
		if (!file_exists($this->optimized_path))
			if (mkdir($this->optimized_path, 0775, true) === false) {
				trigger_error('Gallery: Error creating storage directory.', E_USER_WARNING);
				return;
			}

		// load module style and scripts
		if (ModuleHandler::is_loaded('head_tag')) {
			$head_tag = head_tag::get_instance();

			// load backend files if needed
			if ($section == 'backend') {
				$head_tag->addTag('link',
						array(
							'href' => URL::from_file_path($this->path.'include/gallery.css'),
							'rel'  => 'stylesheet',
							'type' => 'text/css'
						));
				$head_tag->addTag('script',
						array(
							'src'  => URL::from_file_path($this->path.'include/gallery_toolbar.js'),
							'type' => 'text/javascript'
						));
				$head_tag->addTag('script',
						array(
							'src'  => URL::from_file_path($this->path.'include/backend.js'),
							'type' => 'text/javascript'
						));

			} else {
				// load frontend scripts
				$head_tag->addTag('script',
							array(
								'src'  => URL::from_file_path($this->path.'include/gallery.js'),
								'type' => 'text/javascript'
							));
				$head_tag->addTag('script',
							array(
								'src'  => URL::from_file_path($this->path.'include/lightbox.js'),
								'type' => 'text/javascript'
							));
				$head_tag->addTag('link',
							array(
								'href' => URL::from_file_path($this->path.'include/lightbox.less'),
								'rel'  => 'stylesheet/less',
								'type' => 'text/css'
							));
				$head_tag->addTag('link',
							array(
								'href' => URL::from_file_path($this->path.'include/lightbox.css'),
								'rel'  => 'stylesheet',
								'type' => 'text/css'
							));
			}
		}

		// register backend
		if ($section == 'backend' && ModuleHandler::is_loaded('backend')) {
			$backend = backend::get_instance();

			$gallery_menu = new backend_MenuItem(
					$this->get_language_constant('menu_gallery'),
					URL::from_file_path($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);

			$gallery_menu->addChild(null, new backend_MenuItem(
								$this->get_language_constant('menu_images'),
								URL::from_file_path($this->path.'images/images.svg'),
								window_Open( // on click open window
											'gallery_images',
											670,
											$this->get_language_constant('title_images'),
											true, true,
											backend_UrlMake($this->name, 'images')
										),
								5  // level
							));

			$gallery_menu->addChild(null, new backend_MenuItem(
								$this->get_language_constant('menu_groups'),
								URL::from_file_path($this->path.'images/groups.svg'),
								window_Open( // on click open window
											'gallery_groups',
											450,
											$this->get_language_constant('title_groups'),
											true, true,
											backend_UrlMake($this->name, 'groups')
										),
								5  // level
							));

			$gallery_menu->addChild(null, new backend_MenuItem(
								$this->get_language_constant('menu_containers'),
								URL::from_file_path($this->path.'images/containers.svg'),
								window_Open( // on click open window
											'gallery_containers',
											490,
											$this->get_language_constant('title_containers'),
											true, true,
											backend_UrlMake($this->name, 'containers')
										),
								5  // level
							));

			$backend->addMenu($this->name, $gallery_menu);
		}
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
				case 'show_image':
					$this->tag_Image($params, $children);
					break;

				case 'show_image_list':
					$this->tag_ImageList($params, $children);
					break;

				case 'show_group':
					$this->tag_Group($params, $children);
					break;

				case 'show_group_list':
					$this->tag_GroupList($params, $children);
					break;

				case 'show_container':
					$this->tag_Container($params, $children);
					break;

				case 'show_container_list':
					$this->tag_ContainerList($params, $children);
					break;

				case 'add_to_title':
					$manager = GalleryManager::get_instance();
					$manager->add_property_to_title('title', array('id', 'text_id'), $params);
					break;

				case 'add_group_to_title':
					$manager = GalleryGroupManager::get_instance();
					$manager->add_property_to_title('name', array('id', 'text_id'), $params);
					break;

				case 'add_container_to_title':
					$manager = GalleryContainerManager::get_instance();
					$manager->add_property_to_title('name', array('id', 'text_id'), $params);
					break;

				case 'json_image':
					$this->json_Image();
					break;

				case 'json_image_list':
					$this->json_ImageList();
					break;

				case 'json_group':
					$this->json_Group();
					break;

				case 'json_group_list':
					$this->json_GroupList();
					break;

				case 'json_container':
					break;

				case 'json_container_list':
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'images':
					$this->show_images();
					break;

				case 'images_upload':
					$this->upload_image();
					break;

				case 'images_upload_bulk':
					$this->upload_multiple_images();
					break;

				case 'images_upload_save':
					$this->upload_image_save();
					break;

				case 'images_change':
					$this->change_image();
					break;

				case 'images_save':
					$this->save_image();
					break;

				case 'images_delete':
					$this->delete_image();
					break;

				case 'images_delete_commit':
					$this->delete_image_commit();
					break;

				// ---

				case 'groups':
					$this->show_groups();
					break;

				case 'groups_create':
					$this->create_group();
					break;

				case 'groups_change':
					$this->change_group();
					break;

				case 'groups_save':
					$this->save_group();
					break;

				case 'groups_delete':
					$this->delete_group();
					break;

				case 'groups_delete_commit':
					$this->delete_group_commit();
					break;

				case 'groups_set_thumbnail':
					$this->set_group_thumbnail();
					break;

				// ---

				case 'containers':
					$this->show_containers();
					break;

				case 'containers_create':
					$this->create_container();
					break;

				case 'containers_change':
					$this->change_container();
					break;

				case 'containers_save':
					$this->save_container();
					break;

				case 'containers_delete':
					$this->delete_container();
					break;

				case 'containers_delete_commit':
					$this->delete_container_commit();
					break;

				case 'containers_groups':
					$this->container_groups();
					break;

				case 'containers_groups_save':
					$this->container_groups_save();
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
		$file_list = array(
			'gallery.sql', 'groups.sql', 'group_membership.sql',
			'containers.sql'
			);

		foreach ($file_list as $file_name) {
			$sql = Query::load_file($file_name, $this);
			$db->query($sql);
		}

		// save default supported extensions
		$this->save_setting('image_extensions', 'jpg,jpeg,png,gif');
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
		global $db;

		$tables = array('gallery', 'gallery_groups', 'gallery_containers', 'gallery_group_membership');
		$db->drop_tables($tables);
	}

	/**
	 * Show images management form
	 */
	private function show_images() {
		// load template
		$template = new TemplateHandler('images_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$template->register_tag_handler('cms:image_list', $this, 'tag_ImageList');
		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');

		// upload image menu item
		$url_new = URL::make_query(
						_BACKEND_SECTION_,
						'transfer_control',
						array('backend_action', 'images_upload'),
						array('module', $this->name),
						array('group', isset($_REQUEST['group']) ? fix_id($_REQUEST['group']) : 0)
					);

		$link_new = URL::make_hyperlink(
					$this->get_language_constant('upload_images'),
					window_Open(
						'gallery_images_upload',
						400,
						$this->get_language_constant('title_images_upload'),
						true, false,
						$url_new
					)
				);

		// bulk upload image menu item
		$url_new_bulk = URL::make_query(
						_BACKEND_SECTION_,
						'transfer_control',
						array('backend_action', 'images_upload_bulk'),
						array('module', $this->name),
						array('group', isset($_REQUEST['group']) ? fix_id($_REQUEST['group']) : 0)
					);

		$link_new_bulk = URL::make_hyperlink(
					$this->get_language_constant('upload_images_bulk'),
					window_Open(
						'gallery_images_upload_bulk',
						400,
						$this->get_language_constant('title_images_upload_bulk'),
						true, false,
						$url_new_bulk
					)
				);

		// prepare parameters
		$params = array(
					'link_new'      => $link_new,
					'link_new_bulk' => $link_new_bulk,
					'link_groups'   => URL::make_hyperlink(
										$this->get_language_constant('groups'),
										window_Open( // on click open window
											'gallery_groups',
											500,
											$this->get_language_constant('title_groups'),
											true, true,
											backend_UrlMake($this->name, 'groups')
										)
									)
					);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Provides a form for uploading multiple images
	 */
	private function upload_image() {
		$template = new TemplateHandler('images_upload.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'   => backend_UrlMake($this->name, 'images_upload_save'),
					'cancel_action' => window_Close('gallery_images_upload')
				);

		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show multiple image upload form.
	 */
	private function upload_multiple_images() {
		$template = new TemplateHandler('images_bulk_upload.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'   => backend_UrlMake($this->name, 'images_upload_save'),
					'cancel_action' => window_Close('gallery_images_upload_bulk')
				);

		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save uploaded images
	 */
	private function upload_image_save() {
		$manager = GalleryManager::get_instance();

		$multiple_images = isset($_REQUEST['multiple_upload']) ? $_REQUEST['multiple_upload'] == 1 : false;
		$group = isset($_REQUEST['group']) ? fix_id($_REQUEST['group']) : 0;
		$visible = isset($_REQUEST['visible']) ? 1 : 0;
		$slideshow = isset($_REQUEST['slideshow']) ? 1 : 0;

		if ($multiple_images) {
			// store multiple uploaded images
			$window_name = 'gallery_images_upload_bulk';
			$result = $this->create_image('image');

			$manager->update_items(
					array('group' => $group),
					array('id' => array_keys($result['filenames']))
				);

		} else {
			// store single uploaded image
			$window_name = 'gallery_images_upload';
			$text_id = fix_chars($_REQUEST['text_id']);
			$title = $this->get_multilanguage_field('title');
			$description = $this->get_multilanguage_field('description');
			$result = $this->create_image('image');

			if (count($result['errors']) == 0) {
				$data = array(
							'group'       => $group,
							'text_id'     => $text_id,
							'title'       => $title,
							'description' => $description,
							'visible'     => $visible,
							'slideshow'   => $slideshow,
						);

				$manager->update_items($data, array('id' => array_keys($result['filenames'])));
			}
		}

		error_log(var_export($result, true));
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message' => '<ul><li>'.implode('</li><li>', $result['messages']).'</li></ul>',
					'button'  => $this->get_language_constant('close'),
					'action'  => window_Close($window_name).";".window_ReloadContent('gallery_images')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Pring image data editing form
	 */
	private function change_image() {
		$id = fix_id($_REQUEST['id']);
		$manager = GalleryManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		$template = new TemplateHandler('images_change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');

		$params = array(
					'id'            => $item->id,
					'group'         => $item->group,
					'text_id'       => $item->text_id,
					'title'         => unfix_chars($item->title),
					'description'   => $item->description,
					'size'          => $item->size,
					'filename'      => $item->filename,
					'timestamp'     => $item->timestamp,
					'visible'       => $item->visible,
					'slideshow'     => $item->slideshow,
					'form_action'   => backend_UrlMake($this->name, 'images_save'),
					'cancel_action' => window_Close('gallery_images_change')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save changed image data
	 */
	private function save_image() {
		$manager = GalleryManager::get_instance();

		$id = fix_id($_REQUEST['id']);
		$text_id = fix_chars($_REQUEST['text_id']);
		$title = $this->get_multilanguage_field('title');
		$group = !empty($_REQUEST['group']) ? fix_id($_REQUEST['group']) : 'null';
		$description = $this->get_multilanguage_field('description');
		$visible = $this->get_boolean_field('visible') ? 1 : 0;
		$slideshow = $this->get_boolean_field('slideshow') ? 1 : 0;

		$data = array(
					'text_id'     => $text_id,
					'title'       => $title,
					'group'       => $group,
					'description' => $description,
					'visible'     => $visible,
					'slideshow'   => $slideshow
				);

		$manager->update_items($data, array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message' => $this->get_language_constant('message_image_saved'),
					'button'  => $this->get_language_constant('close'),
					'action'  => window_Close('gallery_images_change').";".window_ReloadContent('gallery_images')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Print confirmation dialog
	 */
	private function delete_image() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = GalleryManager::get_instance();

		$item = $manager->get_single_item(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'    => $this->get_language_constant("message_image_delete"),
					'name'       => $item->title[$language],
					'yes_text'   => $this->get_language_constant("delete"),
					'no_text'    => $this->get_language_constant("cancel"),
					'yes_action' => window_LoadContent(
											'gallery_images_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'images_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'  => window_Close('gallery_images_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Complete removal of specified image
	 */
	private function delete_image_commit() {
		$id = fix_id($_REQUEST['id']);

		$manager = GalleryManager::get_instance();

		$manager->delete_items(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message' => $this->get_language_constant("message_image_deleted"),
					'button'  => $this->get_language_constant("close"),
					'action'  => window_Close('gallery_images_delete').";".window_ReloadContent('gallery_images')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show group management form
	 */
	private function show_groups() {
		$template = new TemplateHandler('groups_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new' => window_OpenHyperlink(
										$this->get_language_constant('create_group'),
										'gallery_groups_create', 400,
										$this->get_language_constant('title_groups_create'),
										true, false,
										$this->name,
										'groups_create'
									),
					);

		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Input form for creating new group
	 */
	private function create_group() {
		$template = new TemplateHandler('groups_create.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'   => backend_UrlMake($this->name, 'groups_save'),
					'cancel_action' => window_Close('gallery_groups_create')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Group change form
	 */
	private function change_group() {
		$id = fix_id($_REQUEST['id']);
		$manager = GalleryGroupManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		$template = new TemplateHandler('groups_change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'id'            => $item->id,
					'text_id'       => unfix_chars($item->text_id),
					'name'          => unfix_chars($item->name),
					'description'   => $item->description,
					'thumbnail'     => $item->thumbnail,
					'form_action'   => backend_UrlMake($this->name, 'groups_save'),
					'cancel_action' => window_Close('gallery_groups_change')
				);

		$template->register_tag_handler('cms:image_list', $this, 'tag_ImageList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save new or changed group data
	 */
	private function save_group() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;

		$data = array(
			'text_id'     => fix_chars($_REQUEST['text_id']),
			'name'        => $this->get_multilanguage_field('name'),
			'description' => $this->get_multilanguage_field('description'),
		);

		if (isset($_REQUEST['thumbnail']))
			$data['thumbnail'] = isset($_REQUEST['thumbnail']) ? fix_id($_REQUEST['thumbnail']) : null;

		$manager = GalleryGroupManager::get_instance();

		if (!is_null($id)) {
			$manager->update_items($data, array('id' => $id));
			$window_name = 'gallery_groups_change';
			$message = $this->get_language_constant('message_group_changed');
		} else {
			$manager->insert_item($data);
			$window_name = 'gallery_groups_create';
			$message = $this->get_language_constant('message_group_created');
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message' => $message,
					'button'  => $this->get_language_constant('close'),
					'action'  => window_Close($window_name).";".window_ReloadContent('gallery_groups')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Delete group confirmation dialog
	 */
	private function delete_group() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = GalleryGroupManager::get_instance();

		$item = $manager->get_single_item(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'    => $this->get_language_constant("message_group_delete"),
					'name'       => $item->name[$language],
					'yes_text'   => $this->get_language_constant("delete"),
					'no_text'    => $this->get_language_constant("cancel"),
					'yes_action' => window_LoadContent(
											'gallery_groups_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'groups_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'  => window_Close('gallery_groups_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Delete group from the system
	 */
	private function delete_group_commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = GalleryManager::get_instance();
		$group_manager = GalleryGroupManager::get_instance();

		$manager->delete_items(array('group' => $id));
		$group_manager->delete_items(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message' => $this->get_language_constant("message_group_deleted"),
					'button'  => $this->get_language_constant("close"),
					'action'  => window_Close('gallery_groups_delete').";".window_ReloadContent('gallery_groups').";".window_ReloadContent('gallery_images')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Set specified image as thumbnail for its parent group.
	 */
	private function set_group_thumbnail() {
		global $language;

		$image_id = fix_id($_REQUEST['id']);
		$manager = GalleryManager::get_instance();
		$group_manager = GalleryGroupManager::get_instance();

		// get image and its group from the database
		$image = $manager->get_single_item(array('group'), array('id' => $image_id));
		if (!is_object($image))
			return;

		$group = $group_manager->get_single_item(array('name'), array('id' => $image->group));

		if (!is_null($image->group) && is_object($group)) {
			// set image as thumbnail for its parent group
			$group_manager->update_items(array('thumbnail' => $image_id), array('id' => $image->group));

			// prepare message template
			$template = new TemplateHandler('message_with_name.xml', $this->path.'templates/');
			$template->set_mapped_module($this->name);

			$params = array(
						'message' => $this->get_language_constant('message_group_thumbnail_set'),
						'name'    => $group->name[$language],
					);

		} else {
			// image doesn't belong to a group, show error message
			$template = new TemplateHandler('message.xml', $this->path.'templates/');
			$template->set_mapped_module($this->name);

			$params = array(
						'message' => $this->get_language_constant('message_group_thumbnail_set_error')
					);
		}

		$params['button'] = $this->get_language_constant('close');
		$params['action'] = window_Close('gallery_groups_set_thumbnail').window_ReloadContent('gallery_images');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show container management form
	 */
	private function show_containers() {
		$template = new TemplateHandler('containers_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new' => window_OpenHyperlink(
										$this->get_language_constant('create_container'),
										'gallery_containers_create', 400,
										$this->get_language_constant('title_containers_create'),
										true, false,
										$this->name,
										'containers_create'
									),
					);

		$template->register_tag_handler('_container_list', $this, 'tag_ContainerList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Input form for creating new group container
	 */
	private function create_container() {
		$template = new TemplateHandler('containers_create.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'   => backend_UrlMake($this->name, 'containers_save'),
					'cancel_action' => window_Close('gallery_containers_create')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Container change form
	 */
	private function change_container() {
		$id = fix_id($_REQUEST['id']);
		$manager = GalleryContainerManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		$template = new TemplateHandler('containers_change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'id'            => $item->id,
					'text_id'       => unfix_chars($item->text_id),
					'name'          => unfix_chars($item->name),
					'description'   => $item->description,
					'form_action'   => backend_UrlMake($this->name, 'containers_save'),
					'cancel_action' => window_Close('gallery_containers_change')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save new or changed group container data
	 */
	private function save_container() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;

		$data = array(
			'text_id'     => fix_chars($_REQUEST['text_id']),
			'name'        => $this->get_multilanguage_field('name'),
			'description' => $this->get_multilanguage_field('description'),
		);

		$manager = GalleryContainerManager::get_instance();

		if (!is_null($id)) {
			$manager->update_items($data, array('id' => $id));
			$window_name = 'gallery_containers_change';
			$message = $this->get_language_constant('message_container_changed');

		} else {
			$manager->insert_item($data);
			$window_name = 'gallery_containers_create';
			$message = $this->get_language_constant('message_container_created');
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message' => $message,
					'button'  => $this->get_language_constant('close'),
					'action'  => window_Close($window_name).";".window_ReloadContent('gallery_containers')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Delete container confirmation dialog
	 */
	private function delete_container() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = GalleryContainerManager::get_instance();

		$item = $manager->get_single_item(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'    => $this->get_language_constant('message_container_delete'),
					'name'       => $item->name[$language],
					'yes_text'   => $this->get_language_constant('delete'),
					'no_text'    => $this->get_language_constant('cancel'),
					'yes_action' => window_LoadContent(
											'gallery_containers_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'containers_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'  => window_Close('gallery_containers_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Delete container from the system
	 */
	private function delete_container_commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = GalleryContainerManager::get_instance();
		$membership_manager = GalleryGroupMembershipManager::get_instance();

		$manager->delete_items(array('id' => $id));
		$membership_manager->delete_items(array('container' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message' => $this->get_language_constant("message_container_deleted"),
					'button'  => $this->get_language_constant("close"),
					'action'  => window_Close('gallery_containers_delete').";".window_ReloadContent('gallery_containers')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Print a form containing all the links within a group
	 */
	private function container_groups() {
		$container_id = fix_id($_REQUEST['id']);

		$template = new TemplateHandler('containers_groups.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'container'     => $container_id,
					'form_action'   => backend_UrlMake($this->name, 'containers_groups_save'),
					'cancel_action' => window_Close('gallery_containers_groups')
				);

		$template->register_tag_handler('_container_groups', $this, 'tag_ContainerGroups');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save container group memberships
	 */
	private function container_groups_save() {
		$container = fix_id($_REQUEST['container']);
		$membership_manager = GalleryGroupMembershipManager::get_instance();

		// fetch all ids being set to specific group
		$gallery_ids = array();
		foreach ($_REQUEST as $key => $value) {
			if (substr($key, 0, 9) == 'group_id_' && $value == 1)
				$gallery_ids[] = fix_id(substr($key, 8));
		}

		// remove old memberships
		$membership_manager->delete_items(array('container' => $container));

		// save new memberships
		foreach ($gallery_ids as $id)
			$membership_manager->insert_item(array(
											'group'     => $id,
											'container' => $container
										));

		// display message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message' => $this->get_language_constant('message_container_groups_updated'),
					'button'  => $this->get_language_constant('close'),
					'action'  => window_Close('gallery_containers_groups')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Image tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Image($tag_params, $children) {
		$manager = GalleryManager::get_instance();

		$item = null;
		$order_by = array();
		$order_asc = true;
		$conditions = array();

		if (!isset($tag_params['show_invisible']))
			$conditions['visible'] = 1;

		if (!isset($tag_params['show_protected']))
			$conditions['protected'] = 0;

		if (isset($tag_params['protected']))
			$conditions['protected'] = fix_id($tag_params['protected']);

		if (isset($tag_params['slideshow']))
			$conditions['slideshow'] = fix_id($tag_params['slideshow']);

		if (isset($tag_params['group_id']) && !($tag_params['group_id'] == 0))
			$conditions['group'] = fix_id($tag_params['group_id']);

		if (isset($tag_params['group'])) {
			$group_manager = GalleryGroupManager::get_instance();

			$group_id = $group_manager->get_item_value(
												'id',
												array('text_id' => fix_chars($tag_params['group']))
											);

			if (!empty($group_id))
				$conditions['group'] = $group_id; else
				$conditions['group'] = -1;
		}

		if (isset($tag_params['order_by']))
			$order_by = fix_chars(explode(',', $tag_params['order_by']));

		if (isset($tag_params['random']) && $tag_params['random'] == 1)
			$order_by = array('RAND()');

		if (isset($tag_params['order_asc']))
			$order_asc = fix_id($tag_params['order_asc']) == 1 ? true : false;

		if (isset($tag_params['id'])) {
			// get specific image
			$conditions['id'] = fix_id($tag_params['id']);

		} else if (isset($tag_params['text_id'])) {
			// get image using specified text_id
			$conditions['text_id'] = fix_chars($tag_params['text_id']);
		}

		$item = $manager->get_single_item(
							$manager->get_field_names(),
							$conditions,
							$order_by,
							$order_asc
						);

		// create template parser
		$template = $this->load_template($tag_params, 'image.xml');
		$template->set_template_params_from_array($children);

		if (is_object($item)) {
			$params = array(
						'id'          => $item->id,
						'text_id'     => $item->text_id,
						'group'       => $item->group,
						'title'       => $item->title,
						'description' => $item->description,
						'filename'    => $item->filename,
						'timestamp'   => $item->timestamp,
						'visible'     => $item->visible,
						'slideshow'   => $item->slideshow,
						'image'       => $this->get_raw_image($item)
				);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Image list tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ImageList($tag_params, $children) {
		global $section;

		$manager = GalleryManager::get_instance();

		$limit = null;
		$order_by = array();
		$order_asc = true;
		$conditions = array();
		$default_image = null;
		$generate_sprite = false;

		if (!isset($tag_params['show_invisible']))
			$conditions['visible'] = 1;

		if (!isset($tag_params['show_protected']))
			$conditions['protected'] = 0;

		if (isset($tag_params['protected']))
			$conditions['protected'] = fix_id($tag_params['protected']);

		if (isset($tag_params['slideshow']))
			$conditions['slideshow'] = fix_id($tag_params['slideshow']);

		if (isset($tag_params['group_id']) && !($tag_params['group_id'] == 0))
			$conditions['group'] = fix_id($tag_params['group_id']);

		if (isset($tag_params['limit']))
			$limit = fix_id($tag_params['limit']);

		if (isset($tag_params['group'])) {
			$group_manager = GalleryGroupManager::get_instance();

			$group_id = $group_manager->get_item_value(
												'id',
												array('text_id' => fix_chars($tag_params['group']))
											);

			if (!empty($group_id))
				$conditions['group'] = $group_id; else
				$conditions['group'] = -1;
		}

		if (isset($tag_params['order_by']))
			$order_by = fix_chars(explode(',', $tag_params['order_by']));

		if (isset($tag_params['random']) && $tag_params['random'] == 1)
			$order_by = array('RAND()');

		if (isset($tag_params['order_asc']))
			$order_asc = fix_id($tag_params['order_asc']) == 1 ? true : false;

		if (isset($tag_params['generate_sprite']))
			$generate_sprite = $tag_params['generate_sprite'] == 1;

		// get default image if group is specified
		if (isset($conditions['group'])) {
			$group_manager = GalleryGroupManager::get_instance();
			$default_image = $group_manager->get_item_value(
					'thumbnail',
					array('id' => $conditions['group'])
				);
		}

		// get items from the database
		$items = $manager->get_items(
							$manager->get_field_names(),
							$conditions,
							$order_by,
							$order_asc,
							$limit
						);

		// load template
		$template = $this->load_template($tag_params, 'images_list_item.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:image', $this, 'tag_Image');

		$selected = isset($tag_params['selected']) ? fix_id($tag_params['selected']) : -1;

		if (count($items) == 0)
			return;

		// collect associated images and generate sprite
		$sprite_image = '';

		if ($generate_sprite && !is_null($gallery)) {
			$image_ids = array();

			// collect gallery ids
			foreach ($items as $item)
				$image_ids []= $item->id;

			// get image parameters
			$image_size = isset($tag_params['image_size']) ? fix_id($tag_params['image_size']) : null;
			$image_constraint = isset($tag_params['image_constraint']) ? fix_id($tag_params['image_constraint']) : null;
			$image_crop = isset($tag_params['image_crop']) ? fix_id($tag_params['image_crop']) : null;

			// generate sprite
			$sprite_image = $this->create_sprite_image(
					$image_ids,
					$image_size,
					$image_constraint,
					$image_crop
				);
		}

		// render template for each image
		foreach ($items as $item) {
			$params = array(
						'id'          => $item->id,
						'text_id'     => $item->text_id,
						'group'       => $item->group,
						'title'       => $item->title,
						'description' => $item->description,
						'filename'    => $item->filename,
						'timestamp'   => $item->timestamp,
						'visible'     => $item->visible,
						'image'       => self::get_raw_image($item),
						'selected'    => $selected,
						'default'     => $default_image == $item->id,
						'sprite'      => $sprite_image
				);

			if ($section == 'backend' || $section == 'backend_module') {
				$params['item_change'] = URL::make_hyperlink(
											$this->get_language_constant('change'),
											window_Open(
												'gallery_images_change', 	// window id
												400,						// width
												$this->get_language_constant('title_images_change'), // title
												false, false,
												URL::make_query(
													'backend_module',
													'transfer_control',
													array('module', $this->name),
													array('backend_action', 'images_change'),
													array('id', $item->id)
												)));
				$params['item_delete'] = URL::make_hyperlink(
											$this->get_language_constant('delete'),
											window_Open(
												'gallery_images_delete', 	// window id
												400,						// width
												$this->get_language_constant('title_images_delete'), // title
												false, false,
												URL::make_query(
													'backend_module',
													'transfer_control',
													array('module', $this->name),
													array('backend_action', 'images_delete'),
													array('id', $item->id)
												)));
				$params['item_set_default'] = URL::make_hyperlink(
											$this->get_language_constant('menu_set_default'),
											window_Open(
												'gallery_groups_set_thumbnail', 	// window id
												320,						// width
												$this->get_language_constant('title_groups_set_thumbnail'), // title
												false, false,
												URL::make_query(
													'backend_module',
													'transfer_control',
													array('module', $this->name),
													array('backend_action', 'groups_set_thumbnail'),
													array('id', $item->id)
												)));
			}

			// set template parameters and render it
			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Group list tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Group($tag_params, $children) {
		global $language;

		$manager = GalleryGroupManager::get_instance();

		$conditions = array();
		$order_by = array();
		$order_asc = true;

		if (isset($tag_params['id']))
			$conditions['id'] = fix_id($tag_params['id']);

		if (isset($tag_params['text_id']))
			$conditions['text_id'] = fix_chars($tag_params['text_id']);

		if (isset($tag_params['order_by']) && in_array($tag_params['order_by'], $manager->get_field_names()))
			$order_by[] = fix_chars($tag_params['order_by']); else
			$order_by[] = 'name_'.$language;

		if (isset($tag_params['order_asc']))
			$order_asc = $tag_params['order_asc'] == 1;

		if (isset($tag_params['container']) || isset($tag_params['container_id'])) {
			$container_manager = GalleryContainerManager::get_instance();
			$membership_manager = GalleryGroupMembershipManager::get_instance();
			$container_id = null;

			if (isset($tag_params['container_id'])) {
				// container ID was specified
				$container_id = fix_id($tag_params['container_id']);

			} else {
				// container text_id was specified, get ID
				$container = $container_manager->get_single_item(
													array('id'),
													array('text_id' => fix_chars($tag_params['container']))
												);

				if (is_object($container))
					$container_id = $container->id; else
					$container_id = -1;
			}

			// grab all groups for specified container
			if (!is_null($container_id)) {
				$memberships = $membership_manager->get_items(array('group'), array('container' => $container_id));

				// extract object values
				$list = array();
				if (count($memberships) > 0)
					foreach($memberships as $membership)
						$list[] = $membership->group;

				// add array as condition value (will be parsed as SQL list)
				if (!empty($list))
					$conditions['id'] = $list; else
					$conditions['id'] = -1;  // ensure no groups are selected
			}
		}

		// get group from database
		$item = $manager->get_single_item($manager->get_field_names(), $conditions, $order_by, $order_asc);

		// create template
		$template = $this->load_template($tag_params, 'group.xml');
		$template->set_template_params_from_array($children);

		$template->register_tag_handler('cms:image', $this, 'tag_Image');
		$template->register_tag_handler('cms:image_list', $this, 'tag_ImageList');

		// parse template
		if (is_object($item)) {
			$params = array(
						'id'          => $item->id,
						'text_id'     => $item->text_id,
						'name'        => $item->name,
						'description' => $item->description,
						'thumbnail'   => $item->thumbnail
					);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Group list tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_GroupList($tag_params, $children) {
		global $language, $section;

		$manager = GalleryGroupManager::get_instance();
		$conditions = array();
		$order_by = array();
		$order_asc = true;

		// get conditions
		if (isset($tag_params['order_by']) && in_array($tag_params['order_by'], $manager->get_field_names()))
			$order_by[] = fix_chars($tag_params['order_by']); else
			$order_by[] = 'name_'.$language;

		if (isset($tag_params['order_asc']))
			$order_asc = $tag_params['order_asc'] == 1;

		if (isset($tag_params['container']) || isset($tag_params['container_id'])) {
			$container_manager = GalleryContainerManager::get_instance();
			$membership_manager = GalleryGroupMembershipManager::get_instance();
			$container_id = null;

			if (isset($tag_params['container_id'])) {
				// container ID was specified
				$container_id = fix_id($tag_params['container_id']);

			} else {
				// container text_id was specified, get ID
				$container = $container_manager->get_single_item(
													array('id'),
													array('text_id' => fix_chars($tag_params['container']))
												);

				if (is_object($container))
					$container_id = $container->id; else
					$container_id = -1;
			}

			// grab all groups for specified container
			if (!is_null($container_id)) {
				$memberships = $membership_manager->get_items(array('group'), array('container' => $container_id));

				// extract object values
				$list = array();
				if (count($memberships) > 0)
					foreach($memberships as $membership)
						$list[] = $membership->group;

				// add array as condition value (will be parsed as SQL list)
				if (!empty($list))
					$conditions['id'] = $list; else
					$conditions['id'] = -1;  // ensure no groups are selected
			}
		}

		// get groups from database
		$items = $manager->get_items(
								$manager->get_field_names(),
								$conditions,
								$order_by,
								$order_asc
							);

		// create template
		$template = $this->load_template($tag_params, 'groups_list_item.xml');
		$template->set_template_params_from_array($children);

		$template->register_tag_handler('cms:image', $this, 'tag_Image');
		$template->register_tag_handler('cms:image_list', $this, 'tag_ImageList');

		$selected = isset($tag_params['selected']) ? fix_id($tag_params['selected']) : -1;

		// return if there's nothing to show
		if (count($items) == 0)
			return;

		// render output
		foreach ($items as $item) {
			$params = array(
						'id'          => $item->id,
						'text_id'     => $item->text_id,
						'name'        => $item->name,
						'description' => $item->description,
						'thumbnail'   => $item->thumbnail,
						'selected'    => $selected
					);

			if ($section == 'backend' || $section == 'backend_module') {
				$params['item_change'] = URL::make_hyperlink(
												$this->get_language_constant('change'),
												window_Open(
													'gallery_groups_change', 	// window id
													400,						// width
													$this->get_language_constant('title_groups_change'), // title
													false, false,
													URL::make_query(
														'backend_module',
														'transfer_control',
														array('module', $this->name),
														array('backend_action', 'groups_change'),
														array('id', $item->id)
													))
												);
				$params['item_delete'] = URL::make_hyperlink(
												$this->get_language_constant('delete'),
												window_Open(
													'gallery_groups_delete', 	// window id
													400,						// width
													$this->get_language_constant('title_groups_delete'), // title
													false, false,
													URL::make_query(
														'backend_module',
														'transfer_control',
														array('module', $this->name),
														array('backend_action', 'groups_delete'),
														array('id', $item->id)
													))
											);
			}

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}

	}

	/**
	 * Container tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Container($tag_params, $children) {
		$conditions = array();
		$manager = GalleryContainerManager::get_instance();

		// get conditions
		if (isset($tag_params['id']))
			$conditions['id'] = fix_id($tag_params['id']);

		if (isset($tag_params['text_id']))
			$conditions['text_id'] = fix_chars($tag_params['text_id']);

		// get container
		$item = $manager->get_single_item($manager->get_field_names(), $conditions);

		// load template
		$template = $this->load_template($tag_params, 'container.xml');
		$template->set_template_params_from_array($children);

		$template->register_tag_handler('cms:image', $this, 'tag_Image');
		$template->register_tag_handler('cms:image_list', $this, 'tag_ImageList');
		$template->register_tag_handler('cms:group', $this, 'tag_Group');
		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');

		if (is_object($item)) {
			$params = array(
						'id'          => $item->id,
						'text_id'     => $item->text_id,
						'name'        => $item->name,
						'description' => $item->description,
						'image'       => $this->getContainerImage($item)
					);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Container list tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ContainerList($tag_params, $children) {
		global $language;

		$manager = GalleryContainerManager::get_instance();
		$conditions = array();
		$order_by = array('name_'.$language);
		$order_asc = true;

		// grab parameters
		if (isset($tag_params['exclude'])) {
			$conditions['text_id'] = array(
									'operator' => 'NOT IN',
									'value'    => fix_chars(explode(',', $tag_params['exclude']))
								);
		}

		$items = $manager->get_items(
								$manager->get_field_names(),
								$conditions,
								$order_by,
								$order_asc
							);

		$template = $this->load_template($tag_params, 'containers_list_item.xml');
		$template->set_template_params_from_array($children);

		$template->register_tag_handler('cms:image', $this, 'tag_Image');
		$template->register_tag_handler('cms:image_list', $this, 'tag_ImageList');
		$template->register_tag_handler('cms:group', $this, 'tag_Group');
		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');

		$selected = isset($tag_params['selected']) ? fix_id($tag_params['selected']) : -1;

		if (count($items) > 0)
		foreach ($items as $item) {
			$params = array(
						'id'          => $item->id,
						'text_id'     => $item->text_id,
						'name'        => $item->name,
						'description' => $item->description,
						'image'       => $this->getContainerImage($item),
						'selected'    => $selected
					);

			if ($section == 'backend' || $section == 'backend_module') {
				$params['item_change'] = URL::make_hyperlink(
												$this->get_language_constant('change'),
												window_Open(
													'gallery_containers_change', 	// window id
													400,							// width
													$this->get_language_constant('title_containers_change'), // title
													false, false,
													URL::make_query(
														'backend_module',
														'transfer_control',
														array('module', $this->name),
														array('backend_action', 'containers_change'),
														array('id', $item->id)
													)
												)
											);

				$params['item_delete'] = URL::make_hyperlink(
												$this->get_language_constant('delete'),
												window_Open(
													'gallery_containers_delete', 	// window id
													400,							// width
													$this->get_language_constant('title_containers_delete'), // title
													false, false,
													URL::make_query(
														'backend_module',
														'transfer_control',
														array('module', $this->name),
														array('backend_action', 'containers_delete'),
														array('id', $item->id)
													))
											);

				$params['item_groups'] = URL::make_hyperlink(
												$this->get_language_constant('container_groups'),
												window_Open(
													'gallery_containers_groups', 	// window id
													400,							// width
													$this->get_language_constant('title_containers_groups'), // title
													false, false,
													URL::make_query(
														'backend_module',
														'transfer_control',
														array('module', $this->name),
														array('backend_action', 'containers_groups'),
														array('id', $item->id)
													))
											);
			}

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}

	}

	/**
	 * Container groups list tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ContainerGroups($tag_params, $children) {
		global $language;

		if (!isset($tag_params['container'])) return;

		$container = fix_id($tag_params['container']);
		$manager = GalleryGroupManager::get_instance();
		$membership_manager = GalleryGroupMembershipManager::get_instance();

		$memberships = $membership_manager->get_items(
												array('group'),
												array('container' => $container)
											);

		$gallery_ids = array();
		if (count($memberships) > 0)
			foreach($memberships as $membership)
				$gallery_ids[] = $membership->group;

		$items = $manager->get_items($manager->get_field_names(), array(), array('name_'.$language));

		$template = new TemplateHandler('containers_groups_item.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
								'id'          => $item->id,
								'in_group'    => in_array($item->id, $gallery_ids) ? 1 : 0,
								'name'        => $item->name,
								'description' => $item->description,
							);

				$template->restore_xml();
				$template->set_local_params($params);
				$template->parse();
			}
	}

	/**
	 * This function provides JSON image objects instead of standard
	 * HTML (XML) output. Function takes all the parameters from $_REQUEST.
	 */
	private function json_Image() {
		global $language;

		$all_languages = isset($_REQUEST['all_languages']) && $_REQUEST['all_languages'] == 1;

		if (!isset($_REQUEST['id']) && !isset($_REQUEST['group'])) {
			// invalid params, print blank JSON object with message
			$result = array(
						'error'         => true,
						'error_message' => $this->get_language_constant('message_json_error_params'),
					);

			print json_encode($result);
			return;
		};

		$manager = GalleryManager::get_instance();

		if (isset($_REQUEST['id'])) {
			// get specific image
			$id = fix_id($_REQUEST['id']);
			$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));
		} else {
			// get first image from group (useful for group thumbnails)
			$id = fix_id($_REQUEST['group']);
			$item = $manager->get_single_item($manager->get_field_names(), array('group' => $id));
		}

		if (is_object($item)) {
			$result = array(
						'error'         => false,
						'error_message' => '',
						'id'            => $item->id,
						'group'         => $item->group,
						'title'         => $all_languages ? $item->title : $item->title[$language],
						'description'   => $all_languages ? $item->description : Markdown::parse($item->description[$language]),
						'filename'      => $item->filename,
						'timestamp'     => $item->timestamp,
						'visible'       => $item->visible,
						'slideshow'     => $item->slideshow,
						'image'         => self::get_raw_image($item),
					);
		} else {
			$result = array(
						'error'         => true,
						'error_message' => $this->get_language_constant('message_json_error_object'),
					);
		}

		print json_encode($result);
	}

	/**
	 * This function provides list of JSON image objects instead of standard
	 * HTML (XML) output. Function takes all the parameters from $_REQUEST.
	 */
	private function json_ImageList() {
		global $language;

		$manager = GalleryManager::get_instance();
		$conditions = array();
		$order_by = array();
		$order_asc = true;
		$limit = null;
		$thumbnail_size = null;
		$constraint = Thumbnail::CONSTRAIN_BOTH;

		// if specified invisible images will be shown as well
		if (!isset($_REQUEST['show_invisible']))
			$conditions['visible'] = 1;

		// include protected images in list
		if (!isset($_REQUEST['show_protected']))
			$conditions['protected'] = 0;

		// include only images marked for slideshow
		if (isset($_REQUEST['slideshow']))
			$conditions['slideshow'] = fix_id($_REQUEST['slideshow']);

		// specify thumbnail size
		if (isset($_REQUEST['thumbnail_size']))
			$thumbnail_size = fix_id($_REQUEST['thumbnail_size']);

		// change size constraint of thumbnail
		if (isset($_REQUEST['constraint']))
			$constraint = fix_id($_REQUEST['constraint']);

		// raw group id was specified
		if (isset($_REQUEST['group_id']))
			$conditions['group'] = fix_id($_REQUEST['group_id']);

		// include all languages or just currently selected one
		$all_languages = isset($_REQUEST['all_languages']) && $_REQUEST['all_languages'] == 1;

		// group text_id was specified, get group ID
		if (isset($_REQUEST['group'])) {
			$group_manager = GalleryGroupManager::get_instance();

			$group_id = $group_manager->get_item_value(
												'id',
												array('text_id' => $_REQUEST['group'])
											);

			if (!empty($group_id))
				$conditions['group'] = $group_id; else
				$conditions['group'] = -1;
		}

		if (isset($_REQUEST['order_by'])) {
			$order_by = fix_chars(explode($_REQUEST['order_by']));
		} else {
			// default sorting column
			$order_by[] = 'title';
		}

		// check for items limit
		if (isset($_REQUEST['limit']))
			$limit = fix_id($_REQUEST['limit']);

		// get items
		$items = $manager->get_items($manager->get_field_names(), $conditions, $order_by, $order_asc, $limit);

		$result = array(
					'error'         => false,
					'error_message' => '',
					'items'         => array()
				);

		if (count($items) > 0) {
			foreach ($items as $item) {
				// generate thumbnail if specified
				$thumbnail_url = '';
				if (!is_null($thumbnail_size))
					$thumbnail_url = self::get_image($item, $thumbnail_size, $constraint);

				// add image to result list
				$result['items'][] = array(
							'id'          => $item->id,
							'text_id'     => $item->text_id,
							'group'       => $item->group,
							'title'       => $all_languages ? $item->title : $item->title[$language],
							'description' => $all_languages ? $item->description : Markdown::parse($item->description[$language]),
							'filename'    => $item->filename,
							'timestamp'   => $item->timestamp,
							'visible'     => $item->visible,
							'image'       => self::get_raw_image($item),
							'thumbnail'   => $thumbnail_url
						);
			}
		} else {
			$result['error'] = true;
			$result['error_message'] = $this->get_language_constant('message_json_error_object');
		}

		print json_encode($result);
	}

	/**
	 * This function provides JSON group object instead of standard
	 * HTML (XML) output. Function takes all the parameters from $_REQUEST.
	 */
	private function json_Group() {
		if (isset($_REQUEST['id'])) {
			// display a specific group
			$id = fix_id($_REQUEST['id']);

		} else if (isset($_REQUEST['container'])) {
			// display first group in specific container
			$container = fix_id($_REQUEST['container']);
			$manager = GalleryGroupManager::get_instance();
			$membership_manager = GalleryGroupMembershipManager::get_instance();

			$id = $membership_manager->get_single_item('group', array('container' => $container));
		} else {
			// no container nor group id was specified
			// invalid params, print blank JSON object with message
			$result = array(
						'error'			=> true,
						'error_message'	=> $this->get_language_constant('message_json_error_params'),
					);

			print json_encode($result);
			return;
		}

		$manager = GalleryGroupManager::get_instance();
		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (is_object($item)) {
			$result = array(
						'error'         => false,
						'error_message' => '',
						'id'            => $item->id,
						'name'          => $item->name,
						'description'   => $item->description,
					);
		} else {
			$result = array(
						'error'         => true,
						'error_message' => $this->get_language_constant('message_json_error_object'),
					);
		}

		print json_encode($result);
	}

	/**
	 * This function provides JSON group objects instead of standard
	 * HTML (XML) output. Function takes all the parameters from $_REQUEST.
	 */
	private function json_GroupList() {
		global $language;

		$manager = GalleryGroupManager::get_instance();
		$conditions = array();
		$order_by = array();
		$order_asc = true;

		if (isset($_REQUEST['container']) || isset($_REQUEST['container_id'])) {
			$container_id = isset($_REQUEST['container_id']) ? fix_id($_REQUEST['container_id']) : null;
			$membership_manager = GalleryGroupMembershipManager::get_instance();

			// in case text_id was suplied, get id
			if (is_null($container_id)) {
				$container_text_id = fix_chars($_REQUEST['container']);
				$container_manager = GalleryContainerManager::get_instance();
				$container = $container_manager->get_single_item(
														array('id'),
														array('text_id' => $container_text_id)
													);

				if (is_object($container))
					$container_id = $container->id;
			}

			// grab all groups for specified container
			$memberships = array();
			if (!is_null($container_id))
				$memberships = $membership_manager->get_items(array('group'), array('container' => $container_id));

			// extract object values
			$list = array();
			if (count($memberships) > 0)
				foreach($memberships as $membership)
					$list[] = $membership->group;

			// add array as condition value (will be parsed as SQL list)
			if (!empty($list))
				$conditions['id'] = $list; else
				$conditions['id'] = -1;  // ensure no groups are selected
		}

		if (isset($_REQUEST['order_by'])) {
			$order_by = explode(',', fix_chars($_REQUEST['order_by']));
		} else {
			$order_by = array('name_'.$language);
		}

		if (isset($_REQUEST['order_asc']))
			$order_asc = $tag_params['order_asc'] == '1' or $tag_params['order_asc'] == 'yes';

		$items = $manager->get_items(
								$manager->get_field_names(),
								$conditions,
								$order_by,
								$order_asc
							);

		$result = array(
					'error'         => false,
					'error_message' => '',
					'items'         => array()
				);

		if (count($items) > 0) {
			foreach ($items as $item)
				$result['items'][] = array(
							'id'          => $item->id,
							'name'        => $item->name,
							'description' => $item->description
						);

		} else {
			$result['error'] = true;
			$result['error_message'] = $this->get_language_constant('message_json_error_object');
		}

		print json_encode($result);
	}

	/**
	 * This function provides JSON container object instead of standard
	 * HTML (XML) output. Function takes all the parameters from $_REQUEST.
	 */
	private function json_Container() {
		if (!isset($_REQUEST['id'])) return;

		$id = fix_id($_REQUEST['id']);
		$manager = GalleryContainerManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (is_object($item)) {
			$result = array(
						'error'         => false,
						'error_message' => '',
						'id'            => $item->id,
						'name'          => $item->name,
						'description'   => $item->description,
					);
		} else {
			$result = array(
						'error'         => true,
						'error_message' => $this->get_language_constant('message_json_error_object'),
					);
		}

		print json_encode($result);
	}

	/**
	 * This function provides JSON container objects instead of standard
	 * HTML (XML) output. Function takes all the parameters from $_REQUEST.
	 */
	private function json_ContainerList() {
		$manager = GalleryContainerManager::get_instance();

		$items = $manager->get_items(
								$manager->get_field_names(),
								array(),
								array('name')
							);

		$result = array(
					'error'         => false,
					'error_message' => '',
					'items'         => array()
				);

		if (count($items) > 0) {
			foreach ($items as $item)
				$result['items'][] = array(
							'id'          => $item->id,
							'name'        => $item->name,
							'description' => $item->description
						);
		} else {
			$result['error'] = true;
			$result['error_message'] = $this->get_language_constant('message_json_error_object');
		}

		print json_encode($result);
	}

	/**
	 * Get image id from specified group.
	 *
	 * @param mixed $group
	 * @return integer
	 */
	private static function get_group_image_id($group) {
		$result = null;
		$conditions = array();

		// try to detect which identifier was specified
		if (is_numeric($group)) {
			$conditions['id'] = (int) $group;

		} else if (is_string($group)) {
			$conditions['text_id'] = $group;

		} else if (is_object($group)) {
			// mark as invalid group if property is missing
			if (!property_exists($group, 'thumbnail') || !property_exists($group, 'id'))
				$group = null;
		}

		// try to get item from the database
		if (!empty($conditions)) {
			$manager = GalleryGroupManager::get_instance();
			$group = $manager->get_single_item(array('id', 'thumbnail'), $conditions);
		}

		// try to generate result from specified item
		if (is_object($group)) {
			if (!is_null($group->thumbnail)) {
				// get image from selected thumbnail
				$result = $group->thumbnail;

			} else {
				// get image randomly from the group
				$image_manager = GalleryManager::get_instance();
				$image = $image_manager->get_single_item(
						array('id'),
						array(
							'group'   => $group->id,
							'visible' => 1
						),
						array('RAND()')
					);

				if (is_object($image))
					$result = $image->id;
			}

		} else {
			trigger_error('Gallery: No suitable group identifier specified!', E_USER_NOTICE);
		}

		return $result;
	}

	/**
	 * Get image id from specified container.
	 *
	 * @param mixed $group
	 * @return integer
	 */
	private static function get_container_image_id($container) {
		$result = null;
		$container_id = null;

		// try to get container id depending on parameter type
		if (is_numeric($container)) {
			$container_id = (int) $container;

		} else if (is_string($container)) {
			$manager = GalleryContainerManager::get_instance();
			$container = $manager->get_single_item(
					array('id'),
					array('text_id' => $container)
				);

			if (is_object($container))
				$container_id = $container->id;

		} else if (is_object($container)) {
			if (property_exists($container, 'id'))
				$container_id = $container->id;
		}

		// get random group and then get its image
		if (!is_null($container_id)) {
			$membership_manager = GalleryGroupMembershipManager::get_instance();
			$result = $membership_manager->get_item_value(
					'group',
					array('container' => $container_id),
					array('RAND()')
				);

		} else {
			trigger_error('Gallery: No suitable container identifier specified!', E_USER_NOTICE);
		}

		return $result;
	}

	/**
	 * Get URL to the raw, unmodified and unoptimized image.
	 *
	 * @param object/integer $item
	 * @return string
	 */
	public static function get_raw_image($item) {
		$result = '';
		$conditions = array();

		// try to detect which identifier was specified
		if (is_numeric($item)) {
			$conditions['id'] = (int) $item;

		} else if (is_string($item)) {
			$conditions['text_id'] = $item;

		} else if (is_object($item)) {
			// mark as invalid item if property is missing
			if (!property_exists($item, 'filename'))
				$item = null;
		}

		// try to get item from the database
		if (!empty($conditions)) {
			$manager = GalleryManager::get_instance();
			$item = $manager->get_single_item(array('filename'), $conditions);
		}

		// try to generate result from specified item
		if (is_object($item)) {
			$gallery = self::get_instance();
			$result = URL::from_file_path($gallery->image_path.$item->filename);

		} else {
			trigger_error('Gallery: No suitable image identifier specified!', E_USER_NOTICE);
		}

		return $result;
	}

	/**
	 * Get URL to the raw, unmodified and unoptimized image for
	 * specified group. Parameter can be either object or id.
	 *
	 * @param object/integer $group
	 * @return string
	 */
	public static function get_raw_group_image($group) {
		$result = '';
		$conditions = array();

		// try to detect which identifier was specified
		if (is_numeric($group)) {
			$conditions['id'] = (int) $group;

		} else if (is_string($group)) {
			$conditions['text_id'] = $group;

		} else if (is_object($group)) {
			// mark as invalid group if property is missing
			if (!property_exists($group, 'thumbnail') || !property_exists($group, 'id'))
				$group = null;
		}

		// try to get item from the database
		if (!empty($conditions)) {
			$manager = GalleryGroupManager::get_instance();
			$group = $manager->get_single_item(array('id', 'thumbnail'), $conditions);
		}

		// try to generate result from specified item
		if (is_object($group)) {
			if (!is_null($group->thumbnail)) {
				// get image from selected thumbnail
				$result = self::get_raw_image($group->thumbnail);

			} else {
				// get image randomly from the group
				$image_manager = GalleryManager::get_instance();
				$image = $image_manager->get_single_item(
						array('filename'),
						array(
							'group'   => $group->id,
							'visible' => 1
						),
						array('RAND()')
					);

				if (is_object($image))
					$result = self::get_raw_image($image);
			}

		} else {
			trigger_error('Gallery: No suitable group identifier specified!', E_USER_NOTICE);
		}

		return $result;
	}

	/**
	 * Get URL to the raw, unmodified and unoptimized image for
	 * specified container. Parameter can be either object or id.
	 *
	 * @param object/integer $container
	 * @return string
	 */
	public static function get_raw_container_image($container) {
		$result = '';
		$container_id = null;

		// try to get container id depending on parameter type
		if (is_numeric($container)) {
			$container_id = (int) $container;

		} else if (is_string($container)) {
			$manager = GalleryContainerManager::get_instance();
			$container = $manager->get_single_item(
					array('id'),
					array('text_id' => $container)
				);

			if (is_object($container))
				$container_id = $container->id;

		} else if (is_object($container)) {
			if (property_exists($container, 'id'))
				$container_id = $container->id;
		}

		// get random group and then get its image
		if (!is_null($container_id)) {
			$membership_manager = GalleryGroupMembershipManager::get_instance();
			$group_id = $membership_manager->get_item_value(
					'group',
					array('container' => $container_id),
					array('RAND()')
				);

			if (!is_null($group_id))
				$result = self::get_raw_group_image($group_id);

		} else {
			trigger_error('Gallery: No suitable container identifier specified!', E_USER_NOTICE);
		}
	}

	/**
	 * Get URL to the resized and optimized image from specified object,
	 * numerical or textual id.
	 *
	 * @param mixed $item
	 * @param integer $size
	 * @param Thumbnail $constraint
	 * @param integer $crop_size
	 * @return string
	 */
	public static function get_image($item, $size=100, $constraint=Thumbnail::CONSTRAIN_BOTH, $crop_size=null) {
		$result = '';
		$conditions = array();

		// try to detect which identifier was specified
		if (is_numeric($item)) {
			$conditions['id'] = (int) $item;

		} else if (is_string($item)) {
			$conditions['text_id'] = $item;

		} else if (is_object($item)) {
			// mark as invalid item if property is missing
			if (!property_exists($item, 'filename'))
				$item = null;
		}

		// try to get item from the database
		if (!empty($conditions)) {
			$manager = GalleryManager::get_instance();
			$item = $manager->get_single_item(array('filename'), $conditions);
		}

		// no suitable identifier has been found, report and return
		if (is_object($item)) {
			$gallery = gallery::get_instance();
			$raw_file = $gallery->image_path.$item->filename;

			$generated_file = $gallery->create_optimized_image($raw_file, $size, $constraint, $crop_size);
			$result = URL::from_file_path($generated_file);

		} else {
			trigger_error('Gallery: No suitable image identifier specified!', E_USER_NOTICE);
		}

		return $result;
	}

	/**
	 * Get URL to the resized and optimized image from specified object,
	 * numerical or textual id of the image group.
	 *
	 * @param mixed $group
	 * @param integer $size
	 * @param Thumbnail $constraint
	 * @param integer $crop_size
	 * @return string
	 */
	public static function get_group_image($group, $size=100, $constraint=Thumbnail::CONSTRAIN_BOTH, $crop_size=null) {
		return self::get_image(self::get_group_image_id($group), $size, $constraint, $crop_size);
	}

	/**
	 * Get URL to the resized and optimized image from specified object,
	 * numerical or textual id of the image container.
	 *
	 * @param mixed $container
	 * @param integer $size
	 * @param Thumbnail $constraint
	 * @param integer $crop_size
	 * @return string
	 */
	public static function get_container_image($container, $size=100, $constraint=Thumbnail::CONSTRAIN_BOTH, $crop_size=null) {
		return self::get_group_image(self::get_container_image_id, $size, $constraint, $crop_size);
	}

	/**
	 * Get image URL
	 *
	 * @param resource $item
	 * @return string
	 * @deprecated
	 */
	public function getImageURL($item) {
		trigger_error('Deprecated, use `get_raw_image`.', E_USER_WARNING);
		return $this->get_raw_image($item);
	}

	/**
	 * Get image URL based on id or text_id.
	 *
	 * @param integer $id
	 * @param string $text_id
	 * @return string
	 * @deprecated
	 */
	public static function getImageById($id=null, $text_id=null) {
		trigger_error('Deprecated, use `get_raw_image`.', E_USER_WARNING);
		return self::get_raw_image(is_null($id) ? $text_id : $id);
	}

	/**
	 * Get group image URL based on one of the specified ids.
	 *
	 * @param integer $id
	 * @param string $text_id
	 * @return string
	 * @deprecated
	 */
	public static function getGroupImageById($id=null, $text_id=null) {
		trigger_error('Deprecated, use `get_raw_group_image`.', E_USER_WARNING);
		return self::get_raw_group_image(is_null($id) ? $text_id : $id);
	}
	/**
	 * Get thumbnail URL
	 *
	 * @param resource $item
	 * @param integer $size
	 * @param integer $constraint
	 * @param integer $crop_size
	 * @return string
	 * @deprecated
	 */
	public function getThumbnailURL($item, $size=100, $constraint=Thumbnail::CONSTRAIN_BOTH, $crop_size=null) {
		trigger_error('Deprecated, use `get_image`.', E_USER_WARNING);
		return self::get_image($item, $size, $constraint, $crop_size);
	}

	/**
	 * Get thumbnail URL based on image id or text_id.
	 *
	 * @param integer $id
	 * @param string $text_id
	 * @param integer $size
	 * @param integer $constraint
	 * @param integer $crop_size
	 * @return string
	 * @deprecated
	 */
	public static function getThumbnailById($id=null, $text_id=null, $size=100, $constraint=Thumbnail::CONSTRAIN_BOTH, $crop_size=null) {
		trigger_error('Deprecated, use `get_image`.', E_USER_WARNING);
		return self::get_image(is_null($id) ? $text_id : $id, $size, $constraint, $crop_size);
	}

	/**
	 * Get group thumbnail URL based on one of the specified ids.
	 *
	 * @param integer $id
	 * @param string $text_id
	 * @param integer $size
	 * @param integer $constraint
	 * @param integer $crop_size
	 * @return string
	 * @deprecated
	 */
	public static function getGroupThumbnailById($id=null, $text_id=null, $size=100, $constraint=Thumbnail::CONSTRAIN_BOTH, $crop_size=null) {
		trigger_error('Deprecated, use `get_group_image`.', E_USER_WARNING);
		return self::get_group_image(is_null($id) ? $text_id : $id, $size, $constraint, $crop_size);
	}

	/**
	 * Get container thumbnail URL based on one of the specified ids.
	 *
	 * @param integer $id
	 * @param string $text_id
	 * @param integer $size
	 * @param integer $constraint
	 * @param integer $crop_size
	 * @return string
	 * @deprecated
	 */
	public static function getContainerThumbnailById($id=null, $text_id=null, $size=100, $constraint=Thumbnail::CONSTRAIN_BOTH, $crop_size=null) {
		trigger_error('Deprecated, use `get_container_image`.', E_USER_WARNING);
		return self::get_container_image(is_null($id) ? $text_id : $id, $size, $constraint, $crop_size);
	}


	/**
	 * Get group image
	 *
	 * @param resource $group
	 * @param boolean $big_image
	 * @return string
	 * @deprecated
	 */
	private function getGroupImage($group, $big_image=false) {
		trigger_error('Deprecated, use `get_raw_group_image` or `get_group_image`.', E_USER_WARNING);

		$result = '';
		if ($big_image)
			$result = self::get_raw_group_image($group); else
			$result = self::get_group_image($group);

		return $result;
	}

	/**
	 * Get container image
	 *
	 * @param resource $container
	 * @return string
	 * @deprecated
	 */
	private function getContainerImage($container) {
		trigger_error('Deprecated, use `get_container_image`.', E_USER_WARNING);
		return self::get_raw_container_image($container);
	}

	/**
	 * Saves image from specified field name and return error code
	 *
	 * @param string $field_name
	 * @param integer $protected
	 * @return array
	 */
	public function create_image($field_name, $protected=0) {
		global $site_path;

		// prepare result
		$result = array(
					'errors'   => array(),
					'messages' => array(),
					'filenames' => array()
				);

		// preload uploaded file values
		if (is_array($_FILES[$field_name]['name'])) {
			$file_names = $_FILES[$field_name]['name'];
			$file_temp_names = $_FILES[$field_name]['tmp_name'];
			$file_sizes = $_FILES[$field_name]['size'];
			$multiple_upload = true;

		} else {
			$file_names = array($_FILES[$field_name]['name']);
			$file_temp_names = array($_FILES[$field_name]['tmp_name']);
			$file_sizes = array($_FILES[$field_name]['size']);
			$multiple_upload = false;
		}

		// filter out unwanted files
		$allowed_extensions = explode(',', strtolower($this->settings['image_extensions']));
		$files = array();

		for ($i = 0; $i < count($file_names); $i++) {
			$extension = pathinfo(strtolower($file_names[$i]), PATHINFO_EXTENSION);

			// check extension
			if (!in_array($extension, $allowed_extensions)) {
				$result['errors'][] = $file_names[$i].' - '.$this->get_language_constant('message_image_invalid_type');
				trigger_error('Gallery: Invalid file type uploaded.', E_USER_NOTICE);

				// skip to next file
				continue;
			}

			if (!is_uploaded_file($file_temp_names[$i])) {
				$result['errors'][] = $file_names[$i].' - '.$this->get_language_constant('message_image_upload_error');
				trigger_error('Gallery: Not an uploaded file. This should not happen!', E_USER_ERROR);

				// skip to next file
				continue;
			}

			// store image data for upload
			$files []= array(
					'name' => $file_names[$i],
					'temp' => $file_temp_names[$i],
					'size' => $file_sizes[$i]
				);
		}

		// process uploaded images
		$manager = GalleryManager::get_instance();

		foreach ($files as $file) {
			// get unique file name for this image to be stored
			$extension = pathinfo(strtolower($file['name']), PATHINFO_EXTENSION);
			$filename = hash('md5', time().$file['name']).'.'.$extension;

			// try moving file to new destination
			if (move_uploaded_file($file['temp'], $this->image_path.$filename)) {
				// store empty data in database
				$data = array(
							'size'      => $file['size'],
							'filename'  => $filename,
							'visible'   => 1,
							'slideshow' => 0,
							'protected' => $protected
						);

				$manager->insert_item($data);
				$id = $manager->get_inserted_id();

				$result['filenames'][$id] = $filename;
				$result['messages'][] = $file['name'].' - '.$this->get_language_constant('message_image_uploaded');

			} else {
				$result['errors'] = $file['name'].' - '.$this->get_language_constant('message_image_save_error');
			}
		}

		return $result;
	}

	/**
	 * Create gallery from specified uploaded field names
	 *
	 * @param array $name Multi-language name for newly created gallery
	 * @return integer Newly created gallery Id
	 */
	public function create_gallery($name) {
		$gallery_manager = GalleryGroupManager::get_instance();

		// create gallery
		$gallery_manager->insert_item(array('name' => $name));
		$result = $gallery_manager->get_inserted_id();

		return $result;
	}

	/**
	 * Create thumbnail from specified image
	 *
	 * @param string $filename
	 * @param integer $size
	 * @param integer $constraint
	 * @param integer $crop_size
	 * @return string
	 */
	private function create_optimized_image($filename, $size, $constraint=Thumbnail::CONSTRAIN_BOTH, $crop_size=null) {
		// generate file name
		$target_file = $this->optimized_path;
		$target_file .= $size.'_';
		if (!is_null($crop_size))
			$target_file .= 'crp'.$crop_size.'_';
		$target_file .= 'cs'.$constraint.'_';
		$target_file .= pathinfo($filename, PATHINFO_BASENAME);

		// if target file exists, don't create it
		if (file_exists($target_file))
			return $target_file;

		// make sure source exists
		if (!file_exists($filename))
			return null;

		// create image resource
		$img_source = null;
		$save_function = null;
		$save_quality = null;
		$has_alpha = false;
		$save_quality = null;
		$extension = strtolower(pathinfo(strtolower($filename), PATHINFO_EXTENSION));

		switch ($extension) {
			case 'jpg':
			case 'jpeg':
				$img_source = imagecreatefromjpeg($filename);
				$save_function = @imagejpeg;
				$save_quality = 85;
				break;

			case 'png':
				$img_source = imagecreatefrompng($filename);
				$save_function = @imagepng;
				$save_quality = 9;
				$has_alpha = true;
				break;

			case 'gif':
				$img_source = imagecreatefromgif($filename);
				$save_function = @imagegif;
				$has_alpha = true;
		}

		// we failed to load image, exit
		if ($img_source === FALSE || is_null($img_source))
			return null;

		// calculate width to height ratio
		$source_width = imagesx($img_source);
		$source_height = imagesy($img_source);
		$max_width = null;
		$max_height = null;

		switch ($constraint) {
			case Thumbnail::CONSTRAIN_WIDTH:
				$scale = $size / $source_height;
				if (!is_null($crop_size))
					$max_width = $crop_size;
				break;

			case Thumbnail::CONSTRAIN_HEIGHT:
				$scale = $size / $source_width;
				if (!is_null($crop_size))
					$max_height = $crop_size;
				break;

			case Thumbnail::CONSTRAIN_BOTH:
			default:
				if ($source_width >= $source_height)
					$scale = $size / $source_width; else
					$scale = $size / $source_height;
				break;
		}

		// calculate final image size
		$thumb_width = floor($scale * $source_width);
		$thumb_height = floor($scale * $source_height);

		// create new image
		$thumbnail = imagecreatetruecolor(
				is_null($max_width) ? $thumb_width : $max_width,
				is_null($max_height) ? $thumb_height : $max_height
			);

		if ($has_alpha) {
			imagealphablending($thumbnail, false);
			imagesavealpha($thumbnail, true);
		}

		// copy and resample to new size
		imagecopyresampled(
				$thumbnail,
				$img_source,
				0, 0, 0, 0,
				$thumb_width,
				$thumb_height,
				$source_width,
				$source_height
			);

		// save image to file
		if (!is_null($save_quality))
			$save_function($thumbnail, $target_file, $save_quality); else
			$save_function($thumbnail, $target_file);

		return $target_file;
	}

	/**
	 * Generate SVG sprite composite image. This image will embed other images
	 * and provide easy mechanism for displaying them. While this process does generate
	 * files 15-20% bigger (on average) TCP/IP protocol, due to its initial throttling,
	 * will favor these composites over individual requests for separate small files.
	 *
	 * @param array $id_list,
	 * @param integer $size
	 * @param integer $constraint
	 * @param integer $crop_size
	 * @return string
	 */
	public function create_sprite_image($id_list, $size, $constraint=Thumbnail::CONSTRAIN_BOTH, $crop_size=null) {
		// prepare id hash
		sort($id_list);
		$set_hash = hash('sha256', implode('-', $id_list));

		// make sure constraint is specified
		if (is_null($constraint))
			$constraint = Thumbnail::CONSTRAIN_BOTH;

		// generate target file name
		$target_file = $this->optimized_path;
		$target_file .= 'images_'.$size.'_';
		if (!is_null($crop_size))
			$target_file .= 'crp'.$crop_size.'_';
		$target_file .= 'cs'.$constraint.'_';
		$target_file .= $set_hash.'.svg';

		// if target file exists, don't create it
		if (file_exists($target_file))
			return URL::from_file_path($target_file);

		// get image data
		$manager = GalleryManager::get_instance();
		$images = $manager->get_items(
				array('id', 'filename'),
				array('id' => $id_list)
			);

		// report potential misbehavior
		if (count($images) == 0) {
			trigger_error('Gallery: Requested empty sprite!', E_USER_NOTICE);
			return '';
		}

		// open target file for writing
		$file = fopen($target_file, 'w');

		if ($file === false) {
			trigger_error('Gallery: Unable to create sprite file for writing!', E_USER_ERROR);
			return '';
		}

		// generate header code
		fwrite($file, '<svg version="1.1" baseProfile="tiny" width="100%" height="100%" ');
		fwrite($file, 'viewBox="0 0 '.$size.' '.$size.'" ');
		fwrite($file, 'xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">');
		fwrite($file, '<defs><style type="text/css">image{visibility:hidden;}image:target{visibility:visible;}</style></defs>');

		// embed each image individually
		foreach ($images as $image) {
			$original_file = $this->image_path.$image->filename;
			$image_file = $this->create_optimized_image($original_file, $size, $constraint, $crop_size);

			// skip images which can't be loaded
			if (!file_exists($image_file))
				continue;

			fwrite($file, '<image id="'.$image->id.'" x="0" y="0" width="'.$size.'" height="'.$size.'" ');
			fwrite($file, 'xlink:href="data:image/jpg;base64,'.base64_encode(file_get_contents($image_file)));
			fwrite($file, '"/>');
		}

		// close target file and clean up
		fwrite($file, '</svg>');
		fclose($file);

		return URL::from_file_path($target_file);
	}

	/**
	 * Generate SVG sprite composite image based on group id list. This image will embed
	 * other images and provide easy mechanism for displaying them. While this process does
	 * generate files 15-20% bigger (on average) TCP/IP protocol, due to its initial
	 * throttling, will favor these composites over individual requests for separate small
	 * files.  *
	 * Note: Instead of image id, thumbnails will be stored under group id for easier access.
	 *
	 * @param array $id_list,
	 * @param integer $size
	 * @param integer $constraint
	 * @param integer $crop_size
	 * @return string
	 */
	public function create_group_sprite_image($id_list, $size, $constraint=Thumbnail::CONSTRAIN_BOTH, $crop_size=null) {
		// prepare id hash
		sort($id_list);
		$set_hash = hash('sha256', implode('-', $id_list));

		// make sure constraint is specified
		if (is_null($constraint))
			$constraint = Thumbnail::CONSTRAIN_BOTH;

		// generate target file name
		$target_file = $this->optimized_path;
		$target_file .= 'groups_'.$size.'_';
		if (!is_null($crop_size))
			$target_file .= 'crp'.$crop_size.'_';
		$target_file .= 'cs'.$constraint.'_';
		$target_file .= $set_hash.'.svg';

		// if target file exists, don't create it
		if (file_exists($target_file))
			return URL::from_file_path($target_file);

		// get image data
		$manager = GalleryManager::get_instance();

		$group_image_ids = array();
		foreach ($id_list as $group_id)
			$group_image_ids[$group_id] = self::get_group_image_id($group_id);

		if (count($group_image_ids) == 0) {
			$images = array();  // make empty array so we can fail properly

		} else {
			$images = $manager->get_items(
					array('id', 'filename'),
					array('id' => array_values($group_image_ids))
				);
		}

		// report potential misbehavior
		if (count($images) == 0) {
			trigger_error('Gallery: Requested empty sprite!', E_USER_NOTICE);
			return '';
		}

		// open target file for writing
		$file = fopen($target_file, 'w');

		if ($file === false) {
			trigger_error('Gallery: Unable to create sprite file for writing!', E_USER_ERROR);
			return '';
		}

		// generate header code
		fwrite($file, '<svg version="1.1" baseProfile="tiny" width="100%" height="100%" ');
		fwrite($file, 'viewBox="0 0 '.$size.' '.$size.'" ');
		fwrite($file, 'xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">');
		fwrite($file, '<defs><style type="text/css">image{visibility:hidden;}image:target{visibility:visible;}</style></defs>');

		// embed each image individually
		foreach ($images as $image) {
			$original_file = $this->image_path.$image->filename;
			$image_file = $this->create_optimized_image($original_file, $size, $constraint, $crop_size);
			$group_id = array_search($image->id, $group_image_ids);

			// skip images which can't be loaded
			if (!file_exists($image_file))
				continue;

			fwrite($file, '<image id="'.$group_id.'" x="0" y="0" width="'.$size.'" height="'.$size.'" ');
			fwrite($file, 'xlink:href="data:image/jpg;base64,'.base64_encode(file_get_contents($image_file)));
			fwrite($file, '"/>');
		}

		// close target file and clean up
		fwrite($file, '</svg>');
		fclose($file);

		return URL::from_file_path($target_file);
	}

	/**
	 * Generate SVG sprite composite image based on container id list. This image will embed
	 * other images and provide easy mechanism for displaying them. While this process does
	 * generate files 15-20% bigger (on average) TCP/IP protocol, due to its initial
	 * throttling, will favor these composites over individual requests for separate small
	 * files.
	 *
	 * Note: Instead of image id, thumbnails will be stored under container id for easier access.
	 *
	 * @param array $id_list,
	 * @param integer $size
	 * @param integer $constraint
	 * @param integer $crop_size
	 * @return string
	 */
	public function create_container_sprite_image($id_list, $size, $constraint=Thumbnail::CONSTRAIN_BOTH, $crop_size=null) {
		// prepare id hash
		sort($id_list);
		$set_hash = hash('sha256', implode('-', $id_list));

		// make sure constraint is specified
		if (is_null($constraint))
			$constraint = Thumbnail::CONSTRAIN_BOTH;

		// generate target file name
		$target_file = $this->optimized_path;
		$target_file .= 'containers_'.$size.'_';
		if (!is_null($crop_size))
			$target_file .= 'crp'.$crop_size.'_';
		$target_file .= 'cs'.$constraint.'_';
		$target_file .= $set_hash.'.svg';

		// if target file exists, don't create it
		if (file_exists($target_file))
			return URL::from_file_path($target_file);

		// get image data
		$manager = GalleryManager::get_instance();

		$container_image_ids = array();
		foreach ($id_list as $container_id)
			$container_image_ids[$container_id] = self::get_container_image_id($container_id);

		if (count($container_image_ids) == 0) {
			$images = array();  // make empty array so we can fail properly

		} else {
			$images = $manager->get_items(
					array('id', 'filename'),
					array('id' => array_values($container_image_ids))
				);
		}

		// report potential misbehavior
		if (count($images) == 0) {
			trigger_error('Gallery: Requested empty sprite!', E_USER_NOTICE);
			return '';
		}

		// open target file for writing
		$file = fopen($target_file, 'w');

		if ($file === false) {
			trigger_error('Gallery: Unable to create sprite file for writing!', E_USER_ERROR);
			return '';
		}

		// generate header code
		fwrite($file, '<svg version="1.1" baseProfile="tiny" width="100%" height="100%" ');
		fwrite($file, 'viewBox="0 0 '.$size.' '.$size.'" ');
		fwrite($file, 'xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">');
		fwrite($file, '<defs><style type="text/css">image{visibility:hidden;}image:target{visibility:visible;}</style></defs>');

		// embed each image individually
		foreach ($images as $image) {
			$original_file = $this->image_path.$image->filename;
			$image_file = $this->create_optimized_image($original_file, $size, $constraint, $crop_size);
			$container_id = array_search($image->id, $container_image_ids);

			// skip images which can't be loaded
			if (!file_exists($image_file))
				continue;

			fwrite($file, '<image id="'.$container_id.'" x="0" y="0" width="'.$size.'" height="'.$size.'" ');
			fwrite($file, 'xlink:href="data:image/jpg;base64,'.base64_encode(file_get_contents($image_file)));
			fwrite($file, '"/>');
		}

		// close target file and clean up
		fwrite($file, '</svg>');
		fclose($file);

		return URL::from_file_path($target_file);
	}
}

?>
