<?php

/**
 * YouTube Implmenetation Module
 *
 * @author MeanEYE
 * @todo Add playlist support
 */

require_once('units/video_manager.php');
require_once('units/group_manager.php');
require_once('units/membership_manager.php');

class youtube extends Module {
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
			$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/default_style.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/youtube_controls.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			$youtube_menu = new backend_MenuItem(
								$this->getLanguageConstant('menu_youtube'),
								url_GetFromFilePath($this->path.'images/icon.png'),
								'javascript:void(0);',
								$level=5
							);

			$youtube_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_video_list'),
								url_GetFromFilePath($this->path.'images/list.png'),
								window_Open( // on click open window
											$this->name.'_video_list',
											650,
											$this->getLanguageConstant('title_video_list'),
											true, true,
											backend_UrlMake($this->name, 'video_list')
										),
								$level=5
							));

			$youtube_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_video_groups'),
								url_GetFromFilePath($this->path.'images/groups.png'),
								window_Open( // on click open window
											$this->name.'_group_list',
											570,
											$this->getLanguageConstant('title_video_groups'),
											true, true,
											backend_UrlMake($this->name, 'group_list')
										),
								$level=5
							));

			$backend->addMenu($this->name, $youtube_menu);
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
					$this->tag_Video($params, $children);
					break;

				case 'show_list':
					$this->tag_VideoList($params, $children);
					break;

				case 'show_thumbnail':
					$this->tag_Thumbnail($params, $children);
					break;

				case 'show_group':
					$this->tag_Group($params, $children);
					break;

				case 'show_group_list':
					$this->tag_GroupList($params, $children);
					break;

				case 'json_video':
					$this->json_Video();
					break;

				case 'json_video_list':
					$this->json_VideoList();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				// videos
				case 'video_list':
					$this->showList();
					break;

				case 'video_add':
					$this->addVideo();
					break;

				case 'video_change':
					$this->changeVideo();
					break;

				case 'video_save':
					$this->saveVideo();
					break;

				case 'video_delete':
					$this->deleteVideo();
					break;

				case 'video_delete_commit':
					$this->deleteVideo_Commit();
					break;

				case 'video_preview':
					$this->previewVideo();
					break;

				// video groups
				case 'group_list':
					$this->showGroups();
					break;

				case 'group_create':
					$this->createGroup();
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

				case 'group_videos':
					$this->groupVideos();
					break;

				case 'group_videos_save':
					$this->groupVideos_Save();
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

		$list = MainLanguageHandler::getInstance()->getLanguages(false);

		// create videos table
		$sql = "
			CREATE TABLE IF NOT EXISTS `youtube_video` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`text_id` VARCHAR (32) NULL ,
				`video_id` varchar(11) COLLATE utf8_bin NOT NULL,
			";

		foreach($list as $language)
			$sql .= "`title_{$language}` varchar(255) COLLATE utf8_bin NOT NULL,";

		$sql .= "PRIMARY KEY (`id`),
				INDEX (`text_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create groups table
		$sql = "
			CREATE TABLE IF NOT EXISTS `youtube_groups` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`text_id` VARCHAR (32) NULL ,
			";

		foreach($list as $language)
			$sql .= "`name_{$language}` varchar(255) COLLATE utf8_bin NOT NULL,";

		foreach($list as $language)
			$sql .= "`description_{$language}` TEXT NOT NULL ,";

		$sql .= "
				`visible` BOOLEAN NOT NULL DEFAULT '1',
				PRIMARY KEY (`id`),
				INDEX (`text_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create group membership table
		$sql = "
			CREATE TABLE IF NOT EXISTS `youtube_group_membership` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`group` int(11) NOT NULL,
				`video` int(11) NOT NULL,
				PRIMARY KEY (`id`),
				INDEX (`group`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array('youtube_video', 'youtube_groups', 'youtube_group_membership');
		$db->drop_tables($tables);
	}

	/**
	 * Show backend video list with options
	 */
	private function showList() {
		$template = new TemplateHandler('video_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'	=> window_OpenHyperlink(
										$this->getLanguageConstant('add'),
										$this->name.'_video_add', 400,
										$this->getLanguageConstant('title_video_add'),
										true, false,
										$this->name,
										'video_add'
									)
					);

		$template->registerTagHandler('_video_list', $this, 'tag_VideoList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Add video form
	 */
	private function addVideo() {
		$template = new TemplateHandler('video_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'video_save'),
					'cancel_action'	=> window_Close($this->name.'_video_add')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Change video data form
	 */
	private function changeVideo() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = YouTube_VideoManager::getInstance();

		$video = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('video_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $video->id,
					'text_id'		=> unfix_chars($video->text_id),
					'video_id'		=> unfix_chars($video->video_id),
					'title'			=> unfix_chars($video->title),
					'form_action'	=> backend_UrlMake($this->name, 'video_save'),
					'cancel_action'	=> window_Close($this->name.'_video_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save modified or new video data
	 */
	private function saveVideo() {
		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;
		$text_id = fix_chars($_REQUEST['text_id']);
		$video_id = fix_chars($_REQUEST['video_id']);
		$title = fix_chars($this->getMultilanguageField('title'));

		$manager = YouTube_VideoManager::getInstance();

		$data = array(
					'text_id'	=> $text_id,
					'video_id'	=> $video_id,
					'title' 	=> $title
				);

		if (is_null($id))
			$manager->insertData($data); else
			$manager->updateData($data, array('id' => $id));


		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$window_name = $this->name.(is_null($id) ? '_video_add' : '_video_change');
		$params = array(
					'message'	=> $this->getLanguageConstant("message_video_saved"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close($window_name).";".window_ReloadContent($this->name.'_video_list')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Display confirmation dialog before removing specified video
	 */
	private function deleteVideo() {
		global $language;

		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = YouTube_VideoManager::getInstance();

		$video = $manager->getSingleItem(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_video_delete"),
					'name'			=> $video->title[$language],
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											$this->name.'_video_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'video_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close($this->name.'_video_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Actually delete specified video from database
	 */
	private function deleteVideo_Commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = YouTube_VideoManager::getInstance();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$window_name = $this->name.'_video_delete';
		$params = array(
					'message'	=> $this->getLanguageConstant("message_video_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close($window_name).";".window_ReloadContent($this->name.'_video_list')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Play video in backend window
	 */
	private function previewVideo() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = YouTube_VideoManager::getInstance();

		$video_id = $manager->getItemValue('id', array('id' => $id));

		if ($video_id) {
			$template = new TemplateHandler('video_preview.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
						'video_id'	=> $video_id,
						'button'	=> $this->getLanguageConstant("close"),
						'action'	=> window_Close($this->name.'_video_preview')
					);

			$template->registerTagHandler('_video', $this, 'tag_Video');
			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();

		} else {
			// show error message
			$template = new TemplateHandler('message.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
						'message'	=> $this->getLanguageConstant("message_video_error"),
						'button'	=> $this->getLanguageConstant("close"),
						'action'	=> window_Close($this->name.'_video_preview')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Show window displaying groups
	 */
	private function showGroups() {
		$template = new TemplateHandler('group_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'	=> window_OpenHyperlink(
										$this->getLanguageConstant('create_group'),
										$this->name.'_group_create', 400,
										$this->getLanguageConstant('title_group_create'),
										true, false,
										$this->name,
										'group_create'
									)
					);

		$template->registerTagHandler('_group_list', $this, 'tag_GroupList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show group create form
	 */
	private function createGroup() {
		$template = new TemplateHandler('group_create.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'group_save'),
					'cancel_action'	=> window_Close($this->name.'_group_create')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show group changing form
	 */
	private function changeGroup() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = YouTube_GroupManager::getInstance();

		$group = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('group_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $group->id,
					'text_id'		=> unfix_chars($group->text_id),
					'name'			=> unfix_chars($group->name),
					'description'	=> $group->description,
					'visible'		=> $group->visible,
					'form_action'	=> backend_UrlMake($this->name, 'group_save'),
					'cancel_action'	=> window_Close($this->name.'_group_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save new or changed group data
	 */
	private function saveGroup() {
		$manager = YouTube_GroupManager::getInstance();

		// get parameters and secure them
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$text_id = fix_chars($_REQUEST['text_id']);
		$name = fix_chars($this->getMultilanguageField('name'));
		$description = escape_chars($this->getMultilanguageField('description'));
		$visible = isset($_REQUEST['visible']) && ($_REQUEST['visible'] == 'on' || $_REQUEST['visible'] == '1') ? 1 : 0;

		if (is_null($id)) {
			// store new record
			$manager->insertData(array(
							'text_id'		=> $text_id,
							'name'			=> $name,
							'description'	=> $description,
							'visible'		=> $visible
						));

			$window = $this->name.'_group_create';

		} else {
			// change existing record
			$manager->updateData(
							array(
								'text_id'		=> $text_id,
								'name'			=> $name,
								'description'	=> $description,
								'visible'		=> $visible
							),
							array(
								'id' => $id
							)
						);

			$window = $this->name.'_group_change';
		}

		// display message to the user
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_group_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent($this->name.'_group_list'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation dialog for group removal
	 */
	private function deleteGroup() {
		global $language;

		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = YouTube_GroupManager::getInstance();

		$group = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_group_delete"),
					'name'			=> $group->name[$language],
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											$this->name.'_group_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'group_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close($this->name.'_group_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Actually remove group and tell user about the result
	 */
	private function deleteGroup_Commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = YouTube_GroupManager::getInstance();
		$membership_manager = YouTube_MembershipManager::getInstance();

		$manager->deleteData(array('id' => $id));
		$membership_manager->deleteData(array('group' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$window_name = $this->name.'_group_delete';
		$params = array(
					'message'	=> $this->getLanguageConstant("message_group_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close($window_name).";".window_ReloadContent($this->name.'_group_list')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show video selection form
	 */
	private function groupVideos() {
		$id = fix_id($_REQUEST['id']);

		$template = new TemplateHandler('group_videos.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'group'			=> $id,
					'form_action'	=> backend_UrlMake($this->name, 'group_videos_save'),
					'cancel_action'	=> window_Close($this->name.'_group_videos')
				);

		$template->registerTagHandler('_group_videos', $this, 'tag_GroupVideos');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save selected videos for specified group
	 */
	private function groupVideos_Save() {
		$group = fix_id($_REQUEST['group']);
		$membership_manager = YouTube_MembershipManager::getInstance();

		// fetch all ids being set to specific group
		$video_ids = array();
		foreach ($_REQUEST as $key => $value) {
			if (substr($key, 0, 9) == 'video_id_' && $value == 1)
				$video_ids[] = fix_id(substr($key, 8));
		}

		// remove old memberships
		$membership_manager->deleteData(array('group' => $group));

		// save new memberships
		foreach ($video_ids as $id)
			$membership_manager->insertData(array(
											'group'	=> $group,
											'video'	=> $id
										));

		// display message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_group_videos_updated"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close($this->name.'_group_videos')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Handler for _video tag which embeds player in page.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Video($tag_params, $children) {
		$video = null;
		$manager = YouTube_VideoManager::getInstance();

		if (isset($tag_params['id'])) {
			// video is was specified
			$video = $manager->getSingleItem(
									$manager->getFieldNames(),
									array(
										'id' => $tag_params['id']
									));

		} else if (isset($tag_params['text_id'])) {
			// text id was specified
			$video = $manager->getSingleItem(
									$manager->getFieldNames(),
									array(
										'text_id' => $tag_params['text_id']
									));

		} else if (isset($tag_params['random'])) {
			// get random video
			$video = $manager->getSingleItem(
									$manager->getFieldNames(),
									array(),
									array('RAND()')
								);
		}

		if (isset($tag_params['autoplay']))
			$autoplay = fix_id($tag_params['autoplay']); else
			$autoplay = 0;

		// no id was specified
		if (is_object($video))
			if (isset($tag_params['embed']) && ($tag_params['embed'] == '1') && class_exists('swfobject')) {
				// embed video player
				$module = swfobject::getInstance();
				$module->embedSWF(
								$this->getEmbedURL($video->video_id),
								$tag_params['target'],
								isset($tag_params['width']) ? $tag_params['width'] : 320,
								isset($tag_params['height']) ? $tag_params['height'] : 240,
								array(
									'autoplay'	=> $autoplay
								),
								array(
									'wmode'	=> 'opaque',
								)
							);
			} else {
				// parse specified template
				$template = $this->loadTemplate($tag_params, 'video.xml');

				$params = array(
								'id'			=> $video->id,
								'video_id'		=> $video->video_id,
								'title'			=> $video->title,
								'thumbnail'		=> $this->getThumbnailURL($video->video_id)
							);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Handler of _video_list tag used to print list of all videos.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_VideoList($tag_params, $children) {
		global $language;

		$manager = YouTube_VideoManager::getInstance();
		$conditions = array();
		$limit = isset($tag_params['limit']) ? fix_id($tag_params['limit']) : null;
		$order_by = isset($tag_params['order_by']) ? explode(',', fix_chars($tag_params['order_by'])) : array('id');
		$order_asc = isset($tag_params['order_asc']) && $tag_params['order_asc'] == 'yes' ? true : false;

		// grab parameters
		if (isset($tag_params['group_id']) || isset($tag_params['group_text_id'])) {
			$group_id = null;
			$membership_manager = YouTube_MembershipManager::getInstance();

			if (isset($tag_params['group_text_id'])) {
				// group text id was specified
				$group_manager = YouTube_GroupManager::getInstance();

				$group_item = $group_manager->getSingleItem(
												array('id'),
												array(
													'text_id' => fix_chars($tag_params['group_text_id'])
												)
											);

				if (is_object($group_item))
					$group_id = $group_item->id;

			} else {
				// group id number was specified
				$group_id = fix_id($tag_params['group_id']);
			}


			$membership_items = $membership_manager->getItems(
											array('video'),
											array('group' => $group_id)
										);

			// prepare list of items
			$item_list = array();

			if (count($membership_items) > 0)
				foreach($membership_items as $item)
					$item_list[] = $item->video;

			// make sure nothing is selected if groupd doesn't contain any videos
			if (count($item_list) == 0)
				$item_list[] = -1;

			// add item list to conditions
			$conditions['id'] = $item_list;
		}

		if (isset($tag_params['random']) && $tag_params['random'] == '1') {
			$order_by = array('RAND()');
		}

		// get items from database
		$items = $manager->getItems(
								$manager->getFieldNames(),
								$conditions,
								$order_by,
								$order_asc,
								$limit
							);

		// create template
		$template = $this->loadTemplate($tag_params, 'video_item.xml');
		$template->registerTagHandler('_video', $this, 'tag_Video');
		$template->registerTagHandler('_thumbnail', $this, 'tag_Thumbnail');

		// parse template
		if (count($items) > 0)
		foreach ($items as $item) {
			$params = array(
							'id'			=> $item->id,
							'video_id'		=> $item->video_id,
							'title'			=> $item->title,
							'thumbnail'		=> $this->getThumbnailURL($item->video_id),
							'item_change'	=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														$this->name.'_video_change', 	// window id
														400,							// width
														$this->getLanguageConstant('title_video_change'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'video_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> url_MakeHyperlink(
													$this->getLanguageConstant('delete'),
													window_Open(
														$this->name.'_video_delete', 	// window id
														300,							// width
														$this->getLanguageConstant('title_video_delete'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'video_delete'),
															array('id', $item->id)
														)
													)
												),
							'item_preview'	=> url_MakeHyperlink(
													$this->getLanguageConstant('preview'),
													window_Open(
														$this->name.'_video_preview', 	// window id
														400,							// width
														$item->title[$language], 		// title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'video_preview'),
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
	 * Handle displaying video thumbnail
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Thumbnail($tag_params, $children) {
		$manager = YouTube_VideoManager::getInstance();
		$video = null;
		$image_list = null;

		// get parameters
		if (isset($tag_params['id'])) {
			// get video based on id
			$video_id = fix_id($tag_params['id']);
			$video = $manager->getSingleItem($manager->getFieldNames(), array('id' => $video_id));

		} else if (isset($tag_params['text_id'])) {
			// get video based on textual id
			$video_id = fix_chars($tag_params['text_id']);
			$video = $manager->getSingleItem($manager->getFieldNames(), array('text_id' => $video_id));
		}

		if (isset($tag_params['image_number']))
			$image_list = fix_id(explode(',', $tag_params['image_number']));

		// make sure image number is within valid range
		if (count($image_list) == 0 || min($image_list) < 1 || max($image_list) > 3)
			$image_number = array(2);

		// create template
		$template = $this->loadTemplate($tag_params, 'video_thumbnail.xml');
		$template->setMappedModule($this->name);

		// parse template
		if (!is_null($video))
			foreach($image_list as $image_number) {
				$image_url = $this->getThumbnailURL($video->video_id, $image_number);

				$params = array(
								'id'		=> $video->id,
								'video_id'	=> $video->video_id,
								'title'		=> $video->title,
								'thumbnail'	=> $image_url
							);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Handle group parsing
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Group($tag_params, $children) {
		$conditions = array();

		if (isset($tag_params['text_id']))
			$conditions['text_id'] = fix_chars($tag_params['text_id']);
	}

	/**
	 * Handle group list tag parsing
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_GroupList($tag_params, $children) {
		$manager = YouTube_GroupManager::getInstance();
		$conditions = array();

		// gather all the parameters
		if (isset($tag_params['visible_only']))
			$conditions['text_id'] = fix_chars($tag_params['text_id']);

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// create template handler
		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('group_item.xml', $this->path.'templates/');
		}
		$template->setMappedModule($this);

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
								'id'			=> $item->id,
								'name'			=> $item->name,
								'description'	=> $item->description,
								'visible'		=> $item->visible,
								'visible_char'	=> $item->visible == 1 ? CHAR_CHECKED : CHAR_UNCHECKED,
								'item_change'	=> url_MakeHyperlink(
														$this->getLanguageConstant('change'),
														window_Open(
															$this->name.'_group_change', 	// window id
															400,							// width
															$this->getLanguageConstant('title_group_change'), // title
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
															$this->name.'_group_delete', 	// window id
															300,							// width
															$this->getLanguageConstant('title_group_delete'), // title
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
								'item_videos'	=> url_MakeHyperlink(
														$this->getLanguageConstant('videos'),
														window_Open(
															$this->name.'_group_videos', 	// window id
															400,							// width
															$this->getLanguageConstant('title_group_videos'), // title
															false, false,
															url_Make(
																'transfer_control',
																'backend_module',
																array('module', $this->name),
																array('backend_action', 'group_videos'),
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
	 * Handle displaying group memberships
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_GroupVideos($tag_params, $children) {
		global $language;

		if (!isset($tag_params['group'])) return;

		$group = fix_id($tag_params['group']);
		$manager = YouTube_VideoManager::getInstance();
		$membership_manager = YouTube_MembershipManager::getInstance();

		$memberships = $membership_manager->getItems(
												array('video'),
												array('group' => $group)
											);

		$video_ids = array();
		if (count($memberships) > 0)
			foreach($memberships as $membership)
				$video_ids[] = $membership->video;

		$items = $manager->getItems($manager->getFieldNames(), array(), array('title_'.$language));

		$template = new TemplateHandler('group_videos_item.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
								'id'				=> $item->id,
								'in_group'			=> in_array($item->id, $video_ids) ? 1 : 0,
								'title'				=> $item->title,
								'video_id'			=> $item->video_id,
								'text_id'			=> $item->text_id
							);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Generate JSON object for specified video
	 */
	private function json_Video() {
		global $language;

		define('_OMIT_STATS', 1);

		$id = fix_id($_REQUEST['id']);
		$all_languages = isset($_REQUEST['all_languages']) && $_REQUEST['all_languages'] == 'yes';

		$manager = YouTube_VideoManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'item'			=> array()
				);

		if (is_object($item)) {
			$result['item']['id'] = $item->id;
			$result['item']['video_id'] = $item->video_id;
			$result['item']['title'] = $all_languages ? $item->title : $item->title[$language];
			$result['item']['thumbnail'] = $this->getThumbnailURL($item->video_id);
			$result['item']['embed_url'] = $this->getEmbedURL($item->video_id);
		}

		print json_encode($result);
	}

	/**
	 * Generate list of videos in for of a JSON object
	 */
	private function json_VideoList() {
		global $language;

		define('_OMIT_STATS', 1);

		$limit = isset($tag_params['limit']) ? fix_id($tag_params['limit']) : null;
		$order_by = isset($tag_params['order_by']) ? explode(',', fix_chars($tag_params['order_by'])) : array('id');
		$order_asc = isset($tag_params['order_asc']) && $tag_params['order_asc'] == 'yes' ? true : false;
		$all_languages = isset($_REQUEST['all_languages']) && $_REQUEST['all_languages'] == 'yes';

		$manager = YouTube_VideoManager::getInstance();

		$items = $manager->getItems(
								$manager->getFieldNames(),
								array(),
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
							'id'			=> $item->id,
							'video_id'		=> $item->video_id,
							'title'			=> $all_languages ? $item->title : $item->title[$language],
							'thumbnail'		=> $this->getThumbnailURL($item->video_id),
						);
		} else {

		}

		print json_encode($result);
	}

	/**
	 * Simple function that provides thumbnail image URL based on video ID
	 *
	 * @param string[11] $video_id
	 * @param integer $number 1-3
	 * @return string
	 */
	public function getThumbnailURL($video_id, $number=2) {
		return "http://img.youtube.com/vi/{$video_id}/{$number}.jpg";
	}

	/**
	 * Get URL for embeded video player for specified video ID
	 *
	 * @param string[11] $video_id
	 * @return string
	 */
	public function getEmbedURL($video_id) {
		return "http://www.youtube.com/v/{$video_id}?enablejsapi=1&version=3";
	}

}

