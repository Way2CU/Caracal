<?php

/**
 * Main backend framework class
 *
 * @author MeanEYE[rcf]
 */

define('_BACKEND_SECTION_', 'backend_module');
define('_BACKEND_PATH_', dirname(__FILE__));

define('CHAR_CHECKED', '✔');
define('CHAR_UNCHECKED', '');

require_once('units/menu_item.php');

class backend extends Module {
	private static $_instance;

	/**
	 * Menu list
	 * @var array
	 */
	private $menus = array();

	/**
	 * List of protected modules who can't be disabled or deactivated
	 * @var array
	 */
	private $protected_modules = array('backend', 'head_tag', 'captcha');

	/**
	 * Constructor
	 *
	 * @return backend
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// load CSS and JScript
		if (class_exists('head_tag')) {
			$head_tag = head_tag::getInstance();

			// always load jquery
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/jquery.js'), 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/jquery.event.drag.js'), 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/jquery.extensions.js'), 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/animation_chain.js'), 'type'=>'text/javascript'));

			if ($section == $this->name) {
				$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/backend.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));

				if (MainLanguageHandler::getInstance()->isRTL()) {
					$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/window_rtl.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
					$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/backend_rtl.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
				}

				$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/toolbar_api.js'), 'type'=>'text/javascript'));
				$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/window_system.js'), 'type'=>'text/javascript'));
			}
		}

		// add admin level menus
		if ($section == 'backend') {
			$system_menu = new backend_MenuItem(
									$this->getLanguageConstant('menu_system'),
									url_GetFromFilePath($this->path.'images/icons/16/system.png'),
									'javascript:void(0);',
									$level=5
								);

			$system_menu->addChild(null, new backend_MenuItem(
									$this->getLanguageConstant('menu_modules'),
									url_GetFromFilePath($this->path.'images/icons/16/modules.png'),
									window_Open( // on click open window
												'system_modules',
												610,
												$this->getLanguageConstant('title_modules'),
												true, false, // disallow minimize, safety feature
												backend_UrlMake($this->name, 'modules')
											),
									$level=10
								));
			$system_menu->addChild(null, new backend_MenuItem(
									$this->getLanguageConstant('menu_users'),
									url_GetFromFilePath($this->path.'images/icons/16/users.png'),
									'javascript:void(0)',
									$level=10
								));
			$system_menu->addSeparator(10);
			$system_menu->addChild(null, new backend_MenuItem(
									$this->getLanguageConstant('menu_change_password'),
									url_GetFromFilePath($this->path.'images/icons/16/change_password.png'),
									window_Open( // on click open window
												'change_password_window',
												350,
												$this->getLanguageConstant('title_change_password'),
												true, false, // disallow minimize, safety feature
												backend_UrlMake($this->name, 'change_password')
											),
									$level=1
								));
			$system_menu->addChild(null, new backend_MenuItem(
									$this->getLanguageConstant('menu_logout'),
									url_GetFromFilePath($this->path.'images/icons/16/logout.png'),
									window_Open( // on click open window
												'logout_window',
												350,
												$this->getLanguageConstant('title_logout'),
												true, false, // disallow minimize, safety feature
												backend_UrlMake($this->name, 'logout')
											),
									$level=1
								));

			$this->addMenu($this->name, $system_menu);
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
	public function transferControl($params = array(), $children=array()) {
		// user is not logged, redirect him to a proper place
		if (!isset($_SESSION['logged']) || !$_SESSION['logged']) {
			$session_manager = new SessionManager($this);
			$session_manager->transferControl();
			return;
		}

		// dead lock protection for backend module
		if (isset($params['action']) &&	isset($_REQUEST['module']) &&
		$_REQUEST['module'] == $this->name && $params['action'] == 'transfer_control') {
			$params['backend_action'] = fix_chars($_REQUEST['backend_action']);

			unset($_REQUEST['module']);
			unset($params['action']);
		}

		if (isset($params['action']))
			switch ($params['action']) {
				case 'draw_menu':
					$this->drawCompleteMenu();
					break;

				case 'transfer_control':
					// fix input parameters
					foreach($_REQUEST as $key => $value)
						$_REQUEST[$key] = $this->utf8_urldecode($_REQUEST[$key]);

					// transfer control
					$action = fix_chars($_REQUEST['backend_action']);
					$module_name = fix_chars($_REQUEST['module']);
					$params['backend_action'] = $action;

					if (class_exists($module_name)) {
						$module = call_user_func(array($module_name, 'getInstance'));
						$module->transferControl($params, $children);
					}
					break;
			}

		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'modules':
					$this->showModules();
					break;

				case 'module_activate':
					$this->activateModule();
					break;

				case 'module_deactivate':
					$this->deactivateModule();
					break;

				case 'module_initialise':
					$this->initialiseModule();
					break;

				case 'module_initialise_commit':
					$this->initialiseModule_Commit();
					break;

				case 'module_disable':
					$this->disableModule();
					break;

				case 'module_disable_commit':
					$this->disableModule_Commit();
					break;

				// ---

				case 'users':
					break;

				// ---
				case 'logout':
				case 'logout_commit':
				case 'change_password':
				case 'save_password':
					$session_manager = new SessionManager($this);
					$session_manager->transferControl();
					break;
			}
	}

	/**
	 * Adds menu to draw list
	 *
	 * @param string $name
	 * @param resource $menu
	 */
	public function addMenu($name, $menu) {
		$this->menus[$name] = $menu;
	}

	/**
	 * Get menu assigned to specified name
	 * @param string $name
	 */
	public function getMenu($name) {
		if (array_key_exists($name, $this->menus))
			$result = $this->menus[$name]; else
			$result = null;

		return $result;
	}

	/**
	 * Display
	 */
	private function showModules() {
		$template = new TemplateHandler('modules_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array();

		$template->registerTagHandler('_module_list', &$this, 'tag_ModuleList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Activates specified module
	 */
	private function activateModule() {
		$module_name = fix_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = ModuleManager::getInstance();
			$manager->updateData(
							array('active' => 1),
							array('name' => $module_name)
						);
			$message = $this->getLanguageConstant('message_module_activated');

		} else {
			$message = $this->getLanguageConstant('message_module_protected');
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $message,
					'action'	=> window_Close($this->name.'_module_dialog').";".window_ReloadContent('system_modules')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Deactivates specified module
	 */
	private function deactivateModule() {
		$module_name = fix_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = ModuleManager::getInstance();
			$manager->updateData(
							array('active' => 0),
							array('name' => $module_name)
						);
			$message = $this->getLanguageConstant('message_module_deactivated');
		} else {
			// protected module
			$message = $this->getLanguageConstant('message_module_protected');
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $message,
					'action'		=> window_Close($this->name.'_module_dialog').";".window_ReloadContent('system_modules')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Print confirmation form before initialising module
	 */
	private function initialiseModule() {
		$module_name = fix_chars($_REQUEST['module_name']);

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant('message_module_initialise'),
					'name'			=> $module_name,
					'yes_action'	=> window_LoadContent(
											$this->name.'_module_dialog',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'module_initialise_commit'),
												array('module_name', $module_name)
											)
										),
					'yes_text'		=> $this->getLanguageConstant("initialise"),
					'no_action'		=> window_Close($this->name.'_module_dialog'),
					'no_text'		=> $this->getLanguageConstant("cancel"),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Initialise and activate module
	 */
	private function initialiseModule_Commit() {
		global $ModuleHandler;

		$module_name = fix_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = ModuleManager::getInstance();
			$max_order = $manager->getItemValue(
										"MAX(`order`)",
										array('preload' => 0)
									);

			if (is_null($max_order)) $max_order = -1;

			$manager->insertData(
							array(
								'order'		=> $max_order + 1,
								'name'		=> $module_name,
								'preload'	=> 0,
								'active'	=> 1
							));

			$module = $ModuleHandler->_loadModule($module_name);
			$module->onInit();
			$message = $this->getLanguageConstant('message_module_initialised');

		} else {
			$message = $this->getLanguageConstant('message_module_protected');
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $message,
					'action'		=> window_Close($this->name.'_module_dialog').";".window_ReloadContent('system_modules')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Print confirmation dialog before disabling module
	 */
	private function disableModule() {
		$module_name = fix_chars($_REQUEST['module_name']);

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant('message_module_disable'),
					'name'			=> $module_name,
					'yes_action'	=> window_LoadContent(
											$this->name.'_module_dialog',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'module_disable_commit'),
												array('module_name', $module_name)
											)
										),
					'yes_text'		=> $this->getLanguageConstant("disable"),
					'no_action'		=> window_Close($this->name.'_module_dialog'),
					'no_text'		=> $this->getLanguageConstant("cancel"),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Disable specified module and remove it's settings
	 */
	private function disableModule_Commit() {
		global $ModuleHandler;

		$module_name = fix_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = ModuleManager::getInstance();
			$max_order = $manager->getItemValue(
										"MAX(`order`)",
										array('preload' => 0)
									);

			if (is_null($max_order)) $max_order = -1;

			$manager->deleteData(array('name' => $module_name));

			if (class_exists($module_name)) {
				$module = call_user_func(array($module_name, 'getInstance'));
				$module->onDisable();

				$message = $this->getLanguageConstant('message_module_disabled');
			} else {
				$message = $this->getLanguageConstant('message_module_not_active');
			}

		} else {
			$message = $this->getLanguageConstant('message_module_protected');
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $message,
					'action'		=> window_Close($this->name.'_module_dialog').";".window_ReloadContent('system_modules')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Handle tag _module_list used to display list of all modules on the system
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function tag_ModuleList($params, $children) {
		global $module_path;

		$list = array();
		$raw_list = $this->getModuleList();
		$manager = ModuleManager::getInstance();

		$modules_in_use = $manager->getItems(
											array('id', 'order', 'name', 'preload', 'active'),
											array(),
											array('preload', 'order')
										);

		// add modules from database
		foreach($modules_in_use as $module) {
			if (in_array($module->name, $raw_list)) {
				// module in database exists on disk
				if ($module->active)
					$list[$module->name] = array('status' 	=> 'active'); else
					$list[$module->name] = array('status'	=> 'inactive');

			} else {
				// module does not exist on disk
				$list[$module->name] = array('status'	=> 'missing');
			}

			$list[$module->name]['active'] = $module->active;
			$list[$module->name]['preload'] = $module->preload;
			$list[$module->name]['order'] = $module->order;
		}

		// add missing modules available on drive
		foreach($raw_list as $module_name) {
			if (!array_key_exists($module_name, $list))
				$list[$module_name] = array(
										'status'	=> 'not_initialized',
										'active'	=> 0,
										'preload'	=> 0,
										'order'		=> ''
									);
		}

		$template = new TemplateHandler(
							isset($params['template']) ? $params['template'] : 'module.xml',
							$this->path.'templates/'
						);

		$template->setMappedModule($this->name);

		foreach($list as $name => $definition) {
			$icon_file = _BASEPATH.'/'.$module_path.$name.'/images/icon.png';

			if (file_exists($icon_file))
				$icon = url_GetFromFilePath($icon_file); else
				$icon = url_GetFromFilePath($this->path.'images/icons/16/modules.png');

			$params = array(
							'name'				=> $name,
							'icon'				=> $icon,
							'status'			=> $definition['status'],
							'active'			=> $definition['active'],
							'active_symbol'		=> $definition['active'] ? CHAR_CHECKED : CHAR_UNCHECKED,
							'preload'			=> $definition['preload'],
							'preload_symbol'	=> $definition['preload'] ? CHAR_CHECKED : CHAR_UNCHECKED,
							'order'				=> $definition['order'],
							'item_activate'		=> url_MakeHyperlink(
													$this->getLanguageConstant('activate'),
													window_Open(
														$this->name.'_module_dialog',	// window id
														300,							// width
														$this->getLanguageConstant('title_module_activate'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'module_activate'),
															array('module_name', $name)
														)
													)
												),
							'item_deactivate'		=> url_MakeHyperlink(
													$this->getLanguageConstant('deactivate'),
													window_Open(
														$this->name.'_module_dialog',	// window id
														300,							// width
														$this->getLanguageConstant('title_module_deactivate'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'module_deactivate'),
															array('module_name', $name)
														)
													)
												),
							'item_initialise'		=> url_MakeHyperlink(
													$this->getLanguageConstant('initialise'),
													window_Open(
														$this->name.'_module_dialog',	// window id
														300,							// width
														$this->getLanguageConstant('title_module_initialise'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'module_initialise'),
															array('module_name', $name)
														)
													)
												),
							'item_disable'		=> url_MakeHyperlink(
													$this->getLanguageConstant('disable'),
													window_Open(
														$this->name.'_module_dialog',	// window id
														300,							// width
														$this->getLanguageConstant('title_module_disable'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'module_disable'),
															array('module_name', $name)
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
	 * Get list of modules available on the system
	 *
	 * @return array
	 */
	public function getModuleList() {
		global $module_path;

		$result = array();
		$directory = dir($module_path);

		while (false !== ($entry = $directory->read()))
			if (is_dir($directory->path.DIRECTORY_SEPARATOR.$entry) && $entry[0] != '.' && $entry[0] != '_')
				$result[] = $entry;

		$directory->close();

		return $result;
	}

	/**
	 * Draws all menus for current level
	 */
	private function drawCompleteMenu() {
		echo '<ul id="navigation">';

		foreach ($this->menus as $item)
			$item->drawItem();

		echo '</ul>';
	}

	/**
	 * This function decodes characters encoded by JavaScript
	 *
	 * @param string $str
	 * @return string
	 */
	private function utf8_urldecode($str) {
		$str = preg_replace("/%u([0-9a-f]{3,4})/i","&#x\\1;", urldecode($str));
		return html_entity_decode($str, null, 'UTF-8');;
	}
}

class SessionManager {
	private static $SALT = '_web_engine: SALT1.618: ';

	/**
	 * Parent module (backend)
	 * @var resource
	 */
	var $parent;

	/**
	 * Constructor
	 */
	public function __construct($parent) {
		$this->parent = $parent;
	}

	/**
	 * Transfer control to this object
	 */
	public function transferControl() {
		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;

		if (!is_null($action) && $action == 'transfer_control')
			$action = $_REQUEST['backend_action'];

		switch($action) {
			case 'login_commit':
				$this->login_commit();
				break;

			case 'logout':
				$this->logout();
				break;

			case 'logout_commit':
				$this->logout_commit();
				break;

			case 'change_password':
				$this->changePassword();
				break;

			case 'save_password':
				$this->savePassword();
				break;

			default:
				$_SESSION['redirect_url'] = $_SERVER['REQUEST_URI']; // grab url for later redirection
				$this->login();
				break;
		}
	}

	/**
	 * Show login form
	 *
	 * @param string $message
	 */
	private function login($message='') {
		$manager = LoginRetryManager::getInstance();
		$show_captcha = false;

		// remove old logs
		$manager->deleteData(array(
								'day'	=> array(
											'operator'	=> '<>',
											'value'		=> date('j')
										)
								));

		// try to get retries log
		$retry_log = $manager->getSingleItem(
										$manager->getFieldNames(),
										array('address' => $_SERVER['REMOTE_ADDR'])
									);

		// check if user has more than 3 failed atempts
		if (is_object($retry_log))
			$show_captcha = $retry_log->count > 3;

		// create template and show login form
		$template = new TemplateHandler('session_login.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
					'show_captcha'	=> $show_captcha,
					'username'		=> isset($_REQUEST['username']) ? fix_chars($_REQUEST['username']) : '',
					'image'			=> url_GetFromFilePath($this->parent->path.'images/icons/login.png'),
					'message'		=> $message
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Perform login
	 */
	private function login_commit() {
		$captcha_ok = false;
		$username = fix_chars($_REQUEST['username']);
		$password = fix_chars($_REQUEST['password']);
		$captcha = isset($_REQUEST['captcha']) ? fix_chars($_REQUEST['captcha']) : '';

		$manager = AdministratorManager::getInstance();
		$retry_manager = LoginRetryManager::getInstance();

		$user = $manager->getSingleItem(
									$manager->getFieldNames(),
									array(
										'username'	=> $username,
										'password'	=> $password
									));

		$retry_log = $retry_manager->getSingleItem(
									$retry_manager->getFieldNames(),
									array(
										'address' 	=> $_SERVER['REMOTE_ADDR']
									));

		// check captcha
		if (is_object($retry_log) && $retry_log->count > 3) {
			// on purpose we make a separate condition, if captcha
			// module is not loaded, block IP address for one day
			if (class_exists('captcha')) {
				$captcha_module = captcha::getInstance();

				$captcha_ok = $captcha_module->isCaptchaValid($captcha);
				$captcha_module->resetCaptcha();
			}
		} else {
			$captcha_ok = true;
		}

		// check user data
		if (is_object($user) && $captcha_ok) {
			// remove login retries
			$retry_manager->deleteData(array('address' => $_SERVER['REMOTE_ADDR']));

			// set session variables
			$_SESSION['uid'] = $user->id;
			$_SESSION['logged'] = true;
			$_SESSION['level'] = $user->level;
			$_SESSION['username'] = $user->username;
			$_SESSION['fullname'] = $user->fullname;

			// check if we need to make redirect URL
			if (isset($_SESSION['redirect_url']))
				$url = url_SetRefresh($_SESSION['redirect_url'], 4); else
				$url = url_SetRefresh(url_Make('', $this->parent->name), 4);

			// get message
			$message = $this->parent->getLanguageConstant('message_login_ok');

			// create template and show login form
			$template = new TemplateHandler('session_message.xml', $this->parent->path.'templates/');
			$template->setMappedModule($this->parent->name);

			$params = array(
						'message'		=> $message,
						'redirect_url'	=> $url
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();

		} else {
			// user is not logged in properly, increase fail
			// counter and present login window with message
			if (is_object($retry_log)) {
				// don't allow counter to go over 10
				$count = ($retry_log->count < 10) ? $retry_log->count+1 : 10;

				$retry_manager->updateData(
									array('count' => $count),
									array('id' => $retry_log->id)
								);

			} else {
				$retry_manager->insertData(array(
										'day'		=> date('j'),
										'address'	=> $_SERVER['REMOTE_ADDR'],
										'count'		=> 1
									));
			}

			$message = $this->parent->getLanguageConstant('message_login_error');
			$this->login($message);
		}
	}

	/**
	 * Present confirmation dialog before logout
	 */
	private function logout() {
		$template = new TemplateHandler('confirmation.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
					'message'		=> $this->parent->getLanguageConstant('message_logout'),
					'name'			=> '',
					'yes_text'		=> $this->parent->getLanguageConstant('logout'),
					'no_text'		=> $this->parent->getLanguageConstant('cancel'),
					'yes_action'	=> window_LoadContent(
											'logout_window',
											backend_UrlMake($this->parent->name, 'logout_commit')
										),
					'no_action'		=> window_Close('logout_window')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Perform logout procedure
	 */
	private function logout_commit() {
		// kill session variables
		unset($_SESSION['uid']);
		unset($_SESSION['logged']);
		unset($_SESSION['level']);
		unset($_SESSION['username']);
		unset($_SESSION['fullname']);

		// get message
		$message = $this->parent->getLanguageConstant('message_logout_ok');

		// get url
		$url = url_SetRefresh(url_Make('', $this->parent->name), 2);

		// load template and show the message
		$template = new TemplateHandler('session_message.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
					'message'		=> $message,
					'redirect_url'	=> $url
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show dialog for changing password
	 */
	private function changePassword() {
		$template = new TemplateHandler('change_password.xml', $this->parent->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->parent->name, 'save_password'),
					'cancel_action'	=> window_Close('change_password_window')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Salt and save password
	 */
	private function savePassword() {
		$old_password = md5(SessionManager::SALT.$_REQUEST['old_password']);
		$new_password = md5(SessionManager::SALE.$_REQUEST['new_password']);
	}
}

?>
