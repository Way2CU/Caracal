<?php

/**
 * Main backend framework class
 *
 * @author MeanEYE[rcf]
 */

//define('_BACKEND_SECTION_', 'backend_module');
require_once('units/menu_item.php');

class backend extends Module {
	/**
	 * Menu list
	 * @var array
	 */
	var $menus = array();

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

		switch ($params['backend_action']) {
			case 'modules':
				$this->showModules($level);
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
			
			// legacy stuff
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/page.js'), 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/tree_text.js'), 'type'=>'text/javascript'));
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
											600, 
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
	 * Handle tag _module_list used to display list of all modules on the system
	 * @param $level
	 * @param $params
	 * @param $children
	 */
	function tag_ModuleList($level, $params, $children) {
		$list = $this->getModuleList();
		$manager = new System_ModuleManager();
		
		$modules_in_use = $manager->getItems(
											array('id', 'order', 'name', 'preload', 'active'),
											array(),
											array('order')
										);

		// TODO: Finish
		foreach($modules_in_use as $module) {
			if (array_key_exists($module->name, $list)) {
				// module in database exists on disk
				
			} else {
				// module does not exist on disk
				$list[$module->name] = array(
											'status'	=> 'missing',
											'object'	=> $module 
										);
			}
		}
		
		$template = new TemplateHandler(
							isset($params['template']) ? $params['template'] : 'module.xml',
							$this->path.'templates/'
						);

		$template->setMappedModule($this->name);		
						
		foreach($list as $name => $definition) {
			
			$params = array();
			
			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}
	
	/**
	 * Get list of modules available on the system
	 * @return array
	 */
	function getModuleList() {
		
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
