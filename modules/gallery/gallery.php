<?php

/**
 * Gallery Module
 *
 * @author MeanEYE.rcf
 */

class gallery extends Module {

	/**
	 * Constructor
	 */
	function __construct() {
		$this->file = __FILE__;
		parent::Module();
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
				case 'show_image':
					$this->tag_Image($level, $params, $children);
					break;

				case 'show_image_list':
					$this->tag_ImageList($level, $params, $children);
					break;

				case 'show_group':
					$this->tag_Group($level, $params, $children);
					break;

				case 'show_group_list':
					$this->tag_GroupList($level, $params, $children);
					break;

				case 'show_container':
					$this->tag_Container($level, $params, $children);
					break;

				case 'show_container_list':
					$this->tag_ContainerList($level, $params, $children);
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
					$this->showImages($level);
					break;

				case 'images_upload':
					$this->uploadImage($level);
					break;

				case 'images_upload_save':
					$this->uploadImage_Save($level);
					break;

				case 'images_change':
					$this->changeImage($level);
					break;

				case 'images_save':
					$this->saveImage($level);
					break;

				case 'images_delete':
					$this->deleteImage($level);
					break;

				case 'images_delete_commit':
					$this->deleteImage_Commit($level);
					break;

				// ---

				case 'groups':
					$this->showGroups($level);
					break;

				case 'groups_create':
					$this->createGroup($level);
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

				// ---

				case 'containers':
					$this->showContainers($level);
					break;

				case 'containers_create':
					$this->createContainer($level);
					break;

				case 'containers_change':
					$this->changeContainer($level);
					break;

				case 'containers_save':
					$this->saveContainer($level);
					break;

				case 'containers_delete':
					$this->deleteContainer($level);
					break;

				case 'containers_delete_commit':
					$this->deleteContainer_Commit($level);
					break;

				case 'containers_groups':
					$this->containerGroups($level);
					break;

				case 'containers_groups_save':
					$this->containerGroups_Save($level);
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	function onInit() {
		global $db_active, $db;

		$sql = "
			CREATE TABLE `gallery` (
				`id` int(11) NOT NULL AUTO_INCREMENT ,
				`group` int(11) DEFAULT NULL ,
				`title` VARCHAR( 255 ) NOT NULL ,
				`description` TEXT NOT NULL ,
				`size` BIGINT NOT NULL ,
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
				`name` varchar(50) COLLATE utf8_bin NOT NULL,
				`description` TEXT NOT NULL ,
				PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		$sql = "
			CREATE TABLE IF NOT EXISTS `gallery_containers` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`name` varchar(50) COLLATE utf8_bin NOT NULL,
				`description` TEXT NOT NULL ,
				PRIMARY KEY (`id`)
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
	function onDisable() {
		global $db_active, $db;

		$sql = "DROP TABLE IF EXISTS `gallery`, `gallery_groups`, `gallery_containers`, `gallery_group_membership`;";

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
			$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/gallery.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
		}

		// register backend
		if ($ModuleHandler->moduleExists('backend')) {
			$backend = $ModuleHandler->getObjectFromName('backend');

			$gallery_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_gallery'),
					url_GetFromFilePath($this->path.'images/icon.png'),
					'javascript:void(0);',
					$level=5
				);

			$gallery_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_images'),
								url_GetFromFilePath($this->path.'images/images.png'),
								window_Open( // on click open window
											'gallery_images',
											670,
											$this->getLanguageConstant('title_images'),
											true, true,
											backend_UrlMake($this->name, 'images')
										),
								$level=5
							));

			$gallery_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_groups'),
								url_GetFromFilePath($this->path.'images/groups.png'),
								window_Open( // on click open window
											'gallery_groups',
											450,
											$this->getLanguageConstant('title_groups'),
											true, true,
											backend_UrlMake($this->name, 'groups')
										),
								$level=5
							));

			$gallery_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_containers'),
								url_GetFromFilePath($this->path.'images/containers.png'),
								window_Open( // on click open window
											'gallery_containers',
											490,
											$this->getLanguageConstant('title_containers'),
											true, true,
											backend_UrlMake($this->name, 'containers')
										),
								$level=5
							));

			$backend->addMenu($this->name, $gallery_menu);
		}
	}

	/**
	 * Show images management form
	 * @param integer $level
	 */
	function showImages($level) {
		$template = new TemplateHandler('images_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> backend_WindowHyperlink(
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
		$template->parse($level);
	}

	/**
	 * Provides a form for uploading multiple images
	 * @param integer $level
	 */
	function uploadImage($level) {
		$template = new TemplateHandler('images_upload.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'images_upload_save'),
					'cancel_action'	=> window_Close('gallery_images_upload')
				);

		$template->registerTagHandler('_group_list', &$this, 'tag_GroupList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Save uploaded images
	 * @param integer $level
	 */
	function uploadImage_Save($level) {
		$manager = new GalleryManager();

		$title = fix_chars($_REQUEST['title']);
		$group = fix_id($_REQUEST['group']);
		$description = fix_chars($_REQUEST['description']);
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
		$template->parse($level);
	}

	/**
	 * Pring image data editing form
	 * @param integer $level
	 */
	function changeImage($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new GalleryManager();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('images_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('_group_list', &$this, 'tag_GroupList');

		$params = array(
					'id'			=> $item->id,
					'group'			=> $item->group,
					'title'			=> unfix_chars($item->title),
					'description'	=> unfix_chars($item->description),
					'size'			=> $item->size,
					'filename'		=> $item->filename,
					'timestamp'		=> $item->timestamp,
					'visible'		=> $item->visible,
					'form_action'	=> backend_UrlMake($this->name, 'images_save'),
					'cancel_action'	=> window_Close('gallery_images_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Save changed image data
	 * @param integer $level
	 */
	function saveImage($level) {
		$manager = new GalleryManager();

		$id = fix_id($_REQUEST['id']);
		$title = fix_chars($_REQUEST['title']);
		$group = empty($_REQUEST['group']) ? 'null' : fix_id($_REQUEST['group']);
		$description = fix_chars($_REQUEST['description']);
		$visible = $_REQUEST['visible'] == 'on' ? 1 : 0;

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
		$template->parse($level);

	}

	/**
	 * Print confirmation dialog
	 * @param integer $level
	 */
	function deleteImage($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new GalleryManager();

		$item = $manager->getSingleItem(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_image_delete"),
					'name'			=> $item->title,
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
		$template->parse($level);
	}

	function deleteImage_Commit($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));

		$manager = new GalleryManager();

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
		$template->parse($level);
	}

	/**
	 * Show group management form
	 * @param integer $level
	 */
	function showGroups($level) {
		$template = new TemplateHandler('groups_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> backend_WindowHyperlink(
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
		$template->parse($level);
	}

	/**
	 * Input form for creating new group
	 * @param integer $level
	 */
	function createGroup($level) {
		$template = new TemplateHandler('groups_create.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'groups_save'),
					'cancel_action'	=> window_Close('gallery_groups_create')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Group change form
	 * @param integer $level
	 */
	function changeGroup($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new GalleryGroupManager();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('groups_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $item->id,
					'name'			=> unfix_chars($item->name),
					'description'	=> unfix_chars($item->description),
					'form_action'	=> backend_UrlMake($this->name, 'groups_save'),
					'cancel_action'	=> window_Close('gallery_groups_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Save new or changed group data
	 * @param integer $level
	 */
	function saveGroup($level) {
		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;

		$data = array(
			'name' 			=> fix_chars($_REQUEST['name']),
			'description' 	=> fix_chars($_REQUEST['description']),
		);

		$manager = new GalleryGroupManager();

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
		$template->parse($level);
	}

	/**
	 * Delete group confirmation dialog
	 * @param integer $level
	 */
	function deleteGroup($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new GalleryGroupManager();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_group_delete"),
					'name'			=> $item->name,
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
		$template->parse($level);
	}

	/**
	 * Delete group from the system
	 * @param integer $level
	 */
	function deleteGroup_Commit($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new GalleryManager();
		$group_manager = new GalleryGroupManager();

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
		$template->parse($level);
	}

	/**
	 * Show container management form
	 * @param integer $level
	 */
	function showContainers($level) {
		$template = new TemplateHandler('containers_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> backend_WindowHyperlink(
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
		$template->parse($level);
	}

	/**
	 * Input form for creating new group container
	 * @param integer $level
	 */
	function createContainer($level) {
		$template = new TemplateHandler('containers_create.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'containers_save'),
					'cancel_action'	=> window_Close('gallery_containers_create')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Container change form
	 * @param integer $level
	 */
	function changeContainer($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new GalleryContainerManager();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('containers_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $item->id,
					'name'			=> unfix_chars($item->name),
					'description'	=> unfix_chars($item->description),
					'form_action'	=> backend_UrlMake($this->name, 'containers_save'),
					'cancel_action'	=> window_Close('gallery_containers_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Save new or changed group container data
	 * @param integer $level
	 */
	function saveContainer($level) {
		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;

		$data = array(
			'name' 			=> fix_chars($_REQUEST['name']),
			'description' 	=> fix_chars($_REQUEST['description']),
		);

		$manager = new GalleryContainerManager();

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
		$template->parse($level);
	}

	/**
	 * Delete container confirmation dialog
	 * @param integer $level
	 */
	function deleteContainer($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new GalleryContainerManager();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant('message_container_delete'),
					'name'			=> $item->name,
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
		$template->parse($level);
	}

	/**
	 * Delete container from the system
	 * @param integer $level
	 */
	function deleteContainer_Commit($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new GalleryContainerManager();
		$membership_manager = new GalleryGroupMembershipManager();

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
		$template->parse($level);
	}

	/**
	 * Print a form containing all the links within a group
	 * @param integer $level
	 */
	function containerGroups($level) {
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
		$template->parse($level);
	}

	/**
	 * Save container group memberships
	 * @param integer level
	 */
	function containerGroups_Save($level) {
		$container = fix_id(fix_chars($_REQUEST['container']));
		$membership_manager = new GalleryGroupMembershipManager();

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
		$template->parse($level);
	}


	/**
	 * Image tag handler
	 *
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	function tag_Image($level, $tag_params, $children) {
		if (!isset($tag_params['id']) && !isset($tag_params['group'])) return;

		$manager = new GalleryManager();

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
			$template->parse($level);
		}
	}

	/**
	 * Image list tag handler
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	function tag_ImageList($level, $tag_params, $children) {
		$manager = new GalleryManager();

		$conditions = array();

		if (!isset($tag_params['show_invisible']))
			$conditions['visible'] = 1;

		if (isset($tag_params['group']))
			$conditions['group'] = fix_id($tag_params['group']);

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
			$template->parse($level);
		}
	}

	/**
	 * Group list tag handler
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	function tag_Group($level, $tag_params, $children) {
		if (!isset($tag_params['id'])) return;

		if (isset($tag_params['id'])) {
			// display a specific group
			$id = fix_id($tag_params['id']);
		} else if (isset($tag_params['container'])) {
			// display first group in specific container
			$container = fix_id($tag_params['container']);
			$manager = new GalleryGroupManager();
			$membership_manager = new GalleryGroupMembershipManager();

			$id = $membership_manager->getSingleItem('group', array('container' => $container));
		} else {
			// no container nor group id was specified
			return;
		}

		$manager = new GalleryGroupManager();
		$item = $manager->getSingleItem(
								$manager->getFieldNames(),
								array(
									'id' 		=> $id,
									'visible'	=> 1
								)
							);

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
						'name'			=> $item->name,
						'description'	=> $item->description,
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}

	/**
	 * Group list tag handler
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	function tag_GroupList($level, $tag_params, $children) {
		$manager = new GalleryGroupManager();

		$conditions = array();
		if (isset($tag_params['container'])) {
			$container = fix_id($tag_params['container']);
			$membership_manager = new GalleryGroupMembershipManager();

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
								array('name')
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
							'name'			=> $item->name,
							'description'	=> $item->description,
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
				$template->parse($level);
			}

	}

	/**
	 * Container tag handler
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	function tag_Container($level, $tag_params, $children) {
		if (!isset($tag_params['id'])) return;

		$id = fix_id($tag_params['id']);
		$manager = new GalleryContainerManager();

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
						'name'			=> $item->name,
						'description'	=> $item->description,
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}

	/**
	 * Container list tag handler
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	function tag_ContainerList($level, $tag_params, $children) {
		$manager = new GalleryContainerManager();

		$items = $manager->getItems(
								$manager->getFieldNames(),
								array(),
								array('name')
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
						'name'			=> $item->name,
						'description'	=> $item->description,
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
			$template->parse($level);
		}

	}

	/**
	 * Container groups list tag handler
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	function tag_ContainerGroups($level, $tag_params, $children) {
		if (!isset($tag_params['container'])) return;

		$container = fix_id($tag_params['container']);
		$manager = new GalleryGroupManager();
		$membership_manager = new GalleryGroupMembershipManager();

		$memberships = $membership_manager->getItems(
												array('group'),
												array('container' => $container)
											);

		$gallery_ids = array();
		if (count($memberships) > 0)
			foreach($memberships as $membership)
				$gallery_ids[] = $membership->group;

		$items = $manager->getItems($manager->getFieldNames(), array(), array('name'));

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
				$template->parse($level);
			}
	}

	/**
	 * This function provides JSON image objects instead of standard
	 * HTML (XML) output. Function takes all the parameters from $_REQUEST.
	 */
	function json_Image() {
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

		$manager = new GalleryManager();

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
	function json_ImageList() {
		define('_OMIT_STATS', 1);

		$manager = new GalleryManager();
		$conditions = array('visible' => 1);

		if (isset($_REQUEST['group']))
			$conditions['group'] = fix_id($_REQUEST['group']);

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
	function json_Group() {
		define('_OMIT_STATS', 1);

		if (isset($_REQUEST['id'])) {
			// display a specific group
			$id = fix_id($_REQUEST['id']);

		} else if (isset($_REQUEST['container'])) {
			// display first group in specific container
			$container = fix_id($_REQUEST['container']);
			$manager = new GalleryGroupManager();
			$membership_manager = new GalleryGroupMembershipManager();

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

		$manager = new GalleryGroupManager();
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
	function json_GroupList() {
		define('_OMIT_STATS', 1);

		$manager = new GalleryGroupManager();
		$conditions = array();

		if (isset($_REQUEST['contanier'])) {
			$container = fix_id($_REQUEST['container']);
			$membership_manager = new GalleryGroupMembershipManager();

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
	function json_Container() {
		define('_OMIT_STATS', 1);

		if (!isset($_REQUEST['id'])) return;

		$id = fix_id($_REQUEST['id']);
		$manager = new GalleryContainerManager();

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
	function json_ContainerList() {
		define('_OMIT_STATS', 1);

		$manager = new GalleryContainerManager();

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
	 * @param $filename
	 */
	function _getFileName($filename) {
		return md5($filename.strval(time())).'.'.pathinfo(strtolower($filename), PATHINFO_EXTENSION);
	}

	/**
	 * Get image URL
	 *
	 * @param resource $item
	 * @return string
	 */
	function _getImageURL($item) {
		return url_GetFromFilePath($this->path.'images/'.$item->filename);
	}

	/**
	 * Get thumbnail URL
	 *
	 * @param resource $item
	 * @return string
	 */
	function _getThumbnailURL($item) {
		return url_GetFromFilePath($this->path.'thumbnails/'.$item->filename);
	}

	/**
	 * Get image ID by filename
	 *
	 * @param string $filename
	 * @return integer
	 */
	function _getImageIdByFileName($filename) {
		$manager = new GalleryManager();
		return $manager->getItemValue('id', array('filename' => $filename));
	}

	/**
	 * Saves image from specified field name and return error code
	 *
	 * @param string $field_name
	 * @return array
	 */
	function _createImage($field_name, $thumb_size) {
		$result = array(
					'error'		=> false,
					'message'	=> '',
				);
		$filename = $this->_getFileName($_FILES[$field_name]['name']);

		if (is_uploaded_file($_FILES[$field_name]['tmp_name'])) {
			if (in_array(
					pathinfo(strtolower($_FILES[$field_name]['name']), PATHINFO_EXTENSION),
					split(',', $this->settings['image_extensions'])
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
	 * @param string $filename
	 */
	function _createThumbnail($filename, $thumb_size) {
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

	function __construct() {
		parent::ItemManager('gallery');

		$this->addProperty('id', 'int');
		$this->addProperty('group', 'int');
		$this->addProperty('title', 'varchar');
		$this->addProperty('description', 'text');
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
}

class GalleryGroupManager extends ItemManager {

	function __construct() {
		parent::ItemManager('gallery_groups');

		$this->addProperty('id', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('description', 'text');
	}
}

class GalleryContainerManager extends ItemManager {

	function __construct() {
		parent::ItemManager('gallery_containers');

		$this->addProperty('id', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('description', 'text');
	}
}

class GalleryGroupMembershipManager extends ItemManager {

	function __construct() {
		parent::ItemManager('gallery_group_membership');

		$this->addProperty('id', 'int');
		$this->addProperty('group', 'int');
		$this->addProperty('container', 'int');
	}
}
