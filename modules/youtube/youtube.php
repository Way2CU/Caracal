<?php

/**
 * YouTube Implmenetation Module
 *
 * @author MeanEYE
 * @todo Add playlist support
 */

class youtube extends Module {

	/**
	 * Constructor
	 *
	 * @return youtube
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
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show':
					$this->tag_Video($level, $params, $children);
					break;

				case 'show_thumbnail':
					$this->tag_Thumbnail($level, $params, $children);
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'video_list':
					$this->showList($level);
					break;

				case 'video_add':
					$this->addVideo($level);
					break;

				case 'video_change':
					$this->changeVideo($level);
					break;

				case 'video_save':
					$this->saveVideo($level);
					break;

				case 'video_delete':
					$this->deleteVideo($level);
					break;

				case 'video_delete_commit':
					$this->deleteVideo_Commit($level);
					break;

				case 'video_preview':
					$this->previewVideo($level);
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	function onInit() {
		global $db, $db_active;

		$sql = "
			CREATE TABLE IF NOT EXISTS `youtube_video` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`text_id` VARCHAR (32) NULL ,
				`video_id` varchar(11) COLLATE utf8_bin NOT NULL,
				`title` varchar(255) COLLATE utf8_bin NOT NULL,
				PRIMARY KEY (`id`),
				INDEX (`text_id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	function onDisable() {
		global $db, $db_active;

		$sql = "DROP TABLE IF EXISTS `youtube_video`;";
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
			$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/default_style.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/youtube_controls.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if ($ModuleHandler->moduleExists('backend')) {
			$backend = $ModuleHandler->getObjectFromName('backend');

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

			$backend->addMenu($this->name, $youtube_menu);
		}
	}

	/**
	 * Show backend video list with options
	 */
	function showList($level) {
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

		$template->registerTagHandler('_video_list', &$this, 'tag_VideoList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Add video form
	 * @param integer $level
	 */
	function addVideo($level) {
		$template = new TemplateHandler('video_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'video_save'),
					'cancel_action'	=> window_Close($this->name.'_video_add')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Change video data form
	 * @param integer $level
	 */
	function changeVideo($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new YouTube_VideoManager();

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
		$template->parse($level);
	}

	/**
	 * Save modified or new video data
	 * @param integer $level
	 */
	function saveVideo($level) {
		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;
		$text_id = fix_chars($_REQUEST['text_id']);
		$video_id = fix_chars($_REQUEST['video_id']);
		$title = fix_chars($_REQUEST['title']);

		$manager = new YouTube_VideoManager();

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
		$template->parse($level);
	}

	/**
	 * Display confirmation dialog before removing specified video
	 *
	 * @param integer $level
	 */
	function deleteVideo($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new YouTube_VideoManager();

		$video = $manager->getSingleItem(array('title'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_video_delete"),
					'name'			=> $video->title,
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
		$template->parse($level);
	}

	/**
	 * Actually delete specified video from database
	 *
	 * @param integer $level
	 */
	function deleteVideo_Commit($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new YouTube_VideoManager();

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
		$template->parse($level);
	}

	/**
	 * Play video in backend window
	 * @param integer $level
	 */
	function previewVideo($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new YouTube_VideoManager();

		$video_id = $manager->getItemValue('video_id', array('id' => $id));

		if ($video_id) {
			$template = new TemplateHandler('video_preview.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
						'video_id'	=> $video_id,
						'button'	=> $this->getLanguageConstant("close"),
						'action'	=> window_Close($this->name.'_video_preview')
					);

			$template->registerTagHandler('_video', &$this, 'tag_Video');
			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
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
			$template->parse($level);
		}
	}

	function tag_Thumbnail($level, $params, $children) {

	}

	function tag_ThumbnailList($level, $params, $children) {

	}

	/**
	 * Handler for _video tag which embeds player in page.
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	function tag_Video($level, $params, $children) {
		global $ModuleHandler;

		$video = null;
		$manager = new YouTube_VideoManager();

		if (isset($params['id'])) {
			// video is was specified
			$video = $manager->getSingleItem($manager->getFieldNames(), array('video_id' => $params['id']));

		} else if (isset($params['text_id'])) {
			// text id was specified
			$video = $manager->getSingleItem($manager->getFieldNames(), array('text_id' => $params['text_id']));
		}

		// no id was specified
		if (!is_object($video)) return;

		if ($ModuleHandler->moduleExists('swfobject')) {
			$module = $ModuleHandler->getObjectFromName('swfobject');

			if (isset($params['embed']) && $params['embed'] == '1')
				$module->embedSWF(
								$level,
								$this->getEmbedURL($video->video_id),
								$params['target'],
								isset($params['width']) ? $params['width'] : 320,
								isset($params['height']) ? $params['height'] : 240,
								array(),
								array(
									'wmode'	=> 'opaque'
								)
							);
		}
	}

	/**
	 * Handler of _video_list tag used to print list of all videos.
	 * @param $level
	 * @param $params
	 * @param $children
	 */
	function tag_VideoList($level, $params, $children) {
		$manager = new YouTube_VideoManager();

		$items = $manager->getItems(
								$manager->getFieldNames(),
								array(),
								array('id')
							);

		$template = new TemplateHandler(
								isset($params['template']) ? $params['template'] : 'video_item.xml',
								$this->path.'templates/'
							);
		$template->setMappedModule($this->name);

		if (count($items) > 0)
		foreach ($items as $item) {
			$params = array(
							'id'			=> $item->id,
							'video_id'		=> $item->video_id,
							'title'			=> $item->title,
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
														$item->title, 					// title
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
			$template->parse($level);
		}
	}

	/**
	 * Simple function that provides thumbnail image URL based on video ID
	 *
	 * @param string[11] $video_id
	 * @param integer $number 1-3
	 * @return string
	 */
	function getThumbnailURL($video_id, $number=2) {
		return "http://img.youtube.com/vi/{$video_id}/{$number}.jpg";
	}

	/**
	 * Get URL for embeded video player for specified video ID
	 *
	 * @param string[11] $video_id
	 * @return string
	 */
	function getEmbedURL($video_id) {
		return "http://www.youtube.com/v/{$video_id}?enablejsapi=1&version=3";
	}

}

class YouTube_VideoManager extends ItemManager {

	function __construct() {
		parent::__construct('youtube_video');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('video_id', 'varchar');
		$this->addProperty('title', 'varchar');
	}
}

