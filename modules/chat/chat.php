<?php

/**
 * Semi-Realtime HTTP Based Chat
 *
 * Note: Currently this module is not working.
 *
 * Author: Mladen Mijatov
 */

class chat extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// load module style and scripts
		if ($section == 'backend' && class_exists('head_tag')) {
			$head_tag = head_tag::getInstance();
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
		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			$chat_menu = new backend_MenuItem(
								$this->getLanguageConstant('menu_chat'),
								url_GetFromFilePath($this->path.'images/icon.svg'),
								'javascript:void(0);',
								$level=5
							);

			$chat_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_join_chat'),
								url_GetFromFilePath($this->path.'images/chat.svg'),
								window_Open( // on click open window
											'chat',
											670,
											$this->getLanguageConstant('title_chat'),
											true, true,
											backend_UrlMake($this->name, 'chat')
										),
								$level=5
							));
			$chat_menu->addSeparator(5);
			$chat_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_users'),
								url_GetFromFilePath($this->path.'images/users.svg'),
								window_Open( // on click open window
											'chat_users',
											550,
											$this->getLanguageConstant('title_chat_users'),
											true, true,
											backend_UrlMake($this->name, 'users')
										),
								$level=5
							));
			$chat_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_rooms'),
								url_GetFromFilePath($this->path.'images/rooms.svg'),
								window_Open( // on click open window
											'chat_rooms',
											450,
											$this->getLanguageConstant('title_chat_rooms'),
											true, true,
											backend_UrlMake($this->name, 'rooms')
										),
								$level=5
							));
			$chat_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_admins'),
								url_GetFromFilePath($this->path.'images/admins.svg'),
								window_Open( // on click open window
											'chat_admins',
											450,
											$this->getLanguageConstant('title_chat_admins'),
											true, true,
											backend_UrlMake($this->name, 'admins')
										),
								$level=5
							));

			$backend->addMenu($this->name, $chat_menu);
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
				case 'show_user_list':
					$this->tag_UserList($params, $children);
					break;

				case 'show_room_list':
					$this->tag_RoomList($params, $children);
					break;

				case 'show_admin_list':
					$this->tag_AdminList($params, $children);
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'chat':
					$this->chatInterface();
					break;

				case 'chat_settings':
					$this->chatSettings();
					break;

				// ---

				case 'users':
					$this->showUsers();
					break;

				case 'users_new':
					$this->addUser();
					break;

				case 'users_change':
					$this->changeUser();
					break;

				case 'users_save':
					$this->saveUser();
					break;

				case 'users_delete':
					$this->deleteUser();
					break;

				case 'users_delete_commit':
					$this->deleteUser_Commit();
					break;

				// ---

				case 'rooms':
					$this->showRooms();
					break;

				case 'rooms_new':
					$this->addRoom();
					break;

				case 'rooms_change':
					$this->changeRoom();
					break;

				case 'rooms_save':
					$this->saveRoom();
					break;

				case 'rooms_delete':
					$this->deleteRoom();
					break;

				case 'rooms_delete_commit':
					$this->deleteRoom_Commit();
					break;

				// ---

				case 'admins':
					$this->showAdmins();
					break;

				case 'admins_new':
					$this->addAdmin();
					break;

				case 'admins_save':
					$this->saveAdmin();
					break;

				case 'admins_delete':
					$this->deleteAdmin();
					break;

				case 'admins_delete_commit':
					$this->deleteAdmin_Commit();
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

		$sql = "CREATE TABLE `chat_log` (
					`id` INT NOT NULL AUTO_INCREMENT ,
					`user` INT NOT NULL ,
					`room` INT NOT NULL ,
					`timestamp` TIMESTAMP NOT NULL ,
					`message` VARCHAR(255) NOT NULL ,
					PRIMARY KEY (`id`) ,
					INDEX `index_by_user` (`user` ASC) ,
					INDEX `index_by_room` (`room` ASC)
				)
				ENGINE = MyISAM
				DEFAULT CHARACTER SET = utf8
				COLLATE = utf8_bin
				AUTO_INCREMENT=0;";
		$db->query($sql);

		$sql = "CREATE TABLE `chat_rooms` (
					`id` INT NOT NULL AUTO_INCREMENT ,
					`text_id` VARCHAR(45) NOT NULL DEFAULT '',";

		foreach($list as $language)
			$sql .= "`name_{$language}` VARCHAR(45) NOT NULL ,";

		foreach($list as $language)
			$sql .= "`description_{$language}` TEXT NOT NULL ,";

		$sql .="	`limit` SMALLINT NOT NULL DEFAULT 0 ,
					`password` VARCHAR(45) NOT NULL ,
					PRIMARY KEY (`id`)
				)
				ENGINE = MyISAM
				DEFAULT CHARACTER SET = utf8
				COLLATE = utf8_bin
				AUTO_INCREMENT=0;";
		$db->query($sql);

		$sql = "CREATE TABLE `chat_admins` (
					`id` INT NOT NULL AUTO_INCREMENT ,
					`user` INT NOT NULL ,
					`room` INT NOT NULL ,
					PRIMARY KEY (`id`) ,
					INDEX `index1` (`user` ASC, `room` ASC)
				)
				ENGINE = MyISAM
				DEFAULT CHARACTER SET = utf8
				COLLATE = utf8_bin
				AUTO_INCREMENT=0;";
		$db->query($sql);

		$sql = "CREATE TABLE `chat_users` (
					`id` INT NOT NULL AUTO_INCREMENT ,
					`temp` TINYINT(1)  NOT NULL DEFAULT 1 ,
					`username` VARCHAR(45) NOT NULL ,
					`password` VARCHAR(45) NOT NULL ,
					`display_name` VARCHAR(45) NOT NULL ,
					`last_active` TIMESTAMP NOT NULL ,
					PRIMARY KEY (`id`)
				)
				ENGINE = MyISAM
				DEFAULT CHARACTER SET = utf8
				COLLATE = utf8_bin
				AUTO_INCREMENT=0;";
		$db->query($sql);

		if (!array_key_exists('update_interval', $this->settings))
			$this->saveSetting('update_interval', 2000);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array('chat_log', 'chat_rooms', 'chat_admins', 'chat_users');
		$db->drop_tables($tables);
	}

	/**
	 * Show users administration form
	 */
	private function showUsers() {
		$template = new TemplateHandler('users_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('new'),
										'chat_users_new', 400,
										$this->getLanguageConstant('title_chat_users_new'),
										true, false,
										$this->name,
										'users_new'
									),
					);

		$template->registerTagHandler('_user_list', $this, 'tag_UserList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show user entry form
	 */
	private function addUser() {
		$template = new TemplateHandler('users_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'users_save'),
					'cancel_action'	=> window_Close('chat_users_new')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show user change form
	 */
	private function changeUser() {
		$id = fix_id($_REQUEST['id']);
		$manager = ChatUserManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('users_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		if (is_object($item)) {
			$params = array(
						'id'			=> $item->id,
						'username'		=> unfix_chars($item->username),
						'display_name'	=> unfix_chars($item->display_name),
						'temp'			=> $item->temp,
						'form_action'	=> backend_UrlMake($this->name, 'users_save'),
						'cancel_action'	=> window_Close('chat_users_change')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save changed or new user data
	 */
	private function saveUser() {
		$manager = ChatUserManager::getInstance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$data = array(
					'temp'			=> fix_id($_REQUEST['temp']),
					'username'		=> fix_chars($_REQUEST['username']),
					'display_name'	=> fix_chars($_REQUEST['display_name'])
				);

		if (is_null($id)) {
			$data['password'] = escape_chars($_REQUEST['password']);

			$window = 'chat_users_new';
			$manager->insertData($data);
		} else {
			if (isset($_REQUEST['password']) && !empty($_REQUEST['password']))
				$data['password'] = escape_chars($_REQUEST['password']);

			$window = 'chat_users_change';
			$manager->updateData($data,	array('id' => $id));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_chat_user_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('chat_users'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Print delete user confirmation dialog
	 */
	private function deleteUser() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = ChatUserManager::getInstance();

		$item = $manager->getSingleItem(array('display_name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_chat_user_delete"),
					'name'			=> $item->display_name,
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'chat_users_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'users_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('chat_users_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Delete user from database
	 */
	private function deleteUser_Commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = ChatUserManager::getInstance();
		$admin_manager = ChatAdminManager::getInstance();

		$manager->deleteData(array('id' => $id));
		$admin_manager->deleteData(array('user' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_chat_user_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('chat_users_delete').";".window_ReloadContent('chat_users')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show rooms management from
	 */
	private function showRooms() {
		$template = new TemplateHandler('rooms_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('new'),
										'chat_rooms_new', 400,
										$this->getLanguageConstant('title_chat_rooms_new'),
										true, false,
										$this->name,
										'rooms_new'
									),
					);

		$template->registerTagHandler('_room_list', $this, 'tag_RoomList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Create new room form
	 */
	private function addRoom() {
		$template = new TemplateHandler('rooms_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'rooms_save'),
					'cancel_action'	=> window_Close('chat_rooms_new')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Change room data
	 */
	private function changeRoom() {
		$id = fix_id($_REQUEST['id']);
		$manager = ChatRoomManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('rooms_change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		if (is_object($item)) {
			$params = array(
						'id'			=> $item->id,
						'text_id'		=> $item->text_id,
						'name'			=> unfix_chars($item->name),
						'description'	=> $item->description,
						'limit'			=> $item->limit,
						'password'		=> $item->password,
						'form_action'	=> backend_UrlMake($this->name, 'rooms_save'),
						'cancel_action'	=> window_Close('chat_rooms_change')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save changed or new room data
	 */
	private function saveRoom() {
		$manager = ChatRoomManager::getInstance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$data = array(
					'text_id'		=> escape_chars($_REQUEST['text_id']),
					'name'			=> $this->getMultilanguageField('name'),
					'description'	=> $this->getMultilanguageField('description'),
					'limit'			=> fix_id($_REQUEST['limit'])
				);

		if (is_null($id)) {
			$data['password'] = escape_chars($_REQUEST['password']);

			$window = 'chat_rooms_new';
			$manager->insertData($data);
		} else {
			if (isset($_REQUEST['password']) && !empty($_REQUEST['password']))
				$data['password'] = escape_chars($_REQUEST['password']);

			$window = 'chat_rooms_change';
			$manager->updateData($data,	array('id' => $id));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_chat_room_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('chat_rooms'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Print confirmation dialog before removing room
	 */
	private function deleteRoom() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = ChatRoomManager::getInstance();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_chat_room_delete"),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'chat_rooms_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'rooms_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('chat_rooms_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Remove room and it's admins
	 */
	private function deleteRoom_Commit() {
		$id = fix_chars($_REQUEST['id']);
		$manager = ChatRoomManager::getInstance();
		$admin_manager = ChatAdminManager::getInstance();

		$manager->deleteData(array('id' => $id));
		$admin_manager->deleteData(array('room' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_chat_room_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('chat_rooms_delete').";".window_ReloadContent('chat_rooms')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show admin management form
	 */
	private function showAdmins() {
		$template = new TemplateHandler('admins_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('new'),
										'chat_admins_new', 400,
										$this->getLanguageConstant('title_chat_admins_new'),
										true, false,
										$this->name,
										'admins_new'
									),
					);

		$template->registerTagHandler('_admin_list', $this, 'tag_AdminList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Create admin form
	 */
	private function addAdmin() {
		$template = new TemplateHandler('admins_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'admins_save'),
					'cancel_action'	=> window_Close('chat_admins_new')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save new admin data
	 */
	private function saveAdmin() {
		$manager = ChatAdminManager::getInstance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$data = array(
					'user'	=> fix_id($_REQUEST['user']),
					'room'	=> fix_id($_REQUEST['room'])
				);

		$manager->insertData($data);

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_chat_admin_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('chat_admins_new').";".window_ReloadContent('chat_admins'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Delete admin access
	 */
	private function deleteAdmin() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = ChatAdminManager::getInstance();
		$user_manager = ChatUserManager::getInstance();
		$room_manager = ChatRoomManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));
		$user = $user_manager->getItemValue('display_name', array('id' => $item->user));
		$room = $room_manager->getItemValue('name_'.$language, array('id' => $item->room));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_chat_admin_delete"),
					'name'			=> "{$user} / {$room}",
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'chat_admins_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'admins_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('chat_admins_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Remove administrator
	 */
	private function deleteAdmin_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ChatAdminManager::getInstance();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_chat_admin_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('chat_admins_delete').";".window_ReloadContent('chat_admins')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Create chat interface
	 */
	private function chatInterface() {
		$template = new TemplateHandler('chat_window.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_users'	=> window_OpenHyperlink(
										$this->getLanguageConstant('menu_users'),
										'chat_users', 450,
										$this->getLanguageConstant('title_chat_users'),
										true, false,
										$this->name,
										'users'
									),
					'link_rooms'	=> window_OpenHyperlink(
										$this->getLanguageConstant('menu_rooms'),
										'chat_rooms', 450,
										$this->getLanguageConstant('title_chat_rooms'),
										true, false,
										$this->name,
										'rooms'
									),
					'link_admins'	=> window_OpenHyperlink(
										$this->getLanguageConstant('menu_admins'),
										'chat_admins', 450,
										$this->getLanguageConstant('title_chat_admins'),
										true, false,
										$this->name,
										'admins'
									),
					'link_settings'	=> window_OpenHyperlink(
										$this->getLanguageConstant('menu_settings'),
										'chat_settings', 450,
										$this->getLanguageConstant('title_chat_settings'),
										true, false,
										$this->name,
										'chat_settings'
									)
					);

		$template->registerTagHandler('_channel_list', $this, 'tag_ChannelList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Display channel management form
	 */
	private function chatSettings() {
		$template = new TemplateHandler('channel_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
						'link_new'		=> window_OpenHyperlink(
											$this->getLanguageConstant('add_channel'),
											'chat_settings_add_channel', 400,
											$this->getLanguageConstant('title_channel_add'),
											true, false,
											$this->name,
											'chat_channel_add'
										),
					);

		$template->registerTagHandler('_channel_list', $this, 'tag_ChannelList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Tag handler for user list
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_UserList($tag_params, $children) {
		$manager = ChatUserManager::getInstance();

		$items = $manager->getItems($manager->getFieldNames(), array(), array('username'));

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('users_list_item.xml', $this->path.'templates/');
		}
		$template->setMappedModule($this->name);

		if (count($items) > 0)
			foreach($items as $item) {
				$params = array(
								'id'			=> $item->id,
								'temp'			=> $item->temp,
								'temp_char'		=> $item->temp ? CHAR_CHECKED : CHAR_UNCHECKED,
								'username'		=> $item->username,
								'display_name'	=> $item->display_name,
								'last_active'	=> strtotime($item->last_active),
								'item_change'	=> url_MakeHyperlink(
														$this->getLanguageConstant('change'),
														window_Open(
															'chat_users_change',	// window id
															400,					// width
															$this->getLanguageConstant('title_chat_users_change'), // title
															false, false,
															url_Make(
																'transfer_control',
																'backend_module',
																array('module', $this->name),
																array('backend_action', 'users_change'),
																array('id', $item->id)
															)
														)
													),
								'item_delete'	=> url_MakeHyperlink(
														$this->getLanguageConstant('delete'),
														window_Open(
															'chat_users_delete', 	// window id
															400,				// width
															$this->getLanguageConstant('title_chat_users_delete'), // title
															false, false,
															url_Make(
																'transfer_control',
																'backend_module',
																array('module', $this->name),
																array('backend_action', 'users_delete'),
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
	 * Tag handler for room lists
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_RoomList($tag_params, $children) {
		$manager = ChatRoomManager::getInstance();

		$items = $manager->getItems($manager->getFieldNames(), array(), array('name'));

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('rooms_list_item.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);

		if (count($items) > 0)
			foreach($items as $item) {
				$params = array(
							'id'			=> $item->id,
							'name'			=> $item->name,
							'description'	=> $item->description,
							'limit'			=> $item->limit,
							'protected'		=> !empty($item->password) ? CHAR_CHECKED : CHAR_UNCHECKED,
							'item_change'	=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														'chat_rooms_change', 	// window id
														400,				// width
														$this->getLanguageConstant('title_chat_rooms_change'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'rooms_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> url_MakeHyperlink(
													$this->getLanguageConstant('delete'),
													window_Open(
														'chat_rooms_delete', 	// window id
														400,				// width
														$this->getLanguageConstant('title_chat_rooms_delete'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'rooms_delete'),
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
	 * Tag handler for admin lists
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_AdminList($tag_params, $children) {
		$manager = ChatAdminManager::getInstance();
		$user_manager = ChatUserManager::getInstance();
		$room_manager = ChatRoomManager::getInstance();
		$conditions = array();

		// if room was specified, print only admins for that room
		if (isset($tag_params['room']) && !empty($tag_params['room'])) {
			$room = escape_chars($tag_params['room']);

			if (is_numeric($room))
				$room_id = $room; else
				$room_id = $room_manger->getItemValue('id', array('text_id' => $room));

			$conditions['room'] = $room_id;
		}

		$items = $manager->getItems($manager->getFieldNames(), $conditions, array('user', 'room'));

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('admins_list_item.xml', $this->path.'templates/');
		}

		if (count($items) > 0)
			foreach ($items as $item) {
				$user = $user_manager->getSingleItem(array('display_name'), array('id' => $item->user));
				$room = $room_manager->getSingleItem(array('name'), array('id' => $item->room));
				$params = array(
							'id'			=> $item->id,
							'user'			=> $item->user,
							'room'			=> $item->room,
							'display_name'	=> $user->display_name,
							'room_name'		=> $room->name,
							'item_delete'	=> url_MakeHyperlink(
													$this->getLanguageConstant('delete'),
													window_Open(
														'chat_admins_delete', 	// window id
														400,				// width
														$this->getLanguageConstant('title_chat_admins_delete'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'admins_delete'),
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
	 * Get JSON shaped response for external JavaScript chat
	 */
	private function json_ChatLog() {
		define('_OMIT_STATS', 1);

		$last_id = isset($_REQUEST['last_message_id']) ? fix_id($_REQUEST['last_message_id']) : null;
		$chat_room = isset($_REQUEST['chat_room']) ? fix_id($_REQUEST['chat_room']) : null;
		$manager = ChatLogManager::getInstance();

		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'items'			=> array()
				);

		if (is_null($chat_room)) {
			// we need to have chat room specified
			$result['error'] = true;
			$result['error_message'] = $this->getLanguageConstant('error_invalid_request');
		} else {
			if (is_null($last_id)) {
				// no last message id was specified, get all the logs for specified time

			} else {

			}
		}

		print json_encode($result);
	}

	/**
	 * Ajax handler for checking if username exists
	 */
	private function json_CheckUsername() {
		$manager = ChatUserManager::getInstance();

		$count = $manager->getItems(
								array('id'),
								array('username' => $username)
							);
	}
}


class ChatLogManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('chat_log');

		$this->addProperty('id', 'int');
		$this->addProperty('user', 'int');
		$this->addProperty('room', 'int');
		$this->addProperty('timestamp', 'timestamp');
		$this->addProperty('message', 'varchar');
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


class ChatRoomManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('chat_rooms');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('name', 'ml_varchar');
		$this->addProperty('description', 'ml_text');
		$this->addProperty('limit', 'smallint');
		$this->addProperty('password', 'varchar');
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


class ChatAdminManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('chat_admins');

		$this->addProperty('id', 'int');
		$this->addProperty('user', 'int');
		$this->addProperty('room', 'int');
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


class ChatUserManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('chat_users');

		$this->addProperty('id', 'int');
		$this->addProperty('temp', 'boolean');
		$this->addProperty('username', 'varchar');
		$this->addProperty('password', 'varchar');
		$this->addProperty('display_name', 'varchar');
		$this->addProperty('last_active', 'timestamp');
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
