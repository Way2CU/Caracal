<?php

/**
 * SITE LINKS MODULE
 * This module provides a number of useful ways of printing and organising
 * links on your web site.
 *
 * @author MeanEYE.rcf
 */

class links extends Module {

	/**
	 * Constructor
	 *
	 * @return links
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
		switch ($params['action']) {
			case 'show_link_list':
				$this->tag_LinkList($level, $params, $children);
				break;

			case 'show_group':
				$this->tag_Group($level, $params, $children);
				break;

			case 'show_group_list':
				$this->tag_GroupList($level, $params, $children);
				break;

			case 'redirect':
				$this->redirectLink($level);
				break;

			default:
				break;
		}

		// global control actions
		if (isset($params['backend_action']))
		switch ($params['backend_action']) {
			case 'links_list':
				$this->showList($level);
				break;

			case 'links_add':
				$this->addLink($level);
				break;

			case 'links_change':
				$this->changeLink($level);
				break;

			case 'links_save':
				$this->saveLink($level);
				break;

			case 'links_delete':
				$this->deleteLink($level);
				break;

			case 'links_delete_commit':
				$this->deleteLink_Commit($level);
				break;

			// ----

			case 'groups_list':
				$this->showGroups($level);
				break;

			case 'groups_add':
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

			case 'groups_links':
				$this->groupLinks($level);
				break;

			case 'groups_links_save':
				$this->groupLinksSave($level);
				break;

			// ----

			case 'overview':
				$this->showOverview($level);
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
			CREATE TABLE IF NOT EXISTS `links` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`text` varchar(50) COLLATE utf8_bin NOT NULL,
				`description` text COLLATE utf8_bin,
				`url` varchar(255) COLLATE utf8_bin NOT NULL,
				`external` tinyint(1) NOT NULL DEFAULT '1',
				`sponsored` tinyint(1) NOT NULL DEFAULT '0',
				`display_limit` int(11) NOT NULL DEFAULT '0',
				`sponsored_clicks` int(11) NOT NULL DEFAULT '0',
				`total_clicks` int(11) NOT NULL DEFAULT '0',
				`image` int(11),
				PRIMARY KEY (`id`),
				KEY `sponsored` (`sponsored`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		$sql = "
			CREATE TABLE IF NOT EXISTS `link_groups` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`name` varchar(50) COLLATE utf8_bin NOT NULL,
				PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		$sql = "
			CREATE TABLE IF NOT EXISTS `link_membership` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`link` int(11) NOT NULL,
				`group` int(11) NOT NULL,
				PRIMARY KEY (`id`),
				KEY `group` (`group`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		if (!array_key_exists('thumbnail_size', $this->settings))
			$this->saveSetting('thumbnail_size', '100');
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	function onDisable() {
		global $db, $db_active;

		$sql = "DROP TABLE IF EXISTS `links`, `link_groups`, `link_membership`;";
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler;

		// register backend
		if ($ModuleHandler->moduleExists('backend')) {
			$backend = $ModuleHandler->getObjectFromName('backend');

			$links_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_links'),
					url_GetFromFilePath($this->path.'images/icon.png'),
					'javascript:void(0);',
					$level=5
				);

			$links_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_links_manage'),
								url_GetFromFilePath($this->path.'images/manage.png'),
								window_Open( // on click open window
											'links_list',
											730,
											$this->getLanguageConstant('title_links_manage'),
											true, true,
											backend_UrlMake($this->name, 'links_list')
										),
								$level=5
							));

			$links_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_links_groups'),
								url_GetFromFilePath($this->path.'images/groups.png'),
								window_Open( // on click open window
											'groups_list',
											500,
											$this->getLanguageConstant('title_groups_manage'),
											true, true,
											backend_UrlMake($this->name, 'groups_list')
										),
								$level=5
							));

			$links_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_links_overview'),
								url_GetFromFilePath($this->path.'images/overview.png'),
								window_Open( // on click open window
											'links_overview',
											650,
											$this->getLanguageConstant('title_links_overview'),
											true, true,
											backend_UrlMake($this->name, 'overview')
										),
								$level=6
							));

			$backend->addMenu($this->name, $links_menu);
		}
	}

	/**
	 * Show links window
	 * @param integer $level
	 */
	function showList($level) {
		$template = new TemplateHandler('links_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('add'),
										'links_add', 400,
										$this->getLanguageConstant('title_links_add'),
										true, false,
										$this->name,
										'links_add'
									),
					'link_groups'	=> url_MakeHyperlink(
										$this->getLanguageConstant('groups'),
										window_Open( // on click open window
											'groups_list',
											500,
											$this->getLanguageConstant('title_groups_manage'),
											true, true,
											backend_UrlMake($this->name, 'groups_list')
										)
									),
					'link_overview'	=> url_MakeHyperlink(
										$this->getLanguageConstant('overview'),
										window_Open( // on click open window
											'links_overview',
											650,
											$this->getLanguageConstant('title_links_overview'),
											true, true,
											backend_UrlMake($this->name, 'links_overview')
										)
									)
					);

		$template->registerTagHandler('_link_list', &$this, 'tag_LinkList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Show content of a form used for creation of new `link` object
	 * @param integer $level
	 */
	function addLink($level) {
		global $ModuleHandler;

		$template = new TemplateHandler('links_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'with_images'	=> $ModuleHandler->moduleExists('gallery'),
					'form_action'	=> backend_UrlMake($this->name, 'links_save'),
					'cancel_action'	=> window_Close('links_add')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Show content of a form in editing state for sepected `link` object
	 * @param integer $level
	 */
	function changeLink($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new LinksManager();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('links_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $item->id,
					'text'			=> unfix_chars($item->text),
					'description'	=> unfix_chars($item->description),
					'url'			=> unfix_chars($item->url),
					'external'		=> $item->external,
					'sponsored'		=> $item->sponsored,
					'display_limit'	=> $item->display_limit,
					'sponsored_clicks' => $item->sponsored_clicks,
					'form_action'	=> backend_UrlMake($this->name, 'links_save'),
					'cancel_action'	=> window_Close('links_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Save changes existing (or new) to `link` object and display result
	 * @param integer $level
	 */
	function saveLink($level) {
		global $ModuleHandler;

		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;

		$data = array(
			'text' 			=> fix_chars($_REQUEST['text']),
			'description' 	=> fix_chars($_REQUEST['description']),
			'url' 			=> fix_chars($_REQUEST['url']),
			'external' 		=> isset($_REQUEST['external']) ? 1 : 0,
			'sponsored' 	=> isset($_REQUEST['sponsored']) ? 1 : 0,
			'display_limit'	=> fix_id(fix_chars($_REQUEST['display_limit'])),
		);

		$gallery_addon = '';

		// if images are in use and specified
		if ($ModuleHandler->moduleExists('gallery') && isset($_FILES['image'])) {
			$gallery = $ModuleHandler->getObjectFromName('gallery');
			$gallery_manager = new GalleryManager();

			$result = $gallery->_createImage('image', $this->settings['thumbnail_size']);

			if (!$result['error']) {
				$image_data = array(
							'title'			=> $data['text'],
							'size'			=> $_FILES['image']['size'],
							'filename'		=> $result['filename'],
							'visible'		=> 0,
						);

				$gallery_manager->insertData($image_data);

				$data['image'] = $gallery_manager->getItemValue('id', array('filename' => $result['filename']));
				$gallery_addon = ";".window_ReloadContent('gallery_images');
			}
		}

		$manager = new LinksManager();

		if (!is_null($id)) {
			$manager->updateData($data, array('id' => $id));
			$window_name = 'links_change';
		} else {
			$manager->insertData($data);
			$window_name = 'links_add';
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_link_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window_name).";".
									window_ReloadContent('links_list').";".
									window_ReloadContent('links_overview').$gallery_addon
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Present user with confirmation dialog before removal of specified `link` object
	 * @param integer $level
	 */
	function deleteLink($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new LinksManager();

		$item = $manager->getSingleItem(array('text'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_link_delete"),
					'name'			=> $item->text,
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'links_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'links_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('links_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Remove specified `link` object and inform user about operation status
	 * @param integer $level
	 */
	function deleteLink_Commit($level) {
		global $ModuleHandler;

		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new LinksManager();
		$membership_manager = new LinkMembershipManager();
		$gallery_addon = '';

		// if we used image with this, we need to remove that too
		if ($ModuleHandler->moduleExists('gallery')) {
			$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

			if (is_object($item) && !empty($item->image)) {
				$gallery_manager = new GalleryManager();
				$gallery_manager->deleteData(array('id' => $item->image));
			}

			$gallery_addon = ";".window_ReloadContent('gallery_images');
		}

		$manager->deleteData(array('id' => $id));
		$membership_manager->deleteData(array('link' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_link_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('links_delete').";".window_ReloadContent('links_list').$gallery_addon
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Show link groups management window
	 * @param integer $level
	 */
	function showGroups($level) {
		$template = new TemplateHandler('groups_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('create_group'),
										'groups_add', 400,
										$this->getLanguageConstant('title_groups_create'),
										true, false,
										$this->name,
										'groups_add'
									),
					);

		$template->registerTagHandler('_group_list', &$this, 'tag_GroupList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Create new group form
	 * @param integer $level
	 */
	function addGroup($level) {
		$template = new TemplateHandler('groups_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'groups_save'),
					'cancel_action'	=> window_Close('groups_add')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Group rename form
	 * @param integer $level
	 */
	function changeGroup($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new LinkGroupsManager();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('groups_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $item->id,
					'name'			=> unfix_chars($item->name),
					'form_action'	=> backend_UrlMake($this->name, 'groups_save'),
					'cancel_action'	=> window_Close('groups_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Insert or save group data
	 * @param integer $level
	 */
	function saveGroup($level) {
		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;

		$data = array(
			'name' 	=> fix_chars($_REQUEST['name']),
		);

		$manager = new LinkGroupsManager();

		if (!is_null($id)) {
			$manager->updateData($data, array('id' => $id));
			$window_name = 'groups_change';
			$message = $this->getLanguageConstant('message_group_renamed');
		} else {
			$manager->insertData($data);
			$window_name = 'groups_add';
			$message = $this->getLanguageConstant('message_group_created');
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $message,
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window_name).";".window_ReloadContent('groups_list')
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
		$manager = new LinkGroupsManager();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_group_delete"),
					'name'			=> $item->name,
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'groups_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'groups_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('groups_delete')
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
		$manager = new LinkGroupsManager();
		$membership_manager = new LinkMembershipManager();

		$manager->deleteData(array('id' => $id));
		$membership_manager->deleteData(array('group' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_group_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('groups_delete').";".window_ReloadContent('groups_list')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Print a form containing all the links within a group
	 * @param integer $level
	 */
	function groupLinks($level) {
		$group_id = fix_id(fix_chars($_REQUEST['id']));

		$template = new TemplateHandler('groups_links.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'group'			=> $group_id,
					'form_action'	=> backend_UrlMake($this->name, 'groups_links_save'),
					'cancel_action'	=> window_Close('groups_links')
				);

		$template->registerTagHandler('_group_links', &$this, 'tag_GroupLinks');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Save link group memberships
	 * @param integer level
	 */
	function groupLinksSave($level) {
		$group = fix_id(fix_chars($_REQUEST['group']));
		$membership_manager = new LinkMembershipManager();

		// fetch all ids being set to specific group
		$link_ids = array();
		foreach ($_REQUEST as $key => $value) {
			if (substr($key, 0, 8) == 'link_id_' && $value == 1)
				$link_ids[] = fix_id(substr($key, 8));
		}

		// remove old memberships
		$membership_manager->deleteData(array('group' => $group));

		// save new memberships
		foreach ($link_ids as $id)
			$membership_manager->insertData(array(
											'link'	=> $id,
											'group'	=> $group
										));

		// display message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_group_links_updated"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('groups_links')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Show sponsored link overview
	 *
	 * @param integer $level
	 */
	function showOverview($level) {
		// display message
		$template = new TemplateHandler('overview_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('_link_list', &$this, 'tag_LinkList');

		$params = array(
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Record click cound and redirect to given page
	 *
	 * @param integer $level
	 */
	function redirectLink($level) {
		$link_id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new LinksManager();

		$link = $manager->getSingleItem($manager->getFieldNames(), array('id' => $link_id));

		if (is_object($link)) {
			$url = $link->url;
			$data = array();

			$data['total_clicks'] = $link->total_clicks + 1;
			if ($link->sponsored == 1)
				$data['sponsored_clicks'] = $link->sponsored_clicks + 1;

			$manager->updateData($data, array('id' => $link_id));

			url_SetRefresh($url, 0);
		}
	}

	/**
	 * Tag handler for links in group editor mode
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	function tag_GroupLinks($level, $params, $children) {
		if (!isset($params['group'])) return;

		$group = fix_id($params['group']);
		$link_manager = new LinksManager();
		$membership_manager = new LinkMembershipManager();

		$memberships = $membership_manager->getItems(
												array('link'),
												array('group' => $group)
											);

		$link_ids = array();
		if (count($memberships) > 0)
			foreach($memberships as $membership)
				$link_ids[] = $membership->link;

		$links = $link_manager->getItems($link_manager->getFieldNames(), array());

		$template = new TemplateHandler('groups_links_item.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		if (count($links) > 0)
			foreach($links as $link) {
				$params = array(
								'id'				=> $link->id,
								'in_group'			=> in_array($link->id, $link_ids) ? 1 : 0,
								'text'				=> $link->text,
								'description'		=> $link->description,
								'url'				=> $link->url,
								'external'			=> $link->external,
								'sponsored'			=> $link->sponsored,
								'sponsored_character' => ($link->sponsored == '1') ? CHAR_CHECKED : CHAR_UNCHECKED,
								'display_limit'		=> $link->display_limit,
								'sponsored_clicks'	=> $link->sponsored_clicks,
								'total_clicks'		=> $link->total_clicks,
							);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse($level);
			}
	}

	/**
	 * Tag handler for `link` object
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	function tag_Link($level, $params, $children) {
		global $ModuleHandler;

		$id = isset($params['id']) ? $params['id'] : fix_id(fix_chars($_REQUEST['id']));
		$manager = new LinksManager();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (isset($params['template'])) {
			if (isset($params['local']) && $params['local'] == 1)
				$template = new TemplateHandler($params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($params['template']);
		} else {
			$template = new TemplateHandler('links_item.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);

		// calculate display progress
		if (($item->sponsored_clicks >= $item->display_limit) || ($item->display_limit == 0)) {
			$percent = 100;
		} else {
			$percent = round(($item->sponsored_clicks / $item->display_limit) * 100, 0);
			if ($percent > 100) $percent = 100;
		}

		// get thumbnail image if exists
		$image = null;
		$thumbnail = null;

		if ($ModuleHandler->moduleExists('gallery')) {
			$gallery = $ModuleHandler->getObjectFromName('gallery');
			$gallery_manager = new GalleryManager();

			$image_item = $gallery_manager->getSingleItem(
												$gallery_manager->getFieldNames(),
												array('id' => $item->image)
											);

			if (is_object($image_item)) {
				$image = $gallery->_getImageURL($image_item);
				$thumbnail = $gallery->_getThumbnailURL($image_item);
			}
		}

		$params = array(
					'id'				=> $item->id,
					'text'				=> $item->text,
					'description'		=> $item->description,
					'url'				=> $item->url,
					'redirect_url'		=> url_Make('redirect', $this->name, array('id', $item->id)),
					'external'			=> $item->external,
					'external_character' => ($item->external == '1') ? CHAR_CHECKED : CHAR_UNCHECKED,
					'sponsored'			=> $item->sponsored,
					'sponsored_character' => ($item->sponsored == '1') ? CHAR_CHECKED : CHAR_UNCHECKED,
					'display_limit'		=> $item->display_limit,
					'display_percent'	=> $percent,
					'sponsored_clicks'	=> $item->sponsored_clicks,
					'total_clicks'		=> $item->total_clicks,
					'image'				=> $image,
					'thumbnail'			=> $thumbnail,
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Tag handler for printing link lists
	 *
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	function tag_LinkList($level, $tag_params, $children) {
		global $ModuleHandler;

		$manager = new LinksManager();
		$membership_manager = new LinkMembershipManager();
		$conditions = array();

		// save some CPU time by getting this early
		if ($ModuleHandler->moduleExists('gallery')) {
			$use_images = true;
			$gallery = $ModuleHandler->getObjectFromName('gallery');
			$gallery_manager = new GalleryManager();
		} else {
			$use_images = false;
		}


		if ((isset($tag_params['sponsored']) && $tag_params['sponsored'] == '1') ||
		(isset($tag_params['group']) && $tag_params['group'] == 'sponsored' ))
			$conditions['sponsored'] = 1;

		if (isset($tag_params['group']) && $tag_params['group'] != 'sponsored') {
			$group = $tag_params['group'];
			$items = $membership_manager->getItems(
												array('link'),
												array('group' => $group)
											);

			$item_list = array();
			foreach($items as $item)
				$item_list[] = $item->link;

			$conditions['id'] = $item_list;
		}

		$items = $manager->getItems(
								$manager->getFieldNames(),
								$conditions,
								array('id')
							);

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('links_item.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);
		$template->registerTagHandler('_link', &$this, 'tag_Link');
		$template->registerTagHandler('_link_group', &$this, 'tag_LinkGroupList');

		// give the ability to limit number of links to display
		if (isset($tag_params['limit']))
			$items = array_slice($items, 0, $tag_params['limit'], true);

		if (count($items) > 0)
		foreach ($items as $item) {
			// calculate display progress
			if (($item->sponsored_clicks >= $item->display_limit) || ($item->display_limit == 0)) {
				$percent = 100;
			} else {
				$percent = round(($item->sponsored_clicks / $item->display_limit) * 100, 0);
				if ($percent > 100) $percent = 100;
			}

			// if gallery is loaded
			$image = '';
			$thumbnail = '';
			if ($use_images) {
				$image_item = $gallery_manager->getSingleItem($gallery_manager->getFieldNames(), array('id' => $item->image));

				if (is_object($image_item)) {
					$image = $gallery->_getImageURL($image_item);
					$thumbnail = $gallery->_getThumbnailURL($image_item);
				}
			}

			$params = array(
						'id'				=> $item->id,
						'text'				=> $item->text,
						'description'		=> $item->description,
						'url'				=> $item->url,
						'redirect_url'		=> url_Make('redirect', $this->name, array('id', $item->id)),
						'external'			=> $item->external,
						'external_character' => ($item->external == '1') ? CHAR_CHECKED : CHAR_UNCHECKED,
						'sponsored'			=> $item->sponsored,
						'sponsored_character' => ($item->sponsored == '1') ? CHAR_CHECKED : CHAR_UNCHECKED,
						'display_limit'		=> $item->display_limit,
						'display_percent'	=> $percent,
						'sponsored_clicks'	=> $item->sponsored_clicks,
						'total_clicks'		=> $item->total_clicks,
						'image'				=> $image,
						'thumbnail'			=> $thumbnail,
						'item_change'		=> url_MakeHyperlink(
												$this->getLanguageConstant('change'),
												window_Open(
													'links_change', 	// window id
													400,				// width
													$this->getLanguageConstant('title_links_change'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'links_change'),
														array('id', $item->id)
													)
												)
											),
						'item_delete'		=> url_MakeHyperlink(
												$this->getLanguageConstant('delete'),
												window_Open(
													'links_delete', 	// window id
													400,				// width
													$this->getLanguageConstant('title_links_delete'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'links_delete'),
														array('id', $item->id)
													)
												)
											),
						'item_open'			=> url_MakeHyperlink(
												$this->getLanguageConstant('open'),
												$item->url,
												'', '',
												'_blank'
											),
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}

	/**
	 * Tag handler for printing link group
	 *
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	function tag_Group($level, $tag_params, $children) {
		global $ModuleHandler;

		if (!isset($tag_params['id'])) return;

		$id = $tag_params['id'];

		// save some CPU time by getting this early
		if ($ModuleHandler->moduleExists('gallery')) {
			$use_images = true;
			$gallery = $ModuleHandler->getObjectFromName('gallery');
			$gallery_manager = new GalleryManager();
		} else {
			$use_images = false;
		}

		$manager = new LinkGroupsManager();
		$link_manager = new LinksManager();
		$membership_manager = new LinkMembershipManager();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('group.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);
		$template->registerTagHandler('_link', &$this, 'tag_Link');
		$template->registerTagHandler('_link_list', &$this, 'tag_LinkList');

		if (is_object($item)) {
			$thumbnail = '';

			if ($use_images) {
				$first_link_id = $membership_manager->getItemValue('link', array('group' => $item->id));

				// we have some links assigned to the group, get thumbnail
				if (!empty($first_link_id)) {
					$image_id = $link_manager->getItemValue('image', array('id' => $first_link_id));

					if (!empty($image_id)) {
						$image = $gallery_manager->getSingleItem($gallery_manager->getFieldNames(), array('id' => $image_id));
						$thumbnail = $gallery->_getThumbnailURL($image);
					}
				}
			}

			$params = array(
						'id'		=> $item->id,
						'name'		=> $item->name,
						'thumbnail'	=> $thumbnail,
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}

	/**
	 * Tag handler for printing link groups
	 *
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	function tag_GroupList($level, $tag_params, $children) {
		global $ModuleHandler;

		$manager = new LinkGroupsManager();
		$link_manager = new LinksManager();
		$membership_manager = new LinkMembershipManager();

		// save some CPU time by getting this early
		if ($ModuleHandler->moduleExists('gallery')) {
			$use_images = true;
			$gallery = $ModuleHandler->getObjectFromName('gallery');
			$gallery_manager = new GalleryManager();
		} else {
			$use_images = false;
		}

		$conditions = array();

		if (isset($tag_params['sponsored']) && $tag_params['sponsored'] == '1')
			$conditions['sponsored'] = 1;

		$items = $manager->getItems(
								$manager->getFieldNames(),
								$conditions,
								array('id')
							);

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('groups_item.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);
		$template->registerTagHandler('_link', &$this, 'tag_Link');
		$template->registerTagHandler('_link_list', &$this, 'tag_LinkList');

		if (count($items) > 0)
			foreach ($items as $item) {

				$thumbnail = '';

				if ($use_images) {
					$first_link_id = $membership_manager->getItemValue('link', array('group' => $item->id));

					// we have some links assigned to the group, get thumbnail
					if (!empty($first_link_id)) {
						$image_id = $link_manager->getItemValue('image', array('id' => $first_link_id));

						if (!empty($image_id)) {
							$image = $gallery_manager->getSingleItem($gallery_manager->getFieldNames(), array('id' => $image_id));
							$thumbnail = $gallery->_getThumbnailURL($image);
						}
					}
				}

				$params = array(
							'id'		=> $item->id,
							'name'		=> $item->name,
							'thumbnail'	=> $thumbnail,
							'item_change'		=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														'groups_change', 	// window id
														400,				// width
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
							'item_delete'		=> url_MakeHyperlink(
													$this->getLanguageConstant('delete'),
													window_Open(
														'groups_delete', 	// window id
														400,				// width
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
							'item_links'		=> url_MakeHyperlink(
													$this->getLanguageConstant('links'),
													window_Open(
														'groups_links', 	// window id
														400,				// width
														$this->getLanguageConstant('title_groups_links'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'groups_links'),
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
}

class LinksManager extends ItemManager {

	function __construct() {
		parent::__construct('links');

		$this->addProperty('id', 'int');
		$this->addProperty('text', 'varchar');
		$this->addProperty('description', 'text');
		$this->addProperty('url', 'varchar');
		$this->addProperty('external', 'boolean');
		$this->addProperty('sponsored', 'boolean');
		$this->addProperty('display_limit', 'integer');
		$this->addProperty('sponsored_clicks', 'integer');
		$this->addProperty('total_clicks', 'integer');
		$this->addProperty('image', 'integer');
	}
}

class LinkGroupsManager extends ItemManager {

	function __construct() {
		parent::__construct('link_groups');

		$this->addProperty('id', 'int');
		$this->addProperty('name', 'varchar');
	}
}

class LinkMembershipManager extends ItemManager {

	function __construct() {
		parent::__construct('link_membership');

		$this->addProperty('id', 'int');
		$this->addProperty('link', 'int');
		$this->addProperty('group', 'int');
	}
}
