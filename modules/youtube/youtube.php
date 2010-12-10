<?php

/**
 * YouTube Implmenetation Module
 *
 * @author MeanEYE
 * @todo Add playlist support
 */

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
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($level, $params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show':
					$this->tag_Video($level, $params, $children);
					break;

				case 'show_list':
					$this->tag_VideoList($level, $params, $children);
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
	public function onInit() {
		global $db, $db_active;

		$list = MainLanguageHandler::getInstance()->getLanguages(false);

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

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db, $db_active;

		$sql = "DROP TABLE IF EXISTS `youtube_video`;";
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Show backend video list with options
	 *
	 * @param integer $level
	 */
	private function showList($level) {
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
	 *
	 * @param integer $level
	 */
	private function addVideo($level) {
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
	 *
	 * @param integer $level
	 */
	private function changeVideo($level) {
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
		$template->parse($level);
	}

	/**
	 * Save modified or new video data
	 *
	 * @param integer $level
	 */
	private function saveVideo($level) {
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
		$template->parse($level);
	}

	/**
	 * Display confirmation dialog before removing specified video
	 *
	 * @param integer $level
	 */
	private function deleteVideo($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = YouTube_VideoManager::getInstance();

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
	private function deleteVideo_Commit($level) {
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
		$template->parse($level);
	}

	/**
	 * Play video in backend window
	 * @param integer $level
	 */
	private function previewVideo($level) {
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

	/**
	 * Handler for _video tag which embeds player in page.
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	public function tag_Video($level, $params, $children) {
		$video = null;
		$manager = YouTube_VideoManager::getInstance();

		if (isset($params['id'])) {
			// video is was specified
			$video = $manager->getSingleItem($manager->getFieldNames(), array('id' => $params['id']));

		} else if (isset($params['text_id'])) {
			// text id was specified
			$video = $manager->getSingleItem($manager->getFieldNames(), array('text_id' => $params['text_id']));
		}

		// no id was specified
		if (!is_object($video)) return;

		if (class_exists('swfobject')) {
			$module = swfobject::getInstance();

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
	 *
	 * @param $level
	 * @param $tag_params
	 * @param $children
	 */
	public function tag_VideoList($level, $tag_params, $children) {
		global $language;

		$manager = YouTube_VideoManager::getInstance();
		$limit = isset($tag_params['limit']) ? fix_id($tag_params['limit']) : null;
		$order_by = isset($tag_params['order_by']) ? split(fix_chars($tag_params['order_by'])) : array('id');
		$order_asc = isset($tag_params['order_asc']) && $tag_params['order_asc'] == 'yes' ? true : false;

		$items = $manager->getItems(
								$manager->getFieldNames(),
								array(),
								$order_by,
								$order_asc,
								$limit
							);

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('video_item.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);

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


class YouTube_VideoManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('youtube_video');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('video_id', 'varchar');
		$this->addProperty('title', 'ml_varchar');
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

