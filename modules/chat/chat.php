<?php

/**
 * Semi-Realtime HTTP Based Chat
 *
 * @author MeanEYE.rcf
 */

class chat extends Module {

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
				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'chat':
					$this->chatInterface($level);
					break;

				case 'chat_settings':
					$this->chatSettings($level);
					break;

				case 'chat_channel_add':
					$this->addChannel($level);
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

		$sql = "";

		if ($db_active == 1) $db->query($sql);

		if (!array_key_exists('update_interval', $this->settings))
			$this->saveSetting('update_interval', 2000);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	function onDisable() {
		global $db_active, $db;

		$sql = "";

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
			$head_tag->addTag(
							'link',
							array(
								'href'	=> url_GetFromFilePath($this->path.'include/chat.css'),
								'rel'	=> 'stylesheet',
								'type'	=> 'text/css'
							)
						);

			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/_blank.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if ($ModuleHandler->moduleExists('backend')) {
			$backend = $ModuleHandler->getObjectFromName('backend');

			$chat_menu = new backend_MenuItem(
								$this->getLanguageConstant('menu_chat'),
								url_GetFromFilePath($this->path.'images/icon.png'),
								'javascript:void(0);',
								$level=5
							);

			$chat_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_join_chat'),
								url_GetFromFilePath($this->path.'images/chat.png'),
								window_Open( // on click open window
											'chat',
											670,
											$this->getLanguageConstant('title_chat'),
											true, true,
											backend_UrlMake($this->name, 'chat')
										),
								$level=5
							));
			$chat_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_settings'),
								url_GetFromFilePath($this->path.'images/settings.png'),
								window_Open( // on click open window
											'chat_settings',
											450,
											$this->getLanguageConstant('title_chat_settings'),
											true, true,
											backend_UrlMake($this->name, 'chat_settings')
										),
								$level=5
							));

			$backend->addMenu($this->name, $chat_menu);
		}
	}

	/**
	 * Create chat interface
	 * @param integer $level
	 */
	function chatInterface($level) {
		$template = new TemplateHandler('chat_window.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_settings'	=> backend_WindowHyperlink(
										$this->getLanguageConstant('menu_settings'),
										'chat_settings', 450,
										$this->getLanguageConstant('title_chat_settings'),
										true, false,
										$this->name,
										'chat_settings'
									)
					);

		$template->registerTagHandler('_channel_list', &$this, 'tag_ChannelList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Display channel management form
	 * @param integer $level
	 */
	function chatSettings($level) {
		$template = new TemplateHandler('channel_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
						'link_new'		=> backend_WindowHyperlink(
											$this->getLanguageConstant('add_channel'),
											'chat_settings_add_channel', 400,
											$this->getLanguageConstant('title_channel_add'),
											true, false,
											$this->name,
											'chat_channel_add'
										),
					);

		$template->registerTagHandler('_channel_list', &$this, 'tag_ChannelList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Input form for new channel
	 * @param intger $level
	 */
	function addChannel($level) {
		$template = new TemplateHandler('channel_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'chat_channel_save'),
					'cancel_action'	=> window_Close('chat_settings_add_channel')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Tag handler for printing channel lists
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	function tag_ChannelList($level, $params, $children) {
	}
}

/*
class SomeManager extends ItemManager {
	function __construct() {
		parent::ItemManager();

		$this->addProperty('id', 'int');
	}
}
*/
