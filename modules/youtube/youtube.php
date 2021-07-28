<?php

/**
 * YouTube Implmenetation Module
 *
 * Author: Mladen Mijatov
 */
use Core\Events;
use Core\Module;

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

		// connect events
		Events::connect('head-tag', 'before-print', 'add_tags', $this);
		Events::connect('backend', 'add-menu-items', 'add_menu_items', $this);
		Events::connect('backend', 'sprite-include', 'include_sprite', $this);
		Events::connect('backend', 'add-tags', 'add_backend_tags', $this);
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

				case 'include_api_script':
					$this->includeApiScript();
					break;

				case 'add_to_title':
					$manager = YouTube_VideoManager::get_instance();
					$manager->add_property_to_title('title', array('id', 'text_id'), $params);
					break;

				case 'add_group_to_title':
					$manager = YouTube_GroupManager::get_instance();
					$manager->add_property_to_title('name', array('id', 'text_id'), $params);
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
	public function initialize() {
		global $db;

		$list = Language::get_languages(false);

		// create videos table
		$sql = "
			CREATE TABLE IF NOT EXISTS `youtube_video` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`text_id` VARCHAR (32) NULL ,
				`video_id` varchar(50) COLLATE utf8_bin NOT NULL,
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
	public function cleanup() {
		global $db;

		$tables = array('youtube_video', 'youtube_groups', 'youtube_group_membership');
		$db->drop_tables($tables);
	}

	/**
	 * Add frontend tags.
	 */
	public function add_tags() {
		// add frontend scripts
		$head_tag = head_tag::get_instance();

		// load backend files if needed
		$head_tag->add_tag('script', array(
					'src'  => URL::from_file_path($this->path.'include/interactive.js'),
					'type' => 'text/javascript'
				));
	}

	/**
	 * Include tags needed for backend.
	 */
	public function add_backend_tags() {
		$head_tag = head_tag::get_instance();
		$head_tag->add_tag('script',
				array(
					'src'  => URL::from_file_path($this->path.'include/toolbar.js'),
					'type' => 'text/javascript'
				));
	}

	/**
	 * Add items to backend menu.
	 */
	public function add_menu_items() {
		$backend = backend::get_instance();

		$youtube_menu = new backend_MenuItem(
							$this->get_language_constant('menu_youtube'),
							$this->path.'images/icon.svg',
							'javascript:void(0);',
							$level=5
						);

		$youtube_menu->addChild('', new backend_MenuItem(
							$this->get_language_constant('menu_video_list'),
							$this->path.'images/list.svg',
							window_Open( // on click open window
										$this->name.'_video_list',
										650,
										$this->get_language_constant('title_video_list'),
										true, true,
										backend_UrlMake($this->name, 'video_list')
									),
							$level=5
						));

		$youtube_menu->addChild('', new backend_MenuItem(
							$this->get_language_constant('menu_video_groups'),
							$this->path.'images/groups.svg',
							window_Open( // on click open window
										$this->name.'_group_list',
										570,
										$this->get_language_constant('title_video_groups'),
										true, true,
										backend_UrlMake($this->name, 'group_list')
									),
							$level=5
						));

		$backend->addMenu($this->name, $youtube_menu);
	}

	/**
	 * Include sprite image for use in backend.
	 */
	public function include_sprite() {
		print file_get_contents($this->path.'images/sprite.svg');
	}

	/**
	 * Include YouTube IFrame API script.
	 */
	private function includeApiScript() {
		if (!ModuleHandler::is_loaded('head_tag'))
			return;

		head_tag::get_instance()->add_tag(
			'script',
			array(
				'src'	=> 'https://youtube.com/iframe_api',
				'type'	=> 'text/javascript'
			));
	}

	/**
	 * Show backend video list with options
	 */
	private function showList() {
		$template = new TemplateHandler('video_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new'	=> window_OpenHyperlink(
										$this->get_language_constant('add'),
										$this->name.'_video_add', 400,
										$this->get_language_constant('title_video_add'),
										true, false,
										$this->name,
										'video_add'
									)
					);

		$template->register_tag_handler('_video_list', $this, 'tag_VideoList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Add video form
	 */
	private function addVideo() {
		$template = new TemplateHandler('video_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'video_save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Change video data form
	 */
	private function changeVideo() {
		$id = fix_id($_REQUEST['id']);
		$manager = YouTube_VideoManager::get_instance();

		$video = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		$template = new TemplateHandler('video_change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'id'			=> $video->id,
					'text_id'		=> unfix_chars($video->text_id),
					'video_id'		=> unfix_chars($video->video_id),
					'title'			=> unfix_chars($video->title),
					'form_action'	=> backend_UrlMake($this->name, 'video_save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save modified or new video data
	 */
	private function saveVideo() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$text_id = fix_chars($_REQUEST['text_id']);
		$video_id = fix_chars($_REQUEST['video_id']);
		$title = $this->get_multilanguage_field('title');

		$manager = YouTube_VideoManager::get_instance();

		$data = array(
					'text_id'	=> $text_id,
					'video_id'	=> $video_id,
					'title' 	=> $title
				);

		if (is_null($id))
			$manager->insert_item($data); else
			$manager->update_items($data, array('id' => $id));


		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$window_name = $this->name.(is_null($id) ? '_video_add' : '_video_change');
		$params = array(
					'message'	=> $this->get_language_constant("message_video_saved"),
					'button'	=> $this->get_language_constant("close"),
					'action'	=> window_Close($window_name).";".window_ReloadContent($this->name.'_video_list')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Display confirmation dialog before removing specified video
	 */
	private function deleteVideo() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = YouTube_VideoManager::get_instance();

		$video = $manager->get_single_item(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant("message_video_delete"),
					'name'			=> $video->title[$language],
					'yes_text'		=> $this->get_language_constant("delete"),
					'no_text'		=> $this->get_language_constant("cancel"),
					'yes_action'	=> window_LoadContent(
											$this->name.'_video_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'video_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close($this->name.'_video_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Actually delete specified video from database
	 */
	private function deleteVideo_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = YouTube_VideoManager::get_instance();

		$manager->delete_items(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$window_name = $this->name.'_video_delete';
		$params = array(
					'message'	=> $this->get_language_constant("message_video_deleted"),
					'button'	=> $this->get_language_constant("close"),
					'action'	=> window_Close($window_name).";".window_ReloadContent($this->name.'_video_list')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Play video in backend window
	 */
	private function previewVideo() {
		$id = fix_id($_REQUEST['id']);
		$manager = YouTube_VideoManager::get_instance();

		$video_id = $manager->get_item_value('id', array('id' => $id));

		if ($video_id) {
			$template = new TemplateHandler('video_preview.xml', $this->path.'templates/');
			$template->set_mapped_module($this->name);

			$params = array(
						'video_id'	=> $video_id,
						'button'	=> $this->get_language_constant('close'),
						'action'	=> window_Close($this->name.'_video_preview')
					);

			$template->register_tag_handler('cms:video', $this, 'tag_Video');
			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();

		} else {
			// show error message
			$template = new TemplateHandler('message.xml', $this->path.'templates/');
			$template->set_mapped_module($this->name);

			$params = array(
						'message'	=> $this->get_language_constant('message_video_error'),
						'button'	=> $this->get_language_constant('close'),
						'action'	=> window_Close($this->name.'_video_preview')
					);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Show window displaying groups
	 */
	private function showGroups() {
		$template = new TemplateHandler('group_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new'	=> window_OpenHyperlink(
										$this->get_language_constant('create_group'),
										$this->name.'_group_create', 400,
										$this->get_language_constant('title_group_create'),
										true, false,
										$this->name,
										'group_create'
									)
					);

		$template->register_tag_handler('cms:group_list', $this, 'tag_GroupList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show group create form
	 */
	private function createGroup() {
		$template = new TemplateHandler('group_create.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'group_save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show group changing form
	 */
	private function changeGroup() {
		$id = fix_id($_REQUEST['id']);
		$manager = YouTube_GroupManager::get_instance();

		$group = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		$template = new TemplateHandler('group_change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'id'			=> $group->id,
					'text_id'		=> unfix_chars($group->text_id),
					'name'			=> unfix_chars($group->name),
					'description'	=> $group->description,
					'visible'		=> $group->visible,
					'form_action'	=> backend_UrlMake($this->name, 'group_save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save new or changed group data
	 */
	private function saveGroup() {
		$manager = YouTube_GroupManager::get_instance();

		// get parameters and secure them
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$text_id = fix_chars($_REQUEST['text_id']);
		$name = $this->get_multilanguage_field('name');
		$description = $this->get_multilanguage_field('description');
		$visible = $this->get_boolean_field('visible') ? 1 : 0;

		if (is_null($id)) {
			// store new record
			$manager->insert_item(array(
							'text_id'		=> $text_id,
							'name'			=> $name,
							'description'	=> $description,
							'visible'		=> $visible
						));

			$window = $this->name.'_group_create';

		} else {
			// change existing record
			$manager->update_items(
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
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_group_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent($this->name.'_group_list'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show confirmation dialog for group removal
	 */
	private function deleteGroup() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = YouTube_GroupManager::get_instance();

		$group = $manager->get_single_item(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant("message_group_delete"),
					'name'			=> $group->name[$language],
					'yes_text'		=> $this->get_language_constant("delete"),
					'no_text'		=> $this->get_language_constant("cancel"),
					'yes_action'	=> window_LoadContent(
											$this->name.'_group_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'group_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close($this->name.'_group_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Actually remove group and tell user about the result
	 */
	private function deleteGroup_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = YouTube_GroupManager::get_instance();
		$membership_manager = YouTube_MembershipManager::get_instance();

		$manager->delete_items(array('id' => $id));
		$membership_manager->delete_items(array('group' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$window_name = $this->name.'_group_delete';
		$params = array(
					'message'	=> $this->get_language_constant("message_group_deleted"),
					'button'	=> $this->get_language_constant("close"),
					'action'	=> window_Close($window_name).";".window_ReloadContent($this->name.'_group_list')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show video selection form
	 */
	private function groupVideos() {
		$id = fix_id($_REQUEST['id']);

		$template = new TemplateHandler('group_videos.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'group'			=> $id,
					'form_action'	=> backend_UrlMake($this->name, 'group_videos_save'),
				);

		$template->register_tag_handler('_group_videos', $this, 'tag_GroupVideos');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save selected videos for specified group
	 */
	private function groupVideos_Save() {
		$group = fix_id($_REQUEST['group']);
		$membership_manager = YouTube_MembershipManager::get_instance();

		// fetch all ids being set to specific group
		$video_ids = array();
		foreach ($_REQUEST as $key => $value) {
			if (substr($key, 0, 9) == 'video_id_' && $value == 1)
				$video_ids[] = fix_id(substr($key, 8));
		}

		// remove old memberships
		$membership_manager->delete_items(array('group' => $group));

		// save new memberships
		foreach ($video_ids as $id)
			$membership_manager->insert_item(array(
											'group'	=> $group,
											'video'	=> $id
										));

		// display message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant("message_group_videos_updated"),
					'button'	=> $this->get_language_constant("close"),
					'action'	=> window_Close($this->name.'_group_videos')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Create YouTube video list group and return its id.
	 *
	 * @param array $group_name
	 * @return integer
	 */
	public function create_group($group_name) {
		$group_manager = YouTube_GroupManager::get_instance();
		$group_manager->insert_item(array(
				'name'    => $group_name,
				'visible' => 0
			));

		return $group_manager->get_inserted_id();
	}

	/**
	 * Handler for _video tag which embeds player in page.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Video($tag_params, $children) {
		global $language;

		$video = null;
		$manager = YouTube_VideoManager::get_instance();
		$embed = isset($tag_params['embed']) && $tag_params['embed'] == '1';

		if (isset($tag_params['id'])) {
			// video is was specified
			$video = $manager->get_single_item(
									$manager->get_field_names(),
									array(
										'id' => fix_id($tag_params['id'])
									));

		} else if (isset($tag_params['text_id'])) {
			// text id was specified
			$video = $manager->get_single_item(
									$manager->get_field_names(),
									array(
										'text_id' => fix_chars($tag_params['text_id'])
									));

		} else if (isset($tag_params['random'])) {
			// get random video
			$video = $manager->get_single_item(
									$manager->get_field_names(),
									array(),
									array('RAND()')
								);
		}

		// no id was specified bail
		if (!is_object($video))
			return;

		// player parameters
		$player_params = array(
				'rel'			=> isset($tag_params['show_related']) ? fix_id($tag_params['show_related']) : 0,
				'showinfo'		=> isset($tag_params['show_info']) ? fix_id($tag_params['show_info']) : 0,
				'autoplay'		=> isset($tag_params['autoplay']) ? fix_chars($tag_params['autoplay']) : 0,
				'autohide'		=> isset($tag_params['autohide']) ? fix_chars($tag_params['autohide']) : 2,
				'controls'		=> isset($tag_params['controls']) ? fix_id($tag_params['controls']) : 1,
				'color'			=> isset($tag_params['color']) ? fix_chars($tag_params['color']) : 'default',
				'origin'		=> isset($tag_params['origin']) ? fix_chars($tag_params['origin']) : _DOMAIN,
				'theme'			=> isset($tag_params['theme']) ? fix_chars($tag_params['theme']) : 'dark',
				'start'			=> isset($tag_params['start_time']) ? fix_id($tag_params['start_time']) : 0,
				'loop'			=> isset($tag_params['loop']) ? fix_id($tag_params['loop']) : 0,
				'enablejsapi'	=> isset($tag_params['enable_api']) ? fix_id($tag_params['enable_api']) : 0,
				'hl'			=> $language,
				'fs'			=> 1
			);

		// autoplay interactive videos
		if (!$embed)
			$player_params['autoplay'] = 1;

		// looping requires playlist
		if ($player_params['loop'] > 0 && !isset($player_params['playlist']))
			$player_params['playlist'] = $video->video_id;

		// prepare template parameters
		$query_data = http_build_query($player_params, '', '&amp;');
		$params = array(
				'width'        => isset($tag_params['width']) ? fix_id($tag_params['width']) : 320,
				'height'       => isset($tag_params['height']) ? fix_id($tag_params['height']) : 240,
				'player_id'    => isset($tag_params['player_id']) ? fix_chars($tag_params['player_id']) : false,
				'url'          => 'https://youtube.com/embed/'.$video->video_id.'?'.$query_data,
				'outside_url'  => 'https://youtube.com/watch?v='.$video->video_id,
				'id'           => $video->id,
				'video_id'     => $video->video_id,
				'title'        => $video->title,
				'image_number' => isset($tag_params['image_number']) ? fix_id($tag_params['image_number']) : 0
			);

		// load and render template
		$template_file = $embed ? 'embed.xml' : 'video.xml';
		$template = $this->load_template($tag_params, $template_file);

		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:thumbnail', $this, 'tag_Thumbnail');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Handler of _video_list tag used to print list of all videos.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_VideoList($tag_params, $children) {
		global $language;

		$manager = YouTube_VideoManager::get_instance();
		$conditions = array();
		$limit = isset($tag_params['limit']) ? fix_id($tag_params['limit']) : null;
		$order_by = isset($tag_params['order_by']) ? explode(',', fix_chars($tag_params['order_by'])) : array('id');

		$order_asc = true;
		if (isset($tag_params['order_asc']))
			$order_asc = $tag_params['order_asc'] == 'yes';

		// grab parameters
		if (isset($tag_params['group_id']) || isset($tag_params['group_text_id'])) {
			$group_id = null;
			$membership_manager = YouTube_MembershipManager::get_instance();

			if (isset($tag_params['group_text_id'])) {
				// group text id was specified
				$group_manager = YouTube_GroupManager::get_instance();

				$group_item = $group_manager->get_single_item(
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


			$membership_items = $membership_manager->get_items(
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
		$items = $manager->get_items(
								$manager->get_field_names(),
								$conditions,
								$order_by,
								$order_asc,
								$limit
							);

		// create template
		$template = $this->load_template($tag_params, 'video_item.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:video', $this, 'tag_Video');
		$template->register_tag_handler('cms:thumbnail', $this, 'tag_Thumbnail');

		// parse template
		if (count($items) > 0)
		foreach ($items as $item) {
			$params = array(
							'id'			=> $item->id,
							'video_id'		=> $item->video_id,
							'text_id'		=> $item->text_id,
							'title'			=> $item->title,
							'thumbnail'		=> $this->getThumbnailURL($item->video_id),
							'image'			=> $this->getThumbnailURL($item->video_id, 0),
							'item_change'	=> URL::make_hyperlink(
													$this->get_language_constant('change'),
													window_Open(
														$this->name.'_video_change', 	// window id
														400,							// width
														$this->get_language_constant('title_video_change'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'video_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> URL::make_hyperlink(
													$this->get_language_constant('delete'),
													window_Open(
														$this->name.'_video_delete', 	// window id
														300,							// width
														$this->get_language_constant('title_video_delete'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'video_delete'),
															array('id', $item->id)
														)
													)
												),
							'item_preview'	=> URL::make_hyperlink(
													$this->get_language_constant('preview'),
													window_Open(
														$this->name.'_video_preview', 	// window id
														400,							// width
														$item->title[$language], 		// title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'video_preview'),
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
	 * Handle displaying video thumbnail
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Thumbnail($tag_params, $children) {
		$manager = YouTube_VideoManager::get_instance();
		$video = null;
		$image_list = null;
		$image_width = null;
		$image_height = null;

		// get parameters
		if (isset($tag_params['id'])) {
			// get video based on id
			$video_id = fix_id($tag_params['id']);
			$video = $manager->get_single_item($manager->get_field_names(), array('id' => $video_id));

		} else if (isset($tag_params['text_id'])) {
			// get video based on textual id
			$video_id = fix_chars($tag_params['text_id']);
			$video = $manager->get_single_item($manager->get_field_names(), array('text_id' => $video_id));
		}

		if (isset($tag_params['image_number']))
			$image_list = fix_id(explode(',', $tag_params['image_number']));

		if (isset($tag_params['width']))
			$image_width = fix_id($tag_params['width']);

		if (isset($tag_params['height']))
			$image_height = fix_id($tag_params['height']);

		// make sure image number is within valid range
		if (count($image_list) == 0 || min($image_list) < 1 || max($image_list) > 3)
			$image_number = array(2);

		// create template
		$template = $this->load_template($tag_params, 'video_thumbnail.xml');
		$template->set_template_params_from_array($children);

		// parse template
		if (!is_null($video))
			foreach($image_list as $image_number) {
				$image_url = $this->getThumbnailURL($video->video_id, $image_number);

				$params = array(
								'id'        => $video->id,
								'video_id'  => $video->video_id,
								'title'     => $video->title,
								'thumbnail' => $image_url,
								'width'     => $image_width,
								'height'    => $image_height
							);

				$template->restore_xml();
				$template->set_local_params($params);
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
		$manager = YouTube_GroupManager::get_instance();
		$conditions = array();
		$order_by = array('id');
		$order_asc = true;

		// gather all the parameters
		if (isset($tag_params['visible_only']))
			$conditions['text_id'] = fix_chars($tag_params['text_id']);

		if (isset($tag_params['order_by']))
			$order_by = explode(',', fix_chars($tag_params['order_by']));

		if (isset($tag_params['order_asc']))
			$order_asc = $tag_params['order_asc'] == 'yes';

		// get items from database
		$items = $manager->get_items($manager->get_field_names(), $conditions, $order_by, $order_asc);

		// create template handler
		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('group_item.xml', $this->path.'templates/');
		}
		$template->set_mapped_module($this);

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
								'id'			=> $item->id,
								'name'			=> $item->name,
								'description'	=> $item->description,
								'visible'		=> $item->visible,
								'visible_char'	=> $item->visible == 1 ? CHAR_CHECKED : CHAR_UNCHECKED,
								'item_change'	=> URL::make_hyperlink(
														$this->get_language_constant('change'),
														window_Open(
															$this->name.'_group_change', 	// window id
															400,							// width
															$this->get_language_constant('title_group_change'), // title
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
															$this->name.'_group_delete', 	// window id
															300,							// width
															$this->get_language_constant('title_group_delete'), // title
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
								'item_videos'	=> URL::make_hyperlink(
														$this->get_language_constant('videos'),
														window_Open(
															$this->name.'_group_videos', 	// window id
															400,							// width
															$this->get_language_constant('title_group_videos'), // title
															false, false,
															URL::make_query(
																'backend_module',
																'transfer_control',
																array('module', $this->name),
																array('backend_action', 'group_videos'),
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
	 * Handle displaying group memberships
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_GroupVideos($tag_params, $children) {
		global $language;

		if (!isset($tag_params['group'])) return;

		$group = fix_id($tag_params['group']);
		$manager = YouTube_VideoManager::get_instance();
		$membership_manager = YouTube_MembershipManager::get_instance();

		$memberships = $membership_manager->get_items(
												array('video'),
												array('group' => $group)
											);

		$video_ids = array();
		if (count($memberships) > 0)
			foreach($memberships as $membership)
				$video_ids[] = $membership->video;

		$items = $manager->get_items($manager->get_field_names(), array(), array('title_'.$language));

		$template = new TemplateHandler('group_videos_item.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
								'id'				=> $item->id,
								'in_group'			=> in_array($item->id, $video_ids) ? 1 : 0,
								'title'				=> $item->title,
								'video_id'			=> $item->video_id,
								'text_id'			=> $item->text_id
							);

				$template->restore_xml();
				$template->set_local_params($params);
				$template->parse();
			}
	}

	/**
	 * Generate JSON object for specified video
	 */
	private function json_Video() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$all_languages = isset($_REQUEST['all_languages']) && $_REQUEST['all_languages'] == 'yes';

		$manager = YouTube_VideoManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

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

		$limit = isset($tag_params['limit']) ? fix_id($tag_params['limit']) : null;
		$order_by = isset($tag_params['order_by']) ? explode(',', fix_chars($tag_params['order_by'])) : array('id');
		$order_asc = isset($tag_params['order_asc']) && $tag_params['order_asc'] == 'yes' ? true : false;
		$all_languages = isset($_REQUEST['all_languages']) && $_REQUEST['all_languages'] == 'yes';

		$manager = YouTube_VideoManager::get_instance();

		$items = $manager->get_items(
								$manager->get_field_names(),
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
	 * @param integer $number 0-3
	 * @return string
	 */
	public function getThumbnailURL($video_id, $number=2) {
		return (_SECURE ? 'https://' : 'http://')."img.youtube.com/vi/{$video_id}/{$number}.jpg";
	}

	/**
	 * Get URL for embeded video player for specified video ID
	 *
	 * @param string[11] $video_id
	 * @return string
	 */
	public function getEmbedURL($video_id, $params=array()) {
		$params['enablejsapi'] = 1;
		$params['version'] = 3;

		// join params into query string
		$new_params = array();
		foreach ($params as $key => $value)
			$new_params[] = $key.'='.$value;

		$query_params = implode('&amp;', $new_params);

		return (_SECURE ? 'https://' : 'http://')."www.youtube.com/v/{$video_id}?{$query_params}";
	}

}

