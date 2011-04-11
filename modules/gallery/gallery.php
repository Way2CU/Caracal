<?php

/**
 * Gallery Module
 *
 * @author MeanEYE.rcf
 */

class gallery extends Module {
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

			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/slideshow.js'), 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/lightbox.js'), 'type'=>'text/javascript'));
			$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/lightbox.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));

			// load backend files if needed
			if ($section == 'backend') {
				$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/gallery.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
				$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/gallery_toolbar.js'), 'type'=>'text/javascript'));

				if (MainLanguageHandler::getInstance()->isRTL())
					$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/gallery_rtl.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			}
		}

		// register backend
		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			$gallery_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_gallery'),
					url_GetFromFilePath($this->path.'images/icon.png'),
					'javascript:void(0);',
					$level=5
				);

			$gallery_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_images'),
								url_GetFromFilePath($this->path.'images/images.png'),
								window_Open( // on click open window
											'gallery_images',
											670,
											$this->getLanguageConstant('title_images'),
											true, true,
											backend_UrlMake($this->name, 'images')
										),
								5  // level
							));

			$gallery_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_groups'),
								url_GetFromFilePath($this->path.'images/groups.png'),
								window_Open( // on click open window
											'gallery_groups',
											450,
											$this->getLanguageConstant('title_groups'),
											true, true,
											backend_UrlMake($this->name, 'groups')
										),
								5  // level
							));

			$gallery_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_containers'),
								url_GetFromFilePath($this->path.'images/containers.png'),
								window_Open( // on click open window
											'gallery_containers',
											490,
											$this->getLanguageConstant('title_containers'),
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
					$this->showImages();
					break;

				case 'images_upload':
					$this->uploadImage();
					break;

				case 'images_upload_save':
					$this->uploadImage_Save();
					break;

				case 'images_change':
					$this->changeImage();
					break;

				case 'images_save':
					$this->saveImage();
					break;

				case 'images_delete':
					$this->deleteImage();
					break;

				case 'images_delete_commit':
					$this->deleteImage_Commit();
					break;

				// ---

				case 'groups':
					$this->showGroups();
					break;

				case 'groups_create':
					$this->createGroup();
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

				// ---

				case 'containers':
					$this->showContainers();
					break;

				case 'containers_create':
					$this->createContainer();
					break;

				case 'containers_change':
					$this->changeContainer();
					break;

				case 'containers_save':
					$this->saveContainer();
					break;

				case 'containers_delete':
					$this->deleteContainer();
					break;

				case 'containers_delete_commit':
					$this->deleteContainer_Commit();
					break;

				case 'containers_groups':
					$this->containerGroups();
					break;

				case 'containers_groups_save':
					$this->containerGroups_Save();
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

		$sql = "
			CREATE TABLE `gallery` (
				`id` int(11) NOT NULL AUTO_INCREMENT ,
				`group` int(11) DEFAULT NULL ,";

		foreach($list as $language)
			$sql .= "`title_{$language}` VARCHAR( 255 ) NOT NULL DEFAULT '',";

		foreach($list as $language)
			$sql .= "`description_{$language}` TEXT NOT NULL ,";

		$sql .= "`size` BIGINT NOT NULL ,
				`filename` VARCHAR( 40 ) NOT NULL ,
				`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
				`visible` BOOLEAN NOT NULL DEFAULT '1',
				PRIMARY KEY ( `id` ),
				KEY `group` (`group`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		$sql = "
			CREATE TABLE IF NOT EXISTS `gallery_groups` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`text_id` varchar(32) COLLATE utf8_bin NULL,";

		foreach($list as $language)
			$sql .= "`name_{$language}` VARCHAR( 50 ) NOT NULL,";

		foreach($list as $language)
			$sql .= "`description_{$language}` TEXT NOT NULL,";

		$sql .= "PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		$sql = "
			CREATE TABLE IF NOT EXISTS `gallery_containers` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`text_id` varchar(32) COLLATE utf8_bin NULL,";

		foreach($list as $language)
			$sql .= "`name_{$language}` VARCHAR( 50 ) NOT NULL,";

		foreach($list as $language)
			$sql .= "`description_{$language}` TEXT NOT NULL,";

		$sql .= "PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		$sql = "
			CREATE TABLE IF NOT EXISTS `gallery_group_membership` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`group` int(11) NOT NULL,
				`container` int(11) NOT NULL,
				PRIMARY KEY (`id`),
				KEY `container` (`container`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		if (!array_key_exists('image_extensions', $this->settings))
			$this->saveSetting('image_extensions', 'jpg,jpeg,png');

		if (!array_key_exists('thumbnail_size', $this->settings))
			$this->saveSetting('thumbnail_size', '100');
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db_active, $db;

		$sql = "DROP TABLE IF EXISTS `gallery`, `gallery_groups`, `gallery_containers`, `gallery_group_membership`;";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Show images management form
	 */
	private function showImages() {
		$template = new TemplateHandler('images_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('upload_images'),
										'gallery_images_upload', 400,
										$this->getLanguageConstant('title_images_upload'),
										true, false,
										$this->name,
										'images_upload'
									),
					'link_groups'	=> url_MakeHyperlink(
										$this->getLanguageConstant('groups'),
										window_Open( // on click open window
											'gallery_groups',
											500,
											$this->getLanguageConstant('title_groups'),
											true, true,
											backend_UrlMake($this->name, 'groups')
										)
									)
					);

		$template->registerTagHandler('_image_list', &$this, 'tag_ImageList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Provides a form for uploading multiple images
	 */
	private function uploadImage() {
		$template = new TemplateHandler('images_upload.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'images_upload_save'),
					'cancel_action'	=> window_Close('gallery_images_upload')
				);

		$template->registerTagHandler('_group_list', &$this, 'tag_GroupList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save uploaded images
	 */
	private function uploadImage_Save() {
		$manager = GalleryManager::getInstance();

		$title = fix_chars($this->getMultilanguageField('title'));
		$group = fix_id($_REQUEST['group']);
		$description = escape_chars($this->getMultilanguageField('description'));
		$visible = isset($_REQUEST['visible']) ? 1 : 0;

		$result = $this->_createImage('image', $this->settings['thumbnail_size']);

		if (!$result['error']) {
			$data = array(
						'group'			=> $group,
						'title'			=> $title,
						'description'	=> $description,
						'size'			=> $_FILES['image']['size'],
						'filename'		=> $result['filename'],
						'visible'		=> $visible,
					);

			$manager->insertData($data);
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $result['message'],
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('gallery_images_upload').";".window_ReloadContent('gallery_images')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Pring image data editing form
	 */
	private function changeImage() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = GalleryManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('images_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('_group_list', &$this, 'tag_GroupList');

		$params = array(
					'id'			=> $item->id,
					'group'			=> $item->group,
					'title'			=> unfix_chars($item->title),
					'description'	=> $item->description,
					'size'			=> $item->size,
					'filename'		=> $item->filename,
					'timestamp'		=> $item->timestamp,
					'visible'		=> $item->visible,
					'form_action'	=> backend_UrlMake($this->name, 'images_save'),
					'cancel_action'	=> window_Close('gallery_images_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save changed image data
	 */
	private function saveImage() {
		$manager = GalleryManager::getInstance();

		$id = fix_id($_REQUEST['id']);
		$title = fix_chars($this->getMultilanguageField('title'));
		$group = !empty($_REQUEST['group']) ? fix_id($_REQUEST['group']) : 'null';
		$description = escape_chars($this->getMultilanguageField('description'));
		$visible = isset($_REQUEST['visible']) && ($_REQUEST['visible'] == 'on' || $_REQUEST['visible'] == '1') ? 1 : 0;

		$data = array(
					'title'			=> $title,
					'group'			=> $group,
					'description'	=> $description,
					'visible'		=> $visible
				);

		$manager->updateData($data, array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_image_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('gallery_images_change').";".window_ReloadContent('gallery_images')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();

	}

	/**
	 * Print confirmation dialog
	 */
	private function deleteImage() {
		global $language;

		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = GalleryManager::getInstance();

		$item = $manager->getSingleItem(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_image_delete"),
					'name'			=> $item->title[$language],
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'gallery_images_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'images_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('gallery_images_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Complete removal of specified image
	 */
	private function deleteImage_Commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));

		$manager = GalleryManager::getInstance();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_image_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('gallery_images_delete').";".window_ReloadContent('gallery_images')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show group management form
	 */
	private function showGroups() {
		$template = new TemplateHandler('groups_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('create_group'),
										'gallery_groups_create', 400,
										$this->getLanguageConstant('title_groups_create'),
										true, false,
										$this->name,
										'groups_create'
									),
					);

		$template->registerTagHandler('_group_list', &$this, 'tag_GroupList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Input form for creating new group
	 */
	private function createGroup() {
		$template = new TemplateHandler('groups_create.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'groups_save'),
					'cancel_action'	=> window_Close('gallery_groups_create')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Group change form
	 */
	private function changeGroup() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = GalleryGroupManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('groups_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $item->id,
					'text_id'		=> unfix_chars($item->text_id),
					'name'			=> unfix_chars($item->name),
					'description'	=> $item->description,
					'form_action'	=> backend_UrlMake($this->name, 'groups_save'),
					'cancel_action'	=> window_Close('gallery_groups_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save new or changed group data
	 */
	private function saveGroup() {
		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;

		$data = array(
			'text_id'		=> fix_chars($_REQUEST['text_id']),
			'name' 			=> fix_chars($this->getMultilanguageField('name')),
			'description' 	=> escape_chars($this->getMultilanguageField('description')),
		);

		$manager = GalleryGroupManager::getInstance();

		if (!is_null($id)) {
			$manager->updateData($data, array('id' => $id));
			$window_name = 'gallery_groups_change';
			$message = $this->getLanguageConstant('message_group_changed');
		} else {
			$manager->insertData($data);
			$window_name = 'gallery_groups_create';
			$message = $this->getLanguageConstant('message_group_created');
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $message,
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window_name).";".window_ReloadContent('gallery_groups')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Delete group confirmation dialog
	 */
	private function deleteGroup() {
		global $language;

		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = GalleryGroupManager::getInstance();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_group_delete"),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'gallery_groups_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'groups_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('gallery_groups_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Delete group from the system
	 */
	private function deleteGroup_Commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = GalleryManager::getInstance();
		$group_manager = GalleryGroupManager::getInstance();

		$manager->deleteData(array('group' => $id));
		$group_manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_group_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('gallery_groups_delete').";".window_ReloadContent('gallery_groups').";".window_ReloadContent('gallery_images')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show container management form
	 */
	private function showContainers() {
		$template = new TemplateHandler('containers_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('create_container'),
										'gallery_containers_create', 400,
										$this->getLanguageConstant('title_containers_create'),
										true, false,
										$this->name,
										'containers_create'
									),
					);

		$template->registerTagHandler('_container_list', &$this, 'tag_ContainerList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Input form for creating new group container
	 */
	private function createContainer() {
		$template = new TemplateHandler('containers_create.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'containers_save'),
					'cancel_action'	=> window_Close('gallery_containers_create')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Container change form
	 */
	private function changeContainer() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = GalleryContainerManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('containers_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $item->id,
					'text_id'		=> unfix_chars($item->text_id),
					'name'			=> unfix_chars($item->name),
					'description'	=> $item->description,
					'form_action'	=> backend_UrlMake($this->name, 'containers_save'),
					'cancel_action'	=> window_Close('gallery_containers_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save new or changed group container data
	 */
	private function saveContainer() {
		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;

		$data = array(
			'text_id'		=> fix_chars($_REQUEST['text_id']),
			'name' 			=> fix_chars($this->getMultilanguageField('name')),
			'description' 	=> escape_chars($this->getMultilanguageField('description')),
		);

		$manager = GalleryContainerManager::getInstance();

		if (!is_null($id)) {
			$manager->updateData($data, array('id' => $id));
			$window_name = 'gallery_containers_change';
			$message = $this->getLanguageConstant('message_container_changed');
		} else {
			$manager->insertData($data);
			$window_name = 'gallery_containers_create';
			$message = $this->getLanguageConstant('message_container_created');
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $message,
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window_name).";".window_ReloadContent('gallery_containers')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Delete container confirmation dialog
	 */
	private function deleteContainer() {
		global $language;

		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = GalleryContainerManager::getInstance();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant('message_container_delete'),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->getLanguageConstant('delete'),
					'no_text'		=> $this->getLanguageConstant('cancel'),
					'yes_action'	=> window_LoadContent(
											'gallery_containers_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'containers_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('gallery_containers_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Delete container from the system
	 */
	private function deleteContainer_Commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = GalleryContainerManager::getInstance();
		$membership_manager = GalleryGroupMembershipManager::getInstance();

		$manager->deleteData(array('id' => $id));
		$membership_manager->deleteData(array('container' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_container_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('gallery_containers_delete').";".window_ReloadContent('gallery_containers')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Print a form containing all the links within a group
	 */
	private function containerGroups() {
		$container_id = fix_id(fix_chars($_REQUEST['id']));

		$template = new TemplateHandler('containers_groups.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'container'		=> $container_id,
					'form_action'	=> backend_UrlMake($this->name, 'containers_groups_save'),
					'cancel_action'	=> window_Close('gallery_containers_groups')
				);

		$template->registerTagHandler('_container_groups', &$this, 'tag_ContainerGroups');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save container group memberships
	 */
	private function containerGroups_Save() {
		$container = fix_id(fix_chars($_REQUEST['container']));
		$membership_manager = GalleryGroupMembershipManager::getInstance();

		// fetch all ids being set to specific group
		$gallery_ids = array();
		foreach ($_REQUEST as $key => $value) {
			if (substr($key, 0, 9) == 'group_id_' && $value == 1)
				$gallery_ids[] = fix_id(substr($key, 8));
		}

		// remove old memberships
		$membership_manager->deleteData(array('container' => $container));

		// save new memberships
		foreach ($gallery_ids as $id)
			$membership_manager->insertData(array(
											'group'		=> $id,
											'container'	=> $container
										));

		// display message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_container_groups_updated"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('gallery_containers_groups')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}


	/**
	 * Image tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Image($tag_params, $children) {
		if (!isset($tag_params['id']) && !isset($tag_params['group'])) return;

		$manager = GalleryManager::getInstance();

		if (isset($tag_params['id'])) {
			// get specific image
			$id = fix_id($tag_params['id']);
			$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));
		} else {
			// get first image from group (useful for group thumbnails)
			$id = fix_id($tag_params['group']);
			$item = $manager->getSingleItem($manager->getFieldNames(), array('group' => $id));
		}

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('image.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);

		if (is_object($item)) {
			$params = array(
						'id'			=> $item->id,
						'group'			=> $item->group,
						'title'			=> $item->title,
						'description'	=> $item->description,
						'filename'		=> $item->filename,
						'timestamp'		=> $item->timestamp,
						'visible'		=> $item->visible,
						'image'			=> $this->_getImageURL($item),
						'thumbnail'		=> $this->_getThumbnailURL($item),
				);

			$template->restoreXML();
			$template->setLocalParams($params);
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
		$manager = GalleryManager::getInstance();

		$conditions = array();

		if (!isset($tag_params['show_invisible']))
			$conditions['visible'] = 1;

		if (isset($tag_params['group_id']))
			$conditions['group'] = $tag_params['group_id'];

		if (isset($tag_params['group'])) {
			$group_manager = GalleryGroupManager::getInstance();

			$group_id = $group_manager->getItemValue('id', array('text_id' => $tag_params['group']));
			$conditions['group'] = $group_id;
		}

		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('images_list_item.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);
		$template->registerTagHandler('_image', &$this, 'tag_Image');

		$selected = isset($tag_params['selected']) ? fix_id($tag_params['selected']) : -1;

		if (count($items) > 0)
		foreach ($items as $item) {
			$params = array(
						'id'			=> $item->id,
						'group'			=> $item->group,
						'title'			=> $item->title,
						'description'	=> $item->description,
						'filename'		=> $item->filename,
						'timestamp'		=> $item->timestamp,
						'visible'		=> $item->visible,
						'image'			=> $this->_getImageURL($item),
						'thumbnail'		=> $this->_getThumbnailURL($item),
						'item_change'		=> url_MakeHyperlink(
												$this->getLanguageConstant('change'),
												window_Open(
													'gallery_images_change', 	// window id
													400,						// width
													$this->getLanguageConstant('title_images_change'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'images_change'),
														array('id', $item->id)
													)
												)
											),
						'item_delete'		=> url_MakeHyperlink(
												$this->getLanguageConstant('delete'),
												window_Open(
													'gallery_images_delete', 	// window id
													400,						// width
													$this->getLanguageConstant('title_images_delete'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'images_delete'),
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
	 * Group list tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Group($tag_params, $children) {
		if (!isset($tag_params['id'])) return;

		if (isset($tag_params['id'])) {
			// display a specific group
			$id = fix_id($tag_params['id']);

		} else if (isset($tag_params['container'])) {
			// display first group in specific container
			$container = fix_id($tag_params['container']);
			$manager = GalleryGroupManager::getInstance();
			$membership_manager = GalleryGroupMembershipManager::getInstance();

			$id = $membership_manager->getSingleItem('group', array('container' => $container));
		} else {
			// no container nor group id was specified
			return;
		}

		$manager = GalleryGroupManager::getInstance();
		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('group.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);
		$template->registerTagHandler('_image', &$this, 'tag_Image');
		$template->registerTagHandler('_image_list', &$this, 'tag_ImageList');

		if (is_object($item)) {
			$params = array(
						'id'			=> $item->id,
						'text_id'		=> $item->text_id,
						'name'			=> $item->name,
						'description'	=> $item->description,
						'image'			=> $this->_getGroupImage($item)
					);

			$template->restoreXML();
			$template->setLocalParams($params);
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
		global $language;

		$manager = GalleryGroupManager::getInstance();

		$conditions = array();
		$order_by = array();

		if (isset($tag_params['order_by']) && in_array($tag_params['order_by'], $manager->getFieldNames()))
			$order_by[] = fix_chars($tag_params['order_by']); else
			$order_by[] = 'name_'.$language;

		if (isset($tag_params['container'])) {
			$container = fix_id($tag_params['container']);
			$membership_manager = GalleryGroupMembershipManager::getInstance();

			// grab all groups for specified container
			$memberships = $membership_manager->getItems(array('group'), array('container' => $container));

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

		// get groups
		$items = $manager->getItems(
								$manager->getFieldNames(),
								$conditions,
								$order_by
							);

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('groups_list_item.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);
		$template->registerTagHandler('_image', &$this, 'tag_Image');
		$template->registerTagHandler('_image_list', &$this, 'tag_ImageList');

		$selected = isset($tag_params['selected']) ? fix_id($tag_params['selected']) : -1;

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
							'id'			=> $item->id,
							'text_id'		=> $item->text_id,
							'name'			=> $item->name,
							'description'	=> $item->description,
							'image'			=> $this->_getGroupImage($item),
							'selected'		=> $selected,
							'item_change'	=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														'gallery_groups_change', 	// window id
														400,						// width
														$this->getLanguageConstant('title_groups_change'), // title
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
														'gallery_groups_delete', 	// window id
														400,						// width
														$this->getLanguageConstant('title_groups_delete'), // title
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
	 * Container tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Container($tag_params, $children) {
		if (!isset($tag_params['id'])) return;

		$id = fix_id($tag_params['id']);
		$manager = GalleryContainerManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('container.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);
		$template->registerTagHandler('_image', &$this, 'tag_Image');
		$template->registerTagHandler('_image_list', &$this, 'tag_ImageList');
		$template->registerTagHandler('_group', &$this, 'tag_Group');
		$template->registerTagHandler('_group_list', &$this, 'tag_GroupList');

		if (is_object($item)) {
			$params = array(
						'id'			=> $item->id,
						'text_id'		=> $item->text_id,
						'name'			=> $item->name,
						'description'	=> $item->description,
						'image'			=> $this->_getContainerImage($item)
					);

			$template->restoreXML();
			$template->setLocalParams($params);
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

		$manager = GalleryContainerManager::getInstance();

		$items = $manager->getItems(
								$manager->getFieldNames(),
								array(),
								array('name_'.$language)
							);

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('containers_list_item.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);
		$template->registerTagHandler('_image', &$this, 'tag_Image');
		$template->registerTagHandler('_image_list', &$this, 'tag_ImageList');
		$template->registerTagHandler('_group', &$this, 'tag_Group');
		$template->registerTagHandler('_group_list', &$this, 'tag_GroupList');

		$selected = isset($tag_params['selected']) ? fix_id($tag_params['selected']) : -1;

		if (count($items) > 0)
		foreach ($items as $item) {
			$params = array(
						'id'			=> $item->id,
						'text_id'		=> $item->text_id,
						'name'			=> $item->name,
						'description'	=> $item->description,
						'image'			=> $this->_getContainerImage($item),
						'selected'		=> $selected,
						'item_change'	=> url_MakeHyperlink(
												$this->getLanguageConstant('change'),
												window_Open(
													'gallery_containers_change', 	// window id
													400,							// width
													$this->getLanguageConstant('title_containers_change'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'containers_change'),
														array('id', $item->id)
													)
												)
											),
						'item_delete'	=> url_MakeHyperlink(
												$this->getLanguageConstant('delete'),
												window_Open(
													'gallery_containers_delete', 	// window id
													400,							// width
													$this->getLanguageConstant('title_containers_delete'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'containers_delete'),
														array('id', $item->id)
													)
												)
											),
						'item_groups'	=> url_MakeHyperlink(
												$this->getLanguageConstant('container_groups'),
												window_Open(
													'gallery_containers_groups', 	// window id
													400,							// width
													$this->getLanguageConstant('title_containers_groups'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'containers_groups'),
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
	 * Container groups list tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ContainerGroups($tag_params, $children) {
		global $language;

		if (!isset($tag_params['container'])) return;

		$container = fix_id($tag_params['container']);
		$manager = GalleryGroupManager::getInstance();
		$membership_manager = GalleryGroupMembershipManager::getInstance();

		$memberships = $membership_manager->getItems(
												array('group'),
												array('container' => $container)
											);

		$gallery_ids = array();
		if (count($memberships) > 0)
			foreach($memberships as $membership)
				$gallery_ids[] = $membership->group;

		$items = $manager->getItems($manager->getFieldNames(), array(), array('name_'.$language));

		$template = new TemplateHandler('containers_groups_item.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
								'id'				=> $item->id,
								'in_group'			=> in_array($item->id, $gallery_ids) ? 1 : 0,
								'name'				=> $item->name,
								'description'		=> $item->description,
							);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * This function provides JSON image objects instead of standard
	 * HTML (XML) output. Function takes all the parameters from $_REQUEST.
	 */
	private function json_Image() {
		define('_OMIT_STATS', 1);

		if (!isset($_REQUEST['id']) && !isset($_REQUEST['group'])) {
			// invalid params, print blank JSON object with message
			$result = array(
						'error'			=> true,
						'error_message'	=> $this->getLanguageConstant('message_json_error_params'),
					);

			print json_encode($result);
			return;
		};

		$manager = GalleryManager::getInstance();

		if (isset($_REQUEST['id'])) {
			// get specific image
			$id = fix_id($_REQUEST['id']);
			$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));
		} else {
			// get first image from group (useful for group thumbnails)
			$id = fix_id($_REQUEST['group']);
			$item = $manager->getSingleItem($manager->getFieldNames(), array('group' => $id));
		}

		if (is_object($item)) {
			$result = array(
						'error'			=> false,
						'error_message'	=> '',
						'id'			=> $item->id,
						'group'			=> $item->group,
						'title'			=> $item->title,
						'description'	=> $item->description,
						'filename'		=> $item->filename,
						'timestamp'		=> $item->timestamp,
						'visible'		=> $item->visible,
						'image'			=> $this->_getImageURL($item),
						'thumbnail'		=> $this->_getThumbnailURL($item),
					);
		} else {
			$result = array(
						'error'			=> true,
						'error_message'	=> $this->getLanguageConstant('message_json_error_object'),
					);
		}

		print json_encode($result);
	}

	/**
	 * This function provides list of JSON image objects instead of standard
	 * HTML (XML) output. Function takes all the parameters from $_REQUEST.
	 */
	private function json_ImageList() {
		define('_OMIT_STATS', 1);

		$manager = GalleryManager::getInstance();
		$conditions = array('visible' => 1);

		// raw group id was specified
		if (isset($_REQUEST['group_id']))
			$conditions['group'] = fix_id($_REQUEST['group_id']);

		// group text_id was specified, get group ID
		if (isset($_REQUEST['group'])) {
			$group_manager = GalleryGroupManager::getInstance();

			$group_id = $group_manager->getItemValue('id', array('text_id' => $_REQUEST['group']));

			if (!empty($group_id))
				$conditions['group'] = $group_id; else
				$conditions['group'] = -1;
		}

		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'items'			=> array()
				);

		if (count($items) > 0) {
			foreach ($items as $item) {
				$result['items'][] = array(
							'id'			=> $item->id,
							'group'			=> $item->group,
							'title'			=> $item->title,
							'description'	=> $item->description,
							'filename'		=> $item->filename,
							'timestamp'		=> $item->timestamp,
							'visible'		=> $item->visible,
							'image'			=> $this->_getImageURL($item),
							'thumbnail'		=> $this->_getThumbnailURL($item),
						);
			}
		} else {
			$result['error'] = true;
			$result['error_message'] = $this->getLanguageConstant('message_json_error_object');
		}

		print json_encode($result);
	}

	/**
	 * This function provides JSON group object instead of standard
	 * HTML (XML) output. Function takes all the parameters from $_REQUEST.
	 */
	private function json_Group() {
		define('_OMIT_STATS', 1);

		if (isset($_REQUEST['id'])) {
			// display a specific group
			$id = fix_id($_REQUEST['id']);

		} else if (isset($_REQUEST['container'])) {
			// display first group in specific container
			$container = fix_id($_REQUEST['container']);
			$manager = GalleryGroupManager::getInstance();
			$membership_manager = GalleryGroupMembershipManager::getInstance();

			$id = $membership_manager->getSingleItem('group', array('container' => $container));
		} else {
			// no container nor group id was specified
			// invalid params, print blank JSON object with message
			$result = array(
						'error'			=> true,
						'error_message'	=> $this->getLanguageConstant('message_json_error_params'),
					);

			print json_encode($result);
			return;
		}

		$manager = GalleryGroupManager::getInstance();
		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			$result = array(
						'error'			=> false,
						'error_message'	=> '',
						'id'			=> $item->id,
						'name'			=> $item->name,
						'description'	=> $item->description,
					);
		} else {
			$result = array(
						'error'			=> true,
						'error_message'	=> $this->getLanguageConstant('message_json_error_object'),
					);
		}

		print json_encode($result);
	}

	/**
	 * This function provides JSON group objects instead of standard
	 * HTML (XML) output. Function takes all the parameters from $_REQUEST.
	 */
	private function json_GroupList() {
		define('_OMIT_STATS', 1);

		$manager = GalleryGroupManager::getInstance();
		$conditions = array();

		if (isset($_REQUEST['contanier'])) {
			$container = fix_id($_REQUEST['container']);
			$membership_manager = GalleryGroupMembershipManager::getInstance();

			// grab all groups for specified container
			$memberships = $membership_manager->getItems(array('group'), array('container' => $container));

			// extract object values
			$list = array();
			if (count($memberships) > 0)
				foreach($memberships as $membership)
					$list[] = $membership->group;

			// add array as condition value (will be parsed as SQL list)
			// add array as condition value (will be parsed as SQL list)
			if (!empty($list))
				$conditions['id'] = $list; else
				$conditions['id'] = -1;  // ensure no groups are selected
		}

		$items = $manager->getItems(
								$manager->getFieldNames(),
								$conditions,
								array('name')
							);

		$result = array(
					'error' 			=> false,
					'error_message'		=> '',
					'items'				=> array()
				);

		if (count($items) > 0) {
			foreach ($items as $item)
				$result['items'][] = array(
							'id'			=> $item->id,
							'name'			=> $item->name,
							'description'	=> $item->description
						);
		} else {
			$result['error'] = true;
			$result['error_message'] = $this->getLanguageConstant('message_json_error_object');
		}

		print json_encode($result);
	}

	/**
	 * This function provides JSON container object instead of standard
	 * HTML (XML) output. Function takes all the parameters from $_REQUEST.
	 */
	private function json_Container() {
		define('_OMIT_STATS', 1);

		if (!isset($_REQUEST['id'])) return;

		$id = fix_id($_REQUEST['id']);
		$manager = GalleryContainerManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			$result = array(
						'error'			=> false,
						'error_message'	=> '',
						'id'			=> $item->id,
						'name'			=> $item->name,
						'description'	=> $item->description,
					);
		} else {
			$result = array(
						'error'			=> true,
						'error_message'	=> $this->getLanguageConstant('message_json_error_object'),
					);
		}

		print json_encode($result);
	}

	/**
	 * This function provides JSON container objects instead of standard
	 * HTML (XML) output. Function takes all the parameters from $_REQUEST.
	 */
	private function json_ContainerList() {
		define('_OMIT_STATS', 1);

		$manager = GalleryContainerManager::getInstance();

		$items = $manager->getItems(
								$manager->getFieldNames(),
								array(),
								array('name')
							);

		$result = array(
					'error' 			=> false,
					'error_message'		=> '',
					'items'				=> array()
				);

		if (count($items) > 0) {
			foreach ($items as $item)
				$result['items'][] = array(
							'id'			=> $item->id,
							'name'			=> $item->name,
							'description'	=> $item->description
						);
		} else {
			$result['error'] = true;
			$result['error_message'] = $this->getLanguageConstant('message_json_error_object');
		}

		print json_encode($result);
	}

	/**
	 * Returns hash based filename
	 *
	 * @param $filename
	 */
	private function _getFileName($filename) {
		return md5($filename.strval(time())).'.'.pathinfo(strtolower($filename), PATHINFO_EXTENSION);
	}

	/**
	 * Get image URL
	 *
	 * @param resource $item
	 * @return string
	 */
	public function _getImageURL($item) {
		return url_GetFromFilePath($this->path.'images/'.$item->filename);
	}

	/**
	 * Get thumbnail URL
	 *
	 * @param resource $item
	 * @return string
	 */
	public function _getThumbnailURL($item) {
		return url_GetFromFilePath($this->path.'thumbnails/'.$item->filename);
	}

	/**
	 * Get image ID by filename
	 *
	 * @param string $filename
	 * @return integer
	 */
	public function _getImageIdByFileName($filename) {
		$manager = GalleryManager::getInstance();
		return $manager->getItemValue('id', array('filename' => $filename));
	}

	/**
	 * Get group image
	 *
	 * @param resource/integer $group
	 * @return string
	 */
	private function _getGroupImage($group) {
		$result = '';
		$manager = GalleryManager::getInstance();

		$image = $manager->getSingleItem(
										array('filename'),
										array('group' => is_array($group) ? $group : $group->id),
										array('RAND()')
									);

		if (is_object($image))
			$result = $this->_getThumbnailURL($image);

		return $result;
	}

	/**
	 * Get container image
	 *
	 * @param resource $container
	 * @return string
	 */
	private function _getContainerImage($container) {
		$result = '';
		$group_manager = GalleryGroupManager::getInstance();
		$membership_manager = GalleryGroupMembershipManager::getInstance();

		$items = $membership_manager->getItems(
											array('group'),
											array('container' => $container->id),
											array('RAND()')
										);

		if (count($items) > 0) {
			$groups = array();

			foreach($items as $item)
				$groups[] = $item->group;

			$result = $this->_getGroupImage($groups);
		}

		return $result;
	}

	/**
	 * Saves image from specified field name and return error code
	 *
	 * @param string $field_name
	 * @return array
	 */
	private function _createImage($field_name, $thumb_size) {
		$result = array(
					'error'		=> false,
					'message'	=> '',
				);
		$filename = $this->_getFileName($_FILES[$field_name]['name']);

		if (is_uploaded_file($_FILES[$field_name]['tmp_name'])) {
			if (in_array(
					pathinfo(strtolower($_FILES[$field_name]['name']), PATHINFO_EXTENSION),
					explode(',', $this->settings['image_extensions'])
				)) {

				// try moving file to new destination
				if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $this->path.'images/'.$filename) &&
				$this->_createThumbnail($this->path.'images/'.$filename, $thumb_size)) {

					$result['filename'] = $filename;
					$result['message'] = $this->getLanguageConstant('message_image_uploaded');
				} else {
					$result['error'] = true;
					$result['message'] = $this->getLanguageConstant('message_image_save_error');
				}
			} else {
				$result['error'] = true;
				$result['message'] = $this->getLanguageConstant('message_image_invalid_type');
			}

		} else {
			$result['error'] = true;
			$result['message'] = $this->getLanguageConstant('message_image_upload_error');
		}

		return $result;
	}

	/**
	 * Create thumbnail from specified image
	 *
	 * @param string $filename
	 */
	private function _createThumbnail($filename, $thumb_size) {
		$img_source = null;
		switch (pathinfo(strtolower($filename), PATHINFO_EXTENSION)) {
			case 'jpg':
			case 'jpeg':
				$img_source = imagecreatefromjpeg($filename);
				$save_function = @imagejpeg;
				$save_quality = 95;
				break;

			case 'png':
				$img_source = imagecreatefrompng($filename);
				$save_function = @imagepng;
				$save_quality = 9;
				break;
		}

		if (is_null($img_source)) return false;

		$source_width = imagesx($img_source);
		$source_height = imagesy($img_source);

		if ($source_width >= $source_height)
			$scale = $thumb_size / $source_width; else
			$scale = $thumb_size / $source_height;

		$thumb_width = floor($scale * $source_width);
		$thumb_height = floor($scale * $source_height);

		$thumbnail = imagecreatetruecolor($thumb_width, $thumb_height);
		imagecopyresampled($thumbnail, $img_source, 0, 0, 0, 0, $thumb_width, $thumb_height, $source_width, $source_height);

		$save_function($thumbnail, $this->path.'thumbnails/'.pathinfo($filename, PATHINFO_BASENAME), $save_quality);

		return true;
	}
}


class GalleryManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('gallery');

		$this->addProperty('id', 'int');
		$this->addProperty('group', 'int');
		$this->addProperty('title', 'ml_varchar');
		$this->addProperty('description', 'ml_text');
		$this->addProperty('size', 'bigint');
		$this->addProperty('filename', 'varchar');
		$this->addProperty('timestamp', 'timestamp');
		$this->addProperty('visible', 'boolean');
	}

	/**
	 * Override function in order to remove required files along with database data
	 * @param array $conditionals
	 */
	function deleteData($conditionals) {
		$items = $this->getItems(array('filename'), $conditionals);

		$path = dirname(__FILE__).'/';

		if (count($items) > 0)
		foreach ($items as $item) {
			unlink($path.'images/'.$item->filename);
			unlink($path.'thumbnails/'.$item->filename);
		}

		parent::deleteData($conditionals);
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

class GalleryGroupManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('gallery_groups');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('name', 'ml_varchar');
		$this->addProperty('description', 'ml_text');
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

class GalleryContainerManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('gallery_containers');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('name', 'ml_varchar');
		$this->addProperty('description', 'ml_text');
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

class GalleryGroupMembershipManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('gallery_group_membership');

		$this->addProperty('id', 'int');
		$this->addProperty('group', 'int');
		$this->addProperty('container', 'int');
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
