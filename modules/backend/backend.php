<?php

/**
 * Backend Module
 *
 * This module offers easy to use and user-friednly interface for managing
 * content and framework itself.
 *
 * Author: Mladen Mijatov
 */
use Core\Events;
use Core\Module;
use Core\Cache\Manager as Cache;

define('_BACKEND_SECTION_', 'backend_module');

define('CHAR_CHECKED', '✔');
define('CHAR_UNCHECKED', '');

require_once('units/action.php');
require_once('units/menu_item.php');
require_once('units/session_manager.php');
require_once('units/user_manager.php');


class backend extends Module {
	private static $_instance;

	/**
	 * Menu list
	 * @var array
	 */
	private $menus = array();

	/**
	 * Index of named menu items for faster access
	 * @var array
	 */
	private $named_items = array();

	/**
	 * List of protected modules who can't be disabled or deactivated
	 * @var array
	 */
	private $protected_modules = array('backend', 'head_tag', 'collection');

	/**
	 * Constructor
	 *
	 * @return backend
	 */
	protected function __construct() {
		global $section, $language;

		parent::__construct(__FILE__);

		// create events
		Events::register('backend', 'user-create', 1);
		Events::register('backend', 'user-change', 1);
		Events::register('backend', 'user-delete', 1);
		Events::register('backend', 'user-password-change', 1);

		// load CSS and JScript
		if (ModuleHandler::is_loaded('head_tag') && $section == 'backend') {
			$head_tag = head_tag::getInstance();
			$collection = collection::getInstance();

			$collection->includeScript(collection::JQUERY);
			$collection->includeScript(collection::JQUERY_EVENT_DRAG);
			$collection->includeScript(collection::WINDOW_SYSTEM);

			if ($_SESSION['logged']) {
				$collection->includeScript(collection::JQUERY_EXTENSIONS);
				$collection->includeScript(collection::NOTEBOOK);
				$collection->includeScript(collection::SHOWDOWN);
				$collection->includeScript(collection::TOOLBAR);
			}

			$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/backend.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/backend.js'), 'type'=>'text/javascript'));

		}

		// add admin level menus
		if ($section == 'backend') {
			$system_menu = new backend_MenuItem(
									$this->get_language_constant('menu_system'),
									url_GetFromFilePath($this->path.'images/system.svg'),
									'javascript:void(0);',
									$level=1
								);

			$system_menu->addChild(null, new backend_MenuItem(
									$this->get_language_constant('menu_modules'),
									url_GetFromFilePath($this->path.'images/modules.svg'),
									window_Open( // on click open window
												'system_modules',
												610,
												$this->get_language_constant('title_modules'),
												true, false, // disallow minimize, safety feature
												backend_UrlMake($this->name, 'modules')
											),
									$level=10
								));
			$system_menu->addChild(null, new backend_MenuItem(
									$this->get_language_constant('menu_users'),
									url_GetFromFilePath($this->path.'images/users.svg'),
									window_Open( // on click open window
												'system_users',
												690,
												$this->get_language_constant('title_users_manager'),
												true, false, // disallow minimize, safety feature
												backend_UrlMake($this->name, 'users')
											),
									$level=10
								));
			$system_menu->addChild(null, new backend_MenuItem(
									$this->get_language_constant('menu_clear_cache'),
									url_GetFromFilePath($this->path.'images/clear_cache.svg'),
									window_Open( // on click open window
												'system_clear_cache',
												350,
												$this->get_language_constant('title_clear_cache'),
												true, false, // disallow minimize, safety feature
												backend_UrlMake($this->name, 'clear_cache')
											),
									$level=10
								));
			$system_menu->addSeparator(10);
			$system_menu->addChild(null, new backend_MenuItem(
									$this->get_language_constant('menu_change_password'),
									url_GetFromFilePath($this->path.'images/change_password.svg'),
									window_Open( // on click open window
												'change_password_window',
												350,
												$this->get_language_constant('title_change_password'),
												true, false, // disallow minimize, safety feature
												backend_UrlMake($this->name, 'change_password')
											),
									$level=1
								));
			$system_menu->addChild(null, new backend_MenuItem(
									$this->get_language_constant('menu_logout'),
									url_GetFromFilePath($this->path.'images/logout.svg'),
									window_Open( // on click open window
												'logout_window',
												350,
												$this->get_language_constant('title_logout'),
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
	public function transfer_control($params, $children) {
		// dead lock protection for backend module
		if (isset($params['action']) &&	isset($_REQUEST['module']) &&
		$_REQUEST['module'] == $this->name && $params['action'] == 'transfer_control') {
			// skip module redirect
			$params['backend_action'] = escape_chars($_REQUEST['backend_action']);

			unset($_REQUEST['module']);
			unset($params['action']);

			// if user is not logged, redirect him to a proper place
			if (!isset($_SESSION['logged']) || !$_SESSION['logged']) {
				$session_manager = SessionManager::getInstance($this);
				$session_manager->transfer_control();
				return;
			}

			// fix input parameters
			foreach($_REQUEST as $key => $value)
				$_REQUEST[$key] = $this->utf8_urldecode($_REQUEST[$key]);
		}

		if (isset($params['action']))
			switch ($params['action']) {
				case 'login':
				case 'login_commit':
				case 'logout':
				case 'logout_commit':
				case 'json_login':
				case 'json_logout':
					$session_manager = SessionManager::getInstance();
					$session_manager->transfer_control();
					break;

				case 'verify_account':
					$user_manager = Backend_UserManager::getInstance();
					$user_manager->verifyAccount($params, $children);
					break;

				case 'save_unpriviledged_user_timer':
					$user_manager = Backend_UserManager::getInstance();
					$user_manager->saveTimer();
					break;

				case 'save_unpriviledged_user':
					$user_manager = Backend_UserManager::getInstance();
					$user_manager->saveUnpriviledgedUser($params, $children);
					break;

				case 'save_unpriviledged_password':
					$user_manager = Backend_UserManager::getInstance();
					$user_manager->saveUnpriviledgedPassword($params, $children);
					break;

				case 'password_recovery':
					$user_manager = Backend_UserManager::getInstance();
					$user_manager->recoverPasswordByEmail($params, $children);
					break;

				case 'password_recovery_save':
					$user_manager = Backend_UserManager::getInstance();
					$user_manager->saveRecoveredPassword($params, $children);
					break;

				case 'draw_menu':
					$this->drawCompleteMenu();
					break;

				case 'transfer_control':
					// if user is not logged, redirect him to a proper place
					if (!isset($_SESSION['logged']) || !$_SESSION['logged']) {
						$session_manager = SessionManager::getInstance($this);
						$session_manager->transfer_control();
						return;
					}

					// fix input parameters
					foreach($_REQUEST as $key => $value)
						$_REQUEST[$key] = $this->utf8_urldecode($_REQUEST[$key]);

					// transfer control
					$action = escape_chars($_REQUEST['backend_action']);
					$module_name = escape_chars($_REQUEST['module']);
					$params['backend_action'] = $action;

					// add sub-action if specified
					if (isset($_REQUEST['sub_action']))
						$params['sub_action'] = escape_chars($_REQUEST['sub_action']);

					if (ModuleHandler::is_loaded($module_name)) {
						$module = call_user_func(array($module_name, 'getInstance'));
						$module->transfer_control($params, $children);
					}
					break;

				default:
					// draw main backend as default
					$this->showBackend();
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

				case 'clear_cache':
					$this->clearCache();
					break;

				// ---
				case 'users':
				case 'users_create':
				case 'users_change':
				case 'users_save':
				case 'users_delete':
				case 'users_delete_commit':
				case 'change_password':
				case 'save_password':
				case 'email_templates':
				case 'email_templates_save':
					$user_manager = Backend_UserManager::getInstance();
					$user_manager->transfer_control();
					break;

				// ---
				case 'logout':
				case 'logout_commit':
					$session_manager = SessionManager::getInstance($this);
					$session_manager->transfer_control();
					break;
			}
	}

	/**
	 * Redefine abstract methods
	 */
	public function on_init() {
		$this->save_setting('template_verify', '');
		$this->save_setting('template_recovery', '');
		$this->save_setting('require_verified', 1);
	}

	public function on_disable() {
	}

	/**
	 * Save template selection.
	 *
	 * @param string $verify
	 * @param string $recovery
	 */
	public function saveTemplateSelection($verify, $recovery) {
		$this->save_setting('template_verify', $verify);
		$this->save_setting('template_recovery', $recovery);
	}

	/**
	 * Parse main backend template.
	 */
	private function showBackend() {
		$template = new TemplateHandler('main.xml', $this->path.'templates/');

		$template->setMappedModule($this->name);
		$template->registerTagHandler('cms:main_menu', $this, 'tag_MainMenu');

		$params = array();

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Adds menu to draw list
	 *
	 * @param string $name
	 * @param resource $menu
	 */
	public function addMenu($name, $menu) {
		$this->menus[$name] = $menu;

		if (!is_null($name))
			$this->registerNamedItem($name, $menu);
	}

	/**
	 * Register named item for easier retrieval later
	 *
	 * @param string $name
	 * @param object $menu
	 */
	public function registerNamedItem($name, $menu) {
		$this->named_items[$name] = $menu;
	}

	/**
	 * Get menu assigned to specified name
	 * @param string $name
	 */
	public function getMenu($name) {
		if (array_key_exists($name, $this->named_items))
			$result = $this->named_items[$name]; else
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

		$template->registerTagHandler('_module_list', $this, 'tag_ModuleList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Activates specified module
	 */
	private function activateModule() {
		$module_name = escape_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = ModuleManager::getInstance();
			$manager->update_items(
							array('active' => 1),
							array('name' => $module_name)
						);
			$message = $this->get_language_constant('message_module_activated');

		} else {
			$message = $this->get_language_constant('message_module_protected');
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
		$module_name = escape_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = ModuleManager::getInstance();
			$manager->update_items(
							array('active' => 0),
							array('name' => $module_name)
						);
			$message = $this->get_language_constant('message_module_deactivated');
		} else {
			// protected module
			$message = $this->get_language_constant('message_module_protected');
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
		$module_name = escape_chars($_REQUEST['module_name']);

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->get_language_constant('message_module_initialise'),
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
					'yes_text'		=> $this->get_language_constant("initialise"),
					'no_action'		=> window_Close($this->name.'_module_dialog'),
					'no_text'		=> $this->get_language_constant("cancel"),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Initialise and activate module
	 */
	private function initialiseModule_Commit() {
		$module_name = escape_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = ModuleManager::getInstance();
			$max_order = $manager->get_item_value(
										'MAX(`order`)',
										array('preload' => 0)
									);

			if (is_null($max_order)) $max_order = -1;

			$manager->insert_item(
							array(
								'order'		=> $max_order + 1,
								'name'		=> $module_name,
								'preload'	=> 0,
								'active'	=> 1
							));

			$handler = ModuleHandler::getInstance();
			$module = $handler->loadModule($module_name);

			if (!is_null($module)) {
				$module->on_init();
				$message = $this->get_language_constant('message_module_initialised');
			}

		} else {
			$message = $this->get_language_constant('message_module_protected');
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
		$module_name = escape_chars($_REQUEST['module_name']);

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->get_language_constant('message_module_disable'),
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
					'yes_text'		=> $this->get_language_constant("disable"),
					'no_action'		=> window_Close($this->name.'_module_dialog'),
					'no_text'		=> $this->get_language_constant("cancel"),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Disable specified module and remove it's settings
	 */
	private function disableModule_Commit() {
		$module_name = escape_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = ModuleManager::getInstance();
			$max_order = $manager->get_item_value(
										'MAX(`order`)',
										array('preload' => 0)
									);

			if (is_null($max_order)) $max_order = -1;

			$manager->delete_items(array('name' => $module_name));

			if (ModuleHandler::is_loaded($module_name)) {
				$module = call_user_func(array($module_name, 'getInstance'));
				$module->on_disable();

				$message = $this->get_language_constant('message_module_disabled');

			} else {
				$message = $this->get_language_constant('message_module_not_active');
			}

		} else {
			$message = $this->get_language_constant('message_module_protected');
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
	 * Clear cache.
	 */
	private function clearCache() {
		// clear cache
		$cache = Cache::getInstance();
		$cache->clearCache();

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->get_language_constant('message_cleared_cache'),
					'action'		=> window_Close('system_clear_cache')
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
		global $module_path, $system_module_path;

		$list = array();
		$raw_list = $this->getModuleList();
		$manager = ModuleManager::getInstance();

		$modules_in_use = $manager->get_items(
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
			// locate module icon
			$icon_file = null;
			if (file_exists(_BASEPATH.'/'.$module_path.$name))
				$icon_file = _BASEPATH.'/'.$module_path.$name.'/images/icon.svg'; else
				$icon_file = _BASEPATH.'/'.$system_module_path.$name.'/images/icon.svg';

			if (file_exists($icon_file))
				$icon = url_GetFromFilePath($icon_file); else
				$icon = url_GetFromFilePath($this->path.'images/modules.svg');

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
													$this->get_language_constant('activate'),
													window_Open(
														$this->name.'_module_dialog',	// window id
														300,							// width
														$this->get_language_constant('title_module_activate'), // title
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
													$this->get_language_constant('deactivate'),
													window_Open(
														$this->name.'_module_dialog',	// window id
														300,							// width
														$this->get_language_constant('title_module_deactivate'), // title
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
													$this->get_language_constant('initialise'),
													window_Open(
														$this->name.'_module_dialog',	// window id
														300,							// width
														$this->get_language_constant('title_module_initialise'), // title
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
													$this->get_language_constant('disable'),
													window_Open(
														$this->name.'_module_dialog',	// window id
														300,							// width
														$this->get_language_constant('title_module_disable'), // title
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
		global $module_path, $system_module_path;

		$result = array();

		// load site module list
		if (file_exists($module_path)) {
			$directory = dir($module_path);

			while (false !== ($entry = $directory->read()))
				if (is_dir($directory->path.DIRECTORY_SEPARATOR.$entry) && $entry[0] != '.' && $entry[0] != '_')
					$result[] = $entry;

			$directory->close();
		}

		// load system module list
		if (file_exists($system_module_path)) {
			$directory = dir($system_module_path);

			while (false !== ($entry = $directory->read()))
				if (is_dir($directory->path.DIRECTORY_SEPARATOR.$entry) && $entry[0] != '.' && $entry[0] != '_')
					$result[] = $entry;

			$directory->close();
		}

		return $result;
	}

	/**
	 * Draws all menus for current level
	 */
	public function tag_MainMenu($tag_params, $children) {
		echo '<ul id="navigation">';

		foreach ($this->menus as $item)
			$item->drawItem();

		echo '</ul>';
	}

	/**
	 * This function decodes characters encoded by JavaScript
	 *
	 * @param string/array $str
	 * @return string/array
	 */
	private function utf8_urldecode($str) {
		$result = '';

		if (!is_array($str)) {
			$str = preg_replace("/%u([0-9a-f]{3,4})/i","&#x\\1;", urldecode($str));
			$result = html_entity_decode($str, null, 'UTF-8');;

		} else {
			$result = array();
			foreach ($str as $index => $value)
				$result[$index] = $this->utf8_urldecode($value);
		}

		return $result;
	}
}

?>
