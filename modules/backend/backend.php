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
	/**
	 * Menu list
	 * @var array
	 */
	var $menus = array();

	/**
	 * List of protected modules who can't be disabled or deactivated
	 * @var array
	 */
	var $protected_modules = array('backend', 'head_tag', 'session', 'captcha');

	/**
	 * Constructor
	 *
	 * @return backend
	 */
	function backend() {
		$this->file = __FILE__;
		parent::Module();
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param string $action
	 * @param integer $level
	 */
	function transferControl($level, $params = array(), $children=array()) {
		global $ModuleHandler;

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
					$this->drawCompleteMenu($level);
					break;

				case 'transfer_control':
					// fix input parameters
					foreach($_REQUEST as $key => $value)
						$_REQUEST[$key] = $this->utf8_urldecode($_REQUEST[$key]);

					// transfer control
					$action = fix_chars($_REQUEST['backend_action']);
					$module_name = fix_chars($_REQUEST['module']);
					$params['backend_action'] = $action;

					if ($ModuleHandler->moduleExists($module_name)) {
						$module = $ModuleHandler->getObjectFromName($module_name);
						$module->transferControl($level, $params, $children);
					}
					break;
			}

		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'modules':
					$this->showModules($level);
					break;

				case 'module_activate':
					$this->activateModule($level);
					break;

				case 'module_deactivate':
					$this->deactivateModule($level);
					break;

				case 'module_initialise':
					$this->initialiseModule($level);
					break;

				case 'module_initialise_commit':
					$this->initialiseModule_Commit($level);
					break;

				case 'module_disable':
					$this->disableModule($level);
					break;

				case 'module_disable_commit':
					$this->disableModule_Commit($level);
					break;

				case 'users':
					break;
			}
	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler, $LanguageHandler, $section;

		// load CSS and JScript
		if ($ModuleHandler->moduleExists('head_tag') && $section == $this->name) {
			$head_tag = $ModuleHandler->getObjectFromName('head_tag');

			// load style based on current language
			$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/backend.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			if ($LanguageHandler->isRTL())
				$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/backend_rtl.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));

			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/xmlhttp.js'), 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/window.js'), 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/page.js'), 'type'=>'text/javascript'));

			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/jquery.js'), 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/jquery.iframe-post-form.js'), 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/jquery.show_html.js'), 'type'=>'text/javascript'));
		}

		// add admin level menus
		$system_menu = new backend_MenuItem(
								$this->getLanguageConstant('menu_system'),
								url_GetFromFilePath($this->path.'images/icons/16/system.png'),
								'javascript:void(0);',
								$level=10
							);

		$system_menu->addChild('', new backend_MenuItem(
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
		$system_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_users'),
								url_GetFromFilePath($this->path.'images/icons/16/users.png'),
								'javascript:void(0)',
								$level=10
							));

		$this->addMenu($this->name, $system_menu);
	}

	/**
	 * Display
	 * @param unknown_type $level
	 */
	function showModules($level) {
		$template = new TemplateHandler('modules_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array();

		$template->registerTagHandler('_module_list', &$this, 'tag_ModuleList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Activates specified module
	 * @param integer $level
	 */
	function activateModule($level) {
		$module_name = fix_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = new System_ModuleManager();
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
		$template->parse($level);
	}

	/**
	 * Deactivates specified module
	 * @param integer $level
	 */
	function deactivateModule($level) {
		$module_name = fix_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = new System_ModuleManager();
			$manager->updateData(
							array('active' => 0),
							array('name' => $module_name)
						);
			$message = $this->getLanguageConstant('message_module_deactivated');

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
		$template->parse($level);
	}

	/**
	 * Print confirmation form before initialising module
	 * @param integer $level
	 */
	function initialiseModule($level) {
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
		$template->parse($level);
	}

	/**
	 * Initialise and activate module
	 * @param integer $level
	 */
	function initialiseModule_Commit($level) {
		global $ModuleHandler;

		$module_name = fix_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = new System_ModuleManager();
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

			$module = $ModuleHandler->loadModule($module_name);
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
		$template->parse($level);
	}

	/**
	 * Print confirmation dialog before disabling module
	 * @param integer $level
	 */
	function disableModule($level) {
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
		$template->parse($level);
	}

	/**
	 * Disable specified module and remove it's settings
	 * @param integer $level
	 */
	function disableModule_Commit($level) {
		global $ModuleHandler;

		$module_name = fix_chars($_REQUEST['module_name']);

		if (!in_array($module_name, $this->protected_modules)) {
			// module is not protected
			$manager = new System_ModuleManager();
			$max_order = $manager->getItemValue(
										"MAX(`order`)",
										array('preload' => 0)
									);

			if (is_null($max_order)) $max_order = -1;

			$manager->deleteData(array('name' => $module_name));

			if ($ModuleHandler->moduleExists($module_name))
				$module = $ModuleHandler->getObjectFromName($module_name); else
				$module = $ModuleHandler->loadModule($module_name);

			$module->onDisable();
			$message = $this->getLanguageConstant('message_module_disabled');

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
		$template->parse($level);
	}

	/**
	 * Handle tag _module_list used to display list of all modules on the system
	 * @param $level
	 * @param $params
	 * @param $children
	 */
	function tag_ModuleList($level, $params, $children) {
		$list = array();
		$raw_list = $this->getModuleList();
		$manager = new System_ModuleManager();

		$modules_in_use = $manager->getItems(
											array('id', 'order', 'name', 'preload', 'active'),
											array(),
											array('preload', 'order')
										);

		// add modules from database
		foreach($modules_in_use as $module) {
			if (in_array($module->name, $raw_list)) {
				// module in database exists on disk
				if ($module->active) {
					$list[$module->name] = array('status' 	=> 'active');
				} else {
					$list[$module->name] = array('status'	=> 'inactive');
				}

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
			$params = array(
							'name'				=> $name,
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
			$template->parse($level);
		}
	}

	/**
	 * Get list of modules available on the system
	 *
	 * @return array
	 */
	function getModuleList() {
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
	 *
	 * @param integer $level
	 */
	function drawCompleteMenu($level) {
		$tag_space = str_repeat("\t", $level);

		echo "$tag_space<ul id=\"navigation\">\n";

		foreach ($this->menus as $item)
			$item->drawItem($level+1);

		echo "$tag_space</ul>\n";
	}

	/**
	 * Adds menu to draw list
	 *
	 * @param string $module
	 * @param resource $menu
	 */
	function addMenu($module, $menu) {
		$this->menus[$module] = $menu;
	}

	/**
	 * This function decodes characters encoded by JavaScript
	 *
	 * @param string $str
	 * @return string
	 */
	function utf8_urldecode($str) {
		$str = preg_replace("/%u([0-9a-f]{3,4})/i","&#x\\1;", urldecode($str));
		return html_entity_decode($str, null, 'UTF-8');;
	}
}

class System_ModuleManager extends ItemManager {

	function System_ModuleManager() {
		parent::ItemManager('system_modules');

		$this->addProperty('id', 'int');
		$this->addProperty('order', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('preload', 'smallint');
		$this->addProperty('active', 'smallint');
	}
}

class System_AccessManager extends ItemManager {

	function System_AccessManager() {
		parent::ItemManager('system_modules');

		$this->addProperty('id', 'int');
		$this->addProperty('username', 'varchar');
		$this->addProperty('password', 'varchar');
		$this->addProperty('fullname', 'varchar');
		$this->addProperty('level', 'smallint');
	}
}

?>
