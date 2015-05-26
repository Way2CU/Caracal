<?php

/**
 * Links Module
 *
 * This module provides a number of useful ways of printing and organising
 * links on your web site.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;
use Core\Markdown;


class links extends Module {
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

			$links_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_links'),
					url_GetFromFilePath($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);

			$links_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_links_manage'),
								url_GetFromFilePath($this->path.'images/manage.svg'),
								window_Open( // on click open window
											'links_list',
											720,
											$this->getLanguageConstant('title_links_manage'),
											true, true,
											backend_UrlMake($this->name, 'links_list')
										),
								$level=5
							));

			$links_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_links_groups'),
								url_getFromFilePath($this->path.'images/groups.svg'),
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
								url_GetFromFilePath($this->path.'images/overview.svg'),
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
		switch ($params['action']) {
			case 'show':
				$this->tag_Link($params, $children);
				break;

			case 'show_link_list':
				$this->tag_LinkList($params, $children);
				break;

			case 'show_group':
				$this->tag_Group($params, $children);
				break;

			case 'show_group_list':
				$this->tag_GroupList($params, $children);
				break;

			case 'json_link':
				$this->json_Link();
				break;

			case 'json_link_list':
				$this->json_LinkList();
				break;

			case 'json_group_list':
				$this->json_GroupList();
				break;

			case 'redirect':
				$this->redirectLink();
				break;

			default:
				break;
		}

		// global control actions
		if (isset($params['backend_action']))
		switch ($params['backend_action']) {
			case 'links_list':
				$this->showList();
				break;

			case 'links_add':
				$this->addLink();
				break;

			case 'links_change':
				$this->changeLink();
				break;

			case 'links_save':
				$this->saveLink();
				break;

			case 'links_delete':
				$this->deleteLink();
				break;

			case 'links_delete_commit':
				$this->deleteLink_Commit();
				break;

			// ----

			case 'groups_list':
				$this->showGroups();
				break;

			case 'groups_add':
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

			case 'groups_links':
				$this->groupLinks();
				break;

			case 'groups_links_save':
				$this->groupLinksSave();
				break;

			// ----

			case 'overview':
				$this->showOverview();
				break;

			default:
				break;
		}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db;

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
		$db->query($sql);

		$sql = "
			CREATE TABLE IF NOT EXISTS `link_groups` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`name` varchar(50) COLLATE utf8_bin NOT NULL,
				`text_id` varchar(32) COLLATE utf8_bin NOT NULL,
				PRIMARY KEY (`id`),
				INDEX (`text_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		$sql = "
			CREATE TABLE IF NOT EXISTS `link_membership` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`link` int(11) NOT NULL,
				`group` int(11) NOT NULL,
				PRIMARY KEY (`id`),
				KEY `group` (`group`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		if (!array_key_exists('thumbnail_size', $this->settings))
			$this->saveSetting('thumbnail_size', '100');
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array('links', 'link_groups', 'link_membership');
		$db->drop_tables($tables);
	}

	/**
	 * Show links window
	 */
	private function showList() {
		$template = new TemplateHandler('links_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('add'),
										'links_add', 600,
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

		$template->registerTagHandler('_link_list', $this, 'tag_LinkList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show content of a form used for creation of new `link` object
	 */
	private function addLink() {
		$template = new TemplateHandler('links_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'with_images'	=> class_exists('gallery'),
					'form_action'	=> backend_UrlMake($this->name, 'links_save'),
					'cancel_action'	=> window_Close('links_add')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show content of a form in editing state for sepected `link` object
	 */
	private function changeLink() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = LinksManager::getInstance();

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
		$template->parse();
	}

	/**
	 * Save changes existing (or new) to `link` object and display result
	 */
	private function saveLink() {
		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;

		$data = array(
			'text' 			=> fix_chars($_REQUEST['text']),
			'description' 	=> escape_chars($_REQUEST['description']),
			'url' 			=> fix_chars($_REQUEST['url']),
			'external' 		=> isset($_REQUEST['external']) && ($_REQUEST['external'] == 'on' || $_REQUEST['external'] == '1') ? 1 : 0,
			'sponsored' 	=> isset($_REQUEST['sponsored']) && ($_REQUEST['sponsored'] == 'on' || $_REQUEST['sponsored'] == '1') ? 1 : 0,
			'display_limit'	=> fix_id(fix_chars($_REQUEST['display_limit'])),
		);

		$gallery_addon = '';

		// if images are in use and specified
		if (class_exists('gallery') && isset($_FILES['image'])) {
			$gallery = gallery::getInstance();
			$gallery_manager = GalleryManager::getInstance();

			$result = $gallery->createImage('image');

			if (!$result['error']) {
				$image_data = array(
							'title'			=> $data['text'],
							'visible'		=> 0,
							'protected'		=> 1
						);

				$gallery_manager->updateData($image_data, array('id' => $result['id']));

				$data['image'] = $result['id'];
				$gallery_addon = ";".window_ReloadContent('gallery_images');
			}
		}

		$manager = LinksManager::getInstance();

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
		$template->parse();
	}

	/**
	 * Present user with confirmation dialog before removal of specified `link` object
	 */
	private function deleteLink() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = LinksManager::getInstance();

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
		$template->parse();
	}

	/**
	 * Remove specified `link` object and inform user about operation status
	 */
	private function deleteLink_Commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = LinksManager::getInstance();
		$membership_manager = LinkMembershipManager::getInstance();
		$gallery_addon = '';

		// if we used image with this, we need to remove that too
		if (class_exists('gallery')) {
			$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

			if (is_object($item) && !empty($item->image)) {
				$gallery_manager = GalleryManager::getInstance();
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
		$template->parse();
	}

	/**
	 * Show link groups management window
	 */
	private function showGroups() {
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

		$template->registerTagHandler('_group_list', $this, 'tag_GroupList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Create new group form
	 */
	private function addGroup() {
		$template = new TemplateHandler('groups_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'groups_save'),
					'cancel_action'	=> window_Close('groups_add')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Group rename form
	 */
	private function changeGroup() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = LinkGroupsManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('groups_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $item->id,
					'name'			=> $item->name,
					'text_id'		=> $item->text_id,
					'form_action'	=> backend_UrlMake($this->name, 'groups_save'),
					'cancel_action'	=> window_Close('groups_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Insert or save group data
	 */
	private function saveGroup() {
		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;

		$data = array(
			'name' 		=> fix_chars($_REQUEST['name']),
			'text_id'	=> fix_chars($_REQUEST['text_id'])
		);

		$manager = LinkGroupsManager::getInstance();

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
		$template->parse();
	}

	/**
	 * Delete group confirmation dialog
	 */
	private function deleteGroup() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = LinkGroupsManager::getInstance();

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
		$template->parse();
	}

	/**
	 * Delete group from the system
	 */
	private function deleteGroup_Commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = LinkGroupsManager::getInstance();
		$membership_manager = LinkMembershipManager::getInstance();

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
		$template->parse();
	}

	/**
	 * Print a form containing all the links within a group
	 */
	private function groupLinks() {
		$group_id = fix_id(fix_chars($_REQUEST['id']));

		$template = new TemplateHandler('groups_links.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'group'			=> $group_id,
					'form_action'	=> backend_UrlMake($this->name, 'groups_links_save'),
					'cancel_action'	=> window_Close('groups_links')
				);

		$template->registerTagHandler('_group_links', $this, 'tag_GroupLinks');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save link group memberships
	 */
	private function groupLinksSave() {
		$group = fix_id(fix_chars($_REQUEST['group']));
		$membership_manager = LinkMembershipManager::getInstance();

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
		$template->parse();
	}

	/**
	 * Show sponsored link overview
	 */
	private function showOverview() {
		// display message
		$template = new TemplateHandler('overview_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('_link_list', $this, 'tag_LinkList');

		$params = array(
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Record click cound and redirect to given page
	 */
	private function redirectLink() {
		$link_id = fix_id(fix_chars($_REQUEST['id']));
		$manager = LinksManager::getInstance();

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
	 * @param array $params
	 * @param array $children
	 */
	public function tag_GroupLinks($params, $children) {
		if (!isset($params['group'])) return;

		$group = fix_id($params['group']);
		$link_manager = LinksManager::getInstance();
		$membership_manager = LinkMembershipManager::getInstance();

		$memberships = $membership_manager->getItems(
												array('link'),
												array('group' => $group)
											);

		$link_ids = array();
		if (count($memberships) > 0)
			foreach($memberships as $membership)
				$link_ids[] = $membership->link;

		$links = $link_manager->getItems(array('id', 'text', 'sponsored'), array());

		$template = new TemplateHandler('groups_links_item.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		if (count($links) > 0)
			foreach($links as $link) {
				$params = array(
								'id'				=> $link->id,
								'in_group'			=> in_array($link->id, $link_ids) ? 1 : 0,
								'text'				=> $link->text,
								'sponsored_character' => ($link->sponsored == '1') ? CHAR_CHECKED : CHAR_UNCHECKED,
							);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Tag handler for `link` object
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function tag_Link($params, $children) {
		$id = isset($params['id']) ? $params['id'] : fix_id(fix_chars($_REQUEST['id']));
		$manager = LinksManager::getInstance();

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

		if (class_exists('gallery')) {
			$gallery = gallery::getInstance();
			$gallery_manager = GalleryManager::getInstance();

			if (is_numeric($item->image)) {
				$image_item = $gallery_manager->getSingleItem(
													$gallery_manager->getFieldNames(),
													array('id' => $item->image)
												);

				if (is_object($image_item)) {
					$image = $gallery->getImageURL($image_item);
					$thumbnail = $gallery->getThumbnailURL($image_item);
				}
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
		$template->parse();
	}

	/**
	 * Tag handler for printing link lists
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_LinkList($tag_params, $children) {
		$manager = LinksManager::getInstance();
		$group_manager = LinkGroupsManager::getInstance();
		$membership_manager = LinkMembershipManager::getInstance();
		$conditions = array();

		// save some CPU time by getting this early
		if (class_exists('gallery')) {
			$use_images = true;
			$gallery = gallery::getInstance();
			$gallery_manager = GalleryManager::getInstance();
		} else {
			$use_images = false;
		}

		if (isset($tag_params['sponsored']) && $tag_params['sponsored'] == '1')
			$conditions['sponsored'] = 1;

		if (isset($tag_params['group'])) {
			if (is_numeric($tag_params['group'])) {
				// we already have id of a group
				$group = fix_id($tag_params['group']);

			} else {
				// specified group is text id
				$text_id = fix_chars($tag_params['group']);
				$raw_group = $group_manager->getSingleItem(array('id'), array('text_id' => $text_id));

				if (is_object($raw_group))
					$group = $raw_group->id; else
					return;
			}

			$items = $membership_manager->getItems(
												array('link'),
												array('group' => $group)
											);

			$item_list = array();

			if (count($items) > 0) {
				foreach($items as $item)
					$item_list[] = $item->link;
			} else {
				return;  // no items were found in group, nothing to show
			}

			$conditions['id'] = $item_list;
		}

		$items = $manager->getItems(
								$manager->getFieldNames(),
								$conditions,
								array('id')
							);

		$template = $this->loadTemplate($tag_params, 'links_item.xml');
		$template->registerTagHandler('_link', $this, 'tag_Link');
		$template->registerTagHandler('_link_group', $this, 'tag_LinkGroupList');

		// give the ability to limit number of links to display
		if (isset($tag_params['limit']) && !is_null($items))
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
			if ($use_images && !empty($item->image)) {
				$image_item = $gallery_manager->getSingleItem($gallery_manager->getFieldNames(), array('id' => $item->image));

				if (is_object($image_item)) {
					$image = $gallery->getImageURL($image_item);
					$thumbnail = $gallery->getThumbnailURL($image_item);
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
													600,				// width
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
			$template->parse();
		}
	}

	/**
	 * Tag handler for printing link group
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Group($tag_params, $children) {
		if (!isset($tag_params['id'])) return;

		$id = $tag_params['id'];

		// save some CPU time by getting this early
		if (class_exists('gallery')) {
			$use_images = true;
			$gallery = gallery::getInstance();
			$gallery_manager = GalleryManager::getInstance();
		} else {
			$use_images = false;
		}

		$manager = LinkGroupsManager::getInstance();
		$link_manager = LinksManager::getInstance();
		$membership_manager = LinkMembershipManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('group.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);
		$template->registerTagHandler('_link', $this, 'tag_Link');
		$template->registerTagHandler('_link_list', $this, 'tag_LinkList');

		if (is_object($item)) {
			$thumbnail = '';

			if ($use_images) {
				$first_link_id = $membership_manager->getItemValue('link', array('group' => $item->id));

				// we have some links assigned to the group, get thumbnail
				if (!empty($first_link_id)) {
					$image_id = $link_manager->getItemValue('image', array('id' => $first_link_id));

					if (!empty($image_id)) {
						$image = $gallery_manager->getSingleItem($gallery_manager->getFieldNames(), array('id' => $image_id));
						$thumbnail = $gallery->getThumbnailURL($image);
					}
				}
			}

			$params = array(
						'id'		=> $item->id,
						'name'		=> $item->name,
						'text_id'	=> $item->text_id,
						'thumbnail'	=> $thumbnail,
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Tag handler for printing link groups
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_GroupList($tag_params, $children) {
		$manager = LinkGroupsManager::getInstance();
		$link_manager = LinksManager::getInstance();
		$membership_manager = LinkMembershipManager::getInstance();

		// save some CPU time by getting this early
		if (class_exists('gallery')) {
			$use_images = true;
			$gallery = gallery::getInstance();
			$gallery_manager = GalleryManager::getInstance();
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
		$template->registerTagHandler('_link', $this, 'tag_Link');
		$template->registerTagHandler('_link_list', $this, 'tag_LinkList');

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
							$thumbnail = $gallery->getThumbnailURL($image);
						}
					}
				}

				$params = array(
							'id'		=> $item->id,
							'name'		=> $item->name,
							'text_id'	=> $item->text_id,
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
				$template->parse();
			}
	}

	/**
	 * Get single link through AJAX request.
	 */
	private function json_Link() {
		$conditions = array();
		$manager = LinksManager::getInstance();
		$result = array(
					'error'			=> true,
					'item'			=> array()
				);

		// get conditions
		if (isset($_REQUEST['id']))
			$conditions['id'] = fix_id($_REQUEST['id']);

		// get link from the database
		$item = $manager->getSingleItem($manager->getFieldNames(), $conditions);

		// make sure link exists
		if (is_null($item)) {
			print json_encode($result);
			return;
		}

		// prepare response
		if (is_object($item)) {
			$image_url = null;
			if (class_exists('gallery'))
				$image_url = gallery::getImageById($item->image);

			$result['error'] = false;
			$result['item'] = array(
								'id'				=> $item->id,
								'text'				=> $item->text,
								'description'		=> Markdown::parse($item->description),
								'url'				=> $item->url,
								'redirect_url'		=> url_Make('redirect', $this->name, array('id', $item->id)),
								'external'			=> $item->external,
								'sponsored'			=> $item->sponsored,
								'display_limit'		=> $item->display_limit,
								'sponsored_clicks'	=> $item->sponsored_clicks,
								'total_clicks'		=> $item->total_clicks,
								'image'				=> $image_url
							);
		}

		print json_encode($result);
	}

	/**
	 * Create JSON object containing links with specified characteristics
	 */
	private function json_LinkList() {
		$groups = array();
		$conditions = array();

		$limit = isset($tag_params['limit']) ? fix_id($tag_params['limit']) : null;
		$order_by = isset($tag_params['order_by']) ? explode(',', fix_chars($tag_params['order_by'])) : array('id');
		$order_asc = isset($tag_params['order_asc']) && $tag_params['order_asc'] == 'yes' ? true : false;
		$grouped = isset($_REQUEST['grouped']) && $_REQUEST['grouped'] == 'yes' ? true : false;

		$manager = LinksManager::getInstance();
		$group_manager = LinkGroupsManager::getInstance();
		$membership_manager = LinkMembershipManager::getInstance();

		if (isset($_REQUEST['group'])) {
			$group_list = explode(',', fix_chars($_REQUEST['group']));

			$list = $group_manager->getItems(array('id'), array('name' => $group_list));

			if (count($list) > 0)
				foreach ($list as $list_item)
					$groups[] = $list_item->id;
		}

		if (isset($_REQUEST['group_id']))
			$groups = array_merge($groups, fix_id(explode(',', $_REQUEST['group_id'])));

		if (isset($_REQUEST['sponsored'])) {
			$sponsored = $_REQUEST['sponsored'] == 'yes' ? 1 : 0;
			$conditions['sponsored'] = $sponsored;
		}

		// fetch ids for specified groups
		if (!empty($groups)) {
			$list = $membership_manager->getItems(array('link'), array('group' => $groups));

			$id_list = array();
			if (count($list) > 0) {
				foreach ($list as $list_item)
					$id_list[] = $list_item->link;

			} else {
				// in case no members of specified group were found, ensure no items are retrieved
				$id_list = '-1';
			}

			$conditions['id'] = $id_list;
		}

		// save some CPU time by getting this early
		if (class_exists('gallery')) {
			$use_images = true;
			$gallery = gallery::getInstance();
			$gallery_manager = GalleryManager::getInstance();
		} else {
			$use_images = false;
		}

		$items = $manager->getItems(
							$manager->getFieldNames(),
							$conditions,
							$order_by,
							$order_asc,
							$limit
						);

		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'items'			=> array()
				);

		if (count($items) > 0) {
			foreach ($items as $item)
				$result['items'][] = array(
									'id'				=> $item->id,
									'text'				=> $item->text,
									'url'				=> $item->url,
									'redirect_url'		=> url_Make('redirect', $this->name, array('id', $item->id)),
									'external'			=> $item->external,
									'sponsored'			=> $item->sponsored,
									'display_limit'		=> $item->display_limit,
									'sponsored_clicks'	=> $item->sponsored_clicks,
									'total_clicks'		=> $item->total_clicks,
									'image'				=> null
								);
		} else {
		}

		print json_encode($result);
	}

	/**
	 * Create JSON object containing group items
	 */
	private function json_GroupList() {
		$groups = array();
		$conditions = array();

		$limit = isset($tag_params['limit']) ? fix_id($tag_params['limit']) : null;
		$order_by = isset($tag_params['order_by']) ? explode(',', fix_chars($tag_params['order_by'])) : array('id');
		$order_asc = isset($tag_params['order_asc']) && $tag_params['order_asc'] == 'yes' ? true : false;

		$manager = LinkGroupsManager::getInstance();

		$items = $manager->getItems($manager->getFieldNames(), $conditions, $order_by, $order_asc, $limit);

		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'items'			=> array()
				);

		if (count($items) > 0) {
			foreach ($items as $item)
				$result['items'][] = array(
									'id'		=> $item->id,
									'name'		=> $item->name
								);
		} else {
		}

		print json_encode($result);
	}
}


class LinksManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
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

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}


class LinkGroupsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('link_groups');

		$this->addProperty('id', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('text_id', 'varchar');
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


class LinkMembershipManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('link_membership');

		$this->addProperty('id', 'int');
		$this->addProperty('link', 'int');
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
