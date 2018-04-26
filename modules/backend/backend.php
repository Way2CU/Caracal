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
use Core\Exports\File;
use Core\Cache\Manager as Cache;
use Modules\Backend\OrderEditor as OrderEditor;

define('_BACKEND_SECTION_', 'backend_module');

define('CHAR_CHECKED', '<svg class="check-mark"><use xlink:href="#icon-checkmark"/></svg>');
define('CHAR_UNCHECKED', '');

require_once('units/action.php');
require_once('units/menu_item.php');
require_once('units/session_manager.php');
require_once('units/user_manager.php');
require_once('units/order_editor.php');


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
		Events::register('backend', 'sprite-include');
		Events::register('backend', 'add-tags');
		Events::register('backend', 'add-menu-items');

		// connect events
		Events::connect('backend', 'add-tags', 'add_tags', $this);
		Events::connect('backend', 'add-menu-items', 'add_menu_items', $this);
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
	 * Create new order editor for backend.
	 *
	 * @param object $manager_instance
	 */
	public static function get_order_editor($manager_instance) {
		$parent = self::get_instance();
		$result = new OrderEditor($parent, $manager_instance);

		return $result;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transfer_control($params, $children) {
		global $content_security_policy, $frame_options;

		if (isset($params['action']))
			switch ($params['action']) {
				case 'login':
				case 'login_commit':
				case 'logout':
				case 'logout_commit':
				case 'json_login':
				case 'json_logout':
					$session_manager = SessionManager::get_instance();
					$session_manager->transfer_control();
					break;

				case 'verify_account':
					$user_manager = Backend_UserManager::get_instance();
					$user_manager->verifyAccount($params, $children);
					break;

				case 'save_unpriviledged_user_timer':
					$user_manager = Backend_UserManager::get_instance();
					$user_manager->saveTimer();
					break;

				case 'save_unpriviledged_user':
					$user_manager = Backend_UserManager::get_instance();
					$user_manager->saveUnpriviledgedUser($params, $children);
					break;

				case 'save_unpriviledged_password':
					$user_manager = Backend_UserManager::get_instance();
					$user_manager->saveUnpriviledgedPassword($params, $children);
					break;

				case 'password_recovery':
					$user_manager = Backend_UserManager::get_instance();
					$user_manager->recoverPasswordByEmail($params, $children);
					break;

				case 'password_recovery_save':
					$user_manager = Backend_UserManager::get_instance();
					$user_manager->saveRecoveredPassword($params, $children);
					break;

				/**
				 * Handle transfering flow control to other modules. As `SessionManager` by default sets action
				 * to be `transfer_control` when `backend_module` is passed for section, this is the first piece
				 * of code that gets called before `backend_action` parameter is set.
				 *
				 * Once credentials are verified `$params` variable is updated with all the required information
				 * and then passed on to `module` specified in request parameters.
				 *
				 * Note: We prevent calling `transfer_control` again if the module is `backend` to avoid dead
				 * loop. Instead we just set `backend_action` and let the switch after current one handle the
				 * request.
				 */
				case 'transfer_control':
					// if user is not logged, let session manager handle him
					if (!$_SESSION['logged']) {
						$session_manager = SessionManager::get_instance($this);
						$session_manager->transfer_control();
						return;
					}

					// transfer control
					$action = escape_chars($_REQUEST['backend_action']);
					$module_name = escape_chars($_REQUEST['module']);
					$params['backend_action'] = $action;

					// add sub-action if specified
					if (isset($_REQUEST['sub_action']))
						$params['sub_action'] = escape_chars($_REQUEST['sub_action']);

					// transfer control to other modules
					if (ModuleHandler::is_loaded($module_name) && $module_name != $this->name) {
						if (!(isset($_REQUEST['enclose']) && !empty($_REQUEST['enclose']))) {
							// transfer control to the module in regular way
							$module = call_user_func(array($module_name, 'get_instance'));
							$module->transfer_control($params, $children);

						} else {
							// add extra parameters
							$params['module'] = $module_name;
							$params['source'] = urlencode($_REQUEST['enclose']);

							// configure security options
							$domain = parse_url($_REQUEST['enclose'], PHP_URL_HOST);
							$entries = explode(';', $content_security_policy);
							$entries []= 'style-src '.$domain;
							$content_security_policy = join(';', $entries);
							$frame_options = 'ALLOW-FROM '.$domain;

							// call for modules to add required tags
							if (ModuleHandler::is_loaded('head_tag'))
								Events::trigger('backend', 'add-tags');

							// enclose module content in standalone template
							$template = new TemplateHandler('enclosed_window.xml', $this->path.'templates/');
							$template->set_top_level(true);
							$template->register_tag_handler('cms:sprites', $this, 'tag_Sprites');
							$template->set_mapped_module($this->name);
							$template->set_local_params($params);
							$template->restore_xml();
							$template->parse();
						}
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

				// data import and export
				case 'exports':
					$this->exports();
					break;

				case 'import_options':
					$this->import_options();
					break;

				case 'import_commit':
					$this->import_commit();
					break;

				case 'export_options':
					$this->export_options();
					break;

				case 'export_commit':
					$this->export_commit();
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
					$user_manager = Backend_UserManager::get_instance();
					$user_manager->transfer_control();
					break;

				// ---
				case 'logout':
				case 'logout_commit':
					$session_manager = SessionManager::get_instance($this);
					$session_manager->transfer_control();
					break;
			}
	}

	/**
	 * Redefine abstract methods
	 */
	public function initialize() {
		$this->save_setting('template_verify', '');
		$this->save_setting('template_recovery', '');
		$this->save_setting('require_verified', 1);
	}

	public function cleanup() {
	}

	/**
	 * Add backend menu items.
	 */
	public function add_menu_items() {
		$system_menu = new backend_MenuItem(
								$this->get_language_constant('menu_system'),
								$this->path.'images/system.svg',
								'javascript:void(0);',
								$level=1
							);

		$system_menu->addChild(null, new backend_MenuItem(
								$this->get_language_constant('menu_modules'),
								$this->path.'images/modules.svg',
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
								$this->path.'images/users.svg',
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
								$this->path.'images/clear_cache.svg',
								window_Open( // on click open window
											'system_clear_cache',
											350,
											$this->get_language_constant('title_clear_cache'),
											true, false, // disallow minimize, safety feature
											backend_UrlMake($this->name, 'clear_cache')
										),
								$level=10
							));
		$system_menu->addChild(null, new backend_MenuItem(
								$this->get_language_constant('menu_exports'),
								$this->path.'images/exports.svg',
								window_Open( // on click open window
											'system_exports',
											500,
											$this->get_language_constant('title_exports'),
											true, false, // disallow minimize, safety feature
											backend_UrlMake($this->name, 'exports')
										),
								$level=10
							));

		$system_menu->addSeparator(10);
		$system_menu->addChild(null, new backend_MenuItem(
								$this->get_language_constant('menu_change_password'),
								$this->path.'images/change_password.svg',
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
								$this->path.'images/logout.svg',
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

	/**
	 * Add required scripts and styles.
	 */
	public function add_tags() {
		$head_tag = head_tag::get_instance();
		$collection = collection::get_instance();

		// include scripts from collection module
		$collection->includeScript(collection::JQUERY);
		$collection->includeScript(collection::JQUERY_EVENT_DRAG);
		$collection->includeScript(collection::JQUERY_EXTENSIONS);
		$collection->includeScript(collection::SHOWDOWN);
		$collection->includeScript(collection::COMMUNICATOR);

		// add styles
		$head_tag->add_tag('link', array(
				'href' => URL::from_file_path($this->path.'include/main.less'),
				'rel'  => 'stylesheet/less',
				'type' => 'text/css'
			));

		// add scripts
		$scripts = array(
			'order_editor.js', 'toolbar.js', 'markdown.js', 'notebook.js',
			'window_system.js', 'window.js', 'dialog.js'
			);
		foreach ($scripts as $script)
			$head_tag->add_tag('script', array(
					'src'  => URL::from_file_path($this->path.'include/'.$script),
					'type' => 'text/javascript'
				));
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
		// call for modules to add required tags
		if (ModuleHandler::is_loaded('head_tag'))
			Events::trigger('backend', 'add-tags');

		// create template parser
		$template = new TemplateHandler('main.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:menu_items', $this, 'tag_MainMenu');
		$template->register_tag_handler('cms:sprites', $this, 'tag_Sprites');

		// prepare parameters
		$params = array();

		// render template
		$template->restore_xml();
		$template->set_local_params($params);
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
		$template->set_mapped_module($this->name);

		$params = array();

		$template->register_tag_handler('cms:module_list', $this, 'tag_ModuleList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Activates specified module
	 */
	private function activateModule() {
		$module_name = escape_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = ModuleManager::get_instance();
			$manager->update_items(
							array('active' => 1),
							array('name' => $module_name)
						);
			$message = $this->get_language_constant('message_module_activated');

		} else {
			$message = $this->get_language_constant('message_module_protected');
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $message,
					'action'	=> window_Close($this->name.'_module_dialog').";".window_ReloadContent('system_modules')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Deactivates specified module
	 */
	private function deactivateModule() {
		$module_name = escape_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = ModuleManager::get_instance();
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
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $message,
					'action'		=> window_Close($this->name.'_module_dialog').";".window_ReloadContent('system_modules')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Print confirmation form before initialising module
	 */
	private function initialiseModule() {
		$module_name = escape_chars($_REQUEST['module_name']);

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant('message_module_initialise'),
					'name'			=> $module_name,
					'yes_action'	=> window_LoadContent(
											$this->name.'_module_dialog',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'module_initialise_commit'),
												array('module_name', $module_name)
											)
										),
					'yes_text'		=> $this->get_language_constant("initialise"),
					'no_action'		=> window_Close($this->name.'_module_dialog'),
					'no_text'		=> $this->get_language_constant("cancel"),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Initialise and activate module
	 */
	private function initialiseModule_Commit() {
		$module_name = escape_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = ModuleManager::get_instance();
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

			$handler = ModuleHandler::get_instance();
			$module = $handler->load_module($module_name);

			if (!is_null($module)) {
				$module->initialize();
				$message = $this->get_language_constant('message_module_initialised');
			}

		} else {
			$message = $this->get_language_constant('message_module_protected');
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $message,
					'action'		=> window_Close($this->name.'_module_dialog').";".window_ReloadContent('system_modules')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Print confirmation dialog before disabling module
	 */
	private function disableModule() {
		$module_name = escape_chars($_REQUEST['module_name']);

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant('message_module_disable'),
					'name'			=> $module_name,
					'yes_action'	=> window_LoadContent(
											$this->name.'_module_dialog',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'module_disable_commit'),
												array('module_name', $module_name)
											)
										),
					'yes_text'		=> $this->get_language_constant("disable"),
					'no_action'		=> window_Close($this->name.'_module_dialog'),
					'no_text'		=> $this->get_language_constant("cancel"),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Disable specified module and remove it's settings
	 */
	private function disableModule_Commit() {
		$module_name = escape_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = ModuleManager::get_instance();
			$max_order = $manager->get_item_value(
										'MAX(`order`)',
										array('preload' => 0)
									);

			if (is_null($max_order)) $max_order = -1;

			$manager->delete_items(array('name' => $module_name));

			if (ModuleHandler::is_loaded($module_name)) {
				$module = call_user_func(array($module_name, 'get_instance'));
				$module->cleanup();

				$message = $this->get_language_constant('message_module_disabled');

			} else {
				$message = $this->get_language_constant('message_module_not_active');
			}

		} else {
			$message = $this->get_language_constant('message_module_protected');
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $message,
					'action'		=> window_Close($this->name.'_module_dialog').";".window_ReloadContent('system_modules')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Clear cache.
	 */
	private function clearCache() {
		// clear cache
		$cache = Cache::get_instance();
		$cache->clear();

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant('message_cleared_cache'),
					'action'		=> window_Close('system_clear_cache')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show import file selection list.
	 */
	private function exports() {
		$template = new TemplateHandler('exports_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:list', $this, 'tag_ExportsList');

		$params = array(
				'link_export' => URL::make_hyperlink(
						$this->get_language_constant('export'),
						window_Open(
							'system_export_data',	 	// window id
							350,						// width
							$this->get_language_constant('title_export_data'), // title
							false, false,
							URL::make_query(
								'backend_module',
								'transfer_control',
								array('module', $this->name),
								array('backend_action', 'export_options')
							)
						)),
			);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show configuration dialog
	 */
	private function import_options() {
		// load information from the file
		$file_name = fix_chars($_REQUEST['file_name']);
		$file = new Core\Exports\File($file_name, '', false);  // open file without verifying hash
		$description = $file->read(Core\Exports\Section::DESCRIPTION, null, false);
		$file->close();

		// render user interface
		$template = new TemplateHandler('import_options.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:module_import_list', $this, 'tag_ModuleImportList');

		$params = array(
				'file_name'     => $file_name,
				'description'   => $description,
				'form_action'   => backend_UrlMake($this->name, 'import_commit'),
			);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Perform data import.
	 */
	private function import_commit() {
		$manager = SettingsManager::get_instance();
		$file_name = str_replace(array('/', '\\'), '_', fix_chars($_REQUEST['file_name']));
		$encryption_key = $_REQUEST['key'];
		$options = array (
				'include_files' => $this->get_boolean_field('include_files')
			);

		// preload message template
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$params = array(
					'button'  => $this->get_language_constant('close'),
					'action'  => window_Close('system_import_data')
				);

		// collect list of data and settings to import
		$module_data = array();
		$module_settings = array();

		foreach ($_REQUEST as $key => $value) {
			if (substr($key, 0, 5) == 'data_' && $value == 1)
				$module_data []= substr($key, 5);
			if (substr($key, 0, 9) == 'settings_' && $value == 1)
				$module_settings []= substr($key, 9);
		}

		// load import file and log the whole process
		error_log("Loading export file for import '{$file_name}'...");
		try {
			$file = new Core\Exports\File($file_name, $encryption_key);

		} catch (Core\Exports\InvalidKeyException $error) {
			error_log('Invalid key specified. Unable to import!');

			// show error message about invalid key
			$params['message'] = $this->get_language_constant('message_import_error_key');
			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
			return;

		} catch (Core\Exports\InvalidExportException $error) {
			error_log('Invalid exports file provided. Unable to import!');

			// show error message about invalid key
			$params['message'] = $this->get_language_constant('message_import_error_file');
			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
			return;
		}

		// restore data
		$key_list_data = $file->get_key_names(Core\Exports\Section::DATA);
		$key_list_settings = $file->get_key_names(Core\Exports\Section::SETTINGS);
		$module_list = array_unique(array_merge($key_list_data, $key_list_settings), SORT_REGULAR);

		foreach ($module_list as $module_name) {
			// make sure module is loaded
			if (!ModuleHandler::is_loaded($module_name))
				continue;

			// get module instance
			$module = call_user_func(array($module_name, 'get_instance'));

			// import module data
			if (in_array($module_name, $module_data)) {
				$data = $file->read(Core\Exports\Section::DATA, $module_name);
				$data = unserialize($data);
				$module->import_data($data, $options, $file);
			}

			// import module settings
			if (in_array($module_name, $module_settings)) {
				$data = $file->read(Core\Exports\Section::SETTINGS, $module_name);
				$data = unserialize($data);

				if ($data !== FALSE)
					// Replace variable value instead of calling
					// update. This way we ensure value is inserted.
					foreach ($data as $row) {
						$manager->delete_items(array(
							'module'   => $module_name,
							'variable' => $row->variable
						));
						$manager->insert_item(array(
							'module'   => $module_name,
							'variable' => $row->variable,
							'value'    => $row->value
						));
					}
			}
		}

		// complete import
		$file->close();
		error_log('Import completed!');

		// show completed message
		$params['message'] = $this->get_language_constant('message_import_completed');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show configuration dialog for exporting data.
	 */
	private function export_options() {
		$template = new TemplateHandler('export_options.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
				'form_action'	=> backend_UrlMake($this->name, 'export_commit'),
			);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Create data export with specified configuration.
	 */
	private function export_commit() {
		// collect data
		$file_name = str_replace(array('/', '\\'), '_', fix_chars($_REQUEST['file_name']));
		$key = $_REQUEST['key'];
		$options = array(
				'include_files' => $this->get_boolean_field('include_files'),
				'include_settings' => $this->get_boolean_field('include_settings'),
				'description' => fix_chars($_REQUEST['description'])
			);

		// load message template
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('system_export_data').';'.window_ReloadContent('system_exports')
				);

		// perform export
		if (ModuleHandler::export_data($key, $file_name, $options))
			$params['message'] = $this->get_language_constant('message_export_completed'); else
			$params['message'] = $this->get_language_constant('message_export_error');

		// render message
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Render module list tag.
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function tag_ModuleList($params, $children) {
		global $module_path, $system_module_path;

		$list = array();
		$raw_list = $this->getModuleList();
		$manager = ModuleManager::get_instance();

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

		// load template
		$template = $this->load_template($tag_params, 'module.xml');

		// render data
		foreach($list as $name => $definition) {
			// locate module icon
			$icon_file = null;
			if (file_exists(_BASEPATH.'/'.$module_path.$name))
				$icon_file = _BASEPATH.'/'.$module_path.$name.'/images/icon.svg'; else
				$icon_file = _BASEPATH.'/'.$system_module_path.$name.'/images/icon.svg';

			if (file_exists($icon_file))
				$icon = URL::from_file_path($icon_file); else
				$this->path.'images/modules.svg';

			$params = array(
							'name'				=> $name,
							'icon'				=> $icon,
							'status'			=> $definition['status'],
							'active'			=> $definition['active'],
							'active_symbol'		=> $definition['active'] ? CHAR_CHECKED : CHAR_UNCHECKED,
							'preload'			=> $definition['preload'],
							'preload_symbol'	=> $definition['preload'] ? CHAR_CHECKED : CHAR_UNCHECKED,
							'order'				=> $definition['order'],
							'item_activate'		=> URL::make_hyperlink(
													$this->get_language_constant('activate'),
													window_Open(
														$this->name.'_module_dialog',	// window id
														300,							// width
														$this->get_language_constant('title_module_activate'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'module_activate'),
															array('module_name', $name)
														)
													)
												),
							'item_deactivate'		=> URL::make_hyperlink(
													$this->get_language_constant('deactivate'),
													window_Open(
														$this->name.'_module_dialog',	// window id
														300,							// width
														$this->get_language_constant('title_module_deactivate'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'module_deactivate'),
															array('module_name', $name)
														)
													)
												),
							'item_initialise'		=> URL::make_hyperlink(
													$this->get_language_constant('initialise'),
													window_Open(
														$this->name.'_module_dialog',	// window id
														300,							// width
														$this->get_language_constant('title_module_initialise'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'module_initialise'),
															array('module_name', $name)
														)
													)
												),
							'item_disable'		=> URL::make_hyperlink(
													$this->get_language_constant('disable'),
													window_Open(
														$this->name.'_module_dialog',	// window id
														300,							// width
														$this->get_language_constant('title_module_disable'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'module_disable'),
															array('module_name', $name)
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

		// make the list easier to navigate
		sort($result);

		return $result;
	}

	/**
	 * Draws all menus for current level
	 */
	public function tag_MainMenu($tag_params, $children) {
		// call for module to add their menu items
		Events::trigger('backend', 'add-menu-items');

		// draw menu items
		$template = new TemplateHandler('menu_item.xml', $this->path.'templates/');
		foreach ($this->menus as $item)
			$item->drawItem($template);
	}

	/**
	 * Render list of data exports available for import.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ExportsList($tag_params, $children) {
		global $backup_path;

		// load template
		$template = $this->load_template($tag_params, 'exports_list_item.xml');

		// render template if there are files for import
		foreach(scandir($backup_path) as $file_name) {
			if ($file_name == '.' || $file_name == '..')
				continue;

			$params = array(
					'name'        => $file_name,
					'size'        => round(filesize($backup_path.$file_name) / 1000, 2).' kB',
					'item_import' => URL::make_hyperlink(
							$this->get_language_constant('import'),
							window_Open(
								'system_import_data',	 	// window id
								400,						// width
								$this->get_language_constant('title_import_data').' '.$file_name, // title
								false, false,
								URL::make_query(
									'backend_module',
									'transfer_control',
									array('module', $this->name),
									array('backend_action', 'import_options'),
									array('file_name', $file_name)
								)
							)),
					'item_download' => URL::make_hyperlink(
							$this->get_language_constant('download'),
							URL::from_file_path(_BASEPATH.'/'.$backup_path.$file_name)
						)
				);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * List rendering function for module configuration and data checkboxes.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ModuleImportList($tag_params, $children) {
		$file_name = str_replace(array('/', '\\'), '_', fix_chars($tag_params['file_name']));
		$file = new Core\Exports\File($file_name, '', false);  // open file without hash verification
		$module_data = $file->get_key_names(Core\Exports\Section::DATA);
		$module_settings = $file->get_key_names(Core\Exports\Section::SETTINGS);
		$file->close();

		// combine lists
		$module_list = array_unique(array_merge($module_data, $module_settings), SORT_REGULAR);

		if (count($module_list) == 0)
			return;

		// load template for parsing
		$template = $this->load_template($tag_params, 'import_options_module_list_item.xml');

		foreach ($module_list as $module_name) {
			$params = array(
					'has_settings' => in_array($module_name, $module_settings),
					'has_data'     => in_array($module_name, $module_data),
					'name'         => $module_name
				);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Include sprites and trigger event.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Sprites($tag_params, $children) {
		print file_get_contents($this->path.'images/sprite.svg');
		print file_get_contents($this->path.'images/system.svg');
		Events::trigger('backend', 'sprite-include');
	}
}

?>
