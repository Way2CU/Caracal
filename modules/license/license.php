<?php

/**
 * License Manager
 *
 * @author MeanEYE.rcf
 */

class license extends Module {
	private static $_instance;
	private $_salt = 'CMS_LICENSE_SALT_';

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// load module style and scripts
		if (class_exists('head_tag')) {
			$head_tag = head_tag::getInstance();
			//$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/_blank.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/_blank.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if (class_exists('backend')) {
			$backend = backend::getInstance();

			$menu = $backend->getMenu($backend->name);

			if (!is_null($menu))
				$menu->insertChild(new backend_MenuItem(
										$this->getLanguageConstant('menu_license'),
										url_GetFromFilePath($this->path.'images/icon.png'),
										window_Open( // on click open window
													'licenses',
													680,
													$this->getLanguageConstant('title_licenses'),
													true, false, // disallow minimize, safety feature
													backend_UrlMake($this->name, 'show')
												),
										$level=10
									), 1);
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
				case 'json_get_license_key':
					$this->json_GetLicense();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'show':
					$this->showList();
					break;

				case 'new':
					$this->createLicense();
					break;

				case 'change':
					$this->changeLicense();
					break;

				case 'save':
					$this->saveLicense();
					break;

				case 'delete':
					$this->deleteLicense();
					break;

				case 'delete_commit':
					$this->deleteLicense_Commit();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db_active, $db;

		$sql = "
			CREATE TABLE `licenses` (
				`id` INT NOT NULL AUTO_INCREMENT ,
				`license` VARCHAR( 35 ) NOT NULL ,
				`referer` VARCHAR( 100 ) NOT NULL ,
				`active` BOOLEAN NOT NULL DEFAULT '0',
				PRIMARY KEY ( `id` )
			) ENGINE = MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		$sql = "
			CREATE TABLE `license_modules` (
				`id` INT NOT NULL AUTO_INCREMENT ,
				`license` INT NOT NULL ,
				`module` VARCHAR( 32 ) NOT NULL ,
				PRIMARY KEY ( `id` )
			) ENGINE = MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db_active, $db;

		$sql = "DROP TABLE IF EXISTS `licenses`, `license_modules`;";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Check if license is valid for specified module
	 *
	 * @param string $module_name
	 * @param string $license
	 * @return boolean
	 */
	public function isLicenseValid($module_name, $license) {
		$result = false;
		$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;

		if (!is_null($referer)) {
			$url = parse_url($referer, PHP_URL_HOST);

			if (!is_null($url) && $url == $_SERVER['HTTP_HOST']) {
				// local api, just return true
				$result = true;

			} else {
				// API requesting verification is not local
				$manager = LicenseManager::getInstance();
				$manager_modules = LicenseModulesManager::getInstance();

				$license = $manager->getSingleItem(
											$manager->getFieldNames(),
											array(
												'license' 	=> $license,
												'active'	=> true
											));

				// set result
				$result = is_object($license) && $license->referer = $referer;
			}
		}

		return $result;
	}

	/**
	 * Generate license based on referer URL and localy set salt
	 *
	 * @param string $referer
	 * @return string
	 */
	private function generateLicense($referer) {
		$license = md5($this->_salt.$referer);
		$license = substr($license, 0, 5).'-'.substr($license, 5, -5).'-'.substr($license, -5);
		$license = substr($license, 0, 16).'-'.substr($license, -16);

		return $license;
	}

	/**
	 * Show license list form
	 */
	private function showList() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
						'link_new'		=> window_OpenHyperlink(
											$this->getLanguageConstant('new'),
											'licenses_new', 400,
											$this->getLanguageConstant('title_licenses_new'),
											true, false,
											$this->name,
											'new'
										),
						'form_action'	=> backend_UrlMake($this->name, 'save'),
						'cancel_action'	=> window_Close('page_settings')
					);

		$template->registerTagHandler('_list', $this, 'tag_LicenseList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show input for new license
	 */
	private function createLicense() {
		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'save'),
					'cancel_action'	=> window_Close('licenses_new')
				);

		$template->registerTagHandler('_module_list', $this, 'tag_ModuleList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Present a form for changing license data
	 */
	private function changeLicense() {
		$id = fix_id($_REQUEST['id']);
		$manager = LicenseManager::getInstance();

		// grab license from database
		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		// create template
		$template = new TemplateHandler('change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		if (is_object($item)) {
			$params = array(
						'id'			=> $item->id,
						'license'		=> $item->license,
						'referer'		=> $item->referer,
						'active'		=> $item->active,
						'form_action'	=> backend_UrlMake($this->name, 'save'),
						'cancel_action'	=> window_Close('licenses_change')
					);

			$template->registerTagHandler('_module_list', $this, 'tag_ModuleList');

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save license from data submited to the system
	 */
	private function saveLicense() {
		// get data
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$referer = escape_chars($_REQUEST['referer']);
		$active = fix_id($_REQUEST['active']);
		$license_key = $this->generateLicense($referer);
		$modules = array();

		// populate array with selected modules
		foreach ($_REQUEST as $key => $value)
			if (substr($key, 0, 7) == 'module_' && $value == 1)
				$modules[] = substr($key, 7);

		// get data managers
		$manager = LicenseManager::getInstance();
		$license_modules = LicenseModulesManager::getInstance();

		$data = array(
					'license'	=> $license_key,
					'referer'	=> $referer,
					'active'	=> $active,
				);

		// depending on ID we determine whether process is changing data or inserting new
		if (is_null($id)) {
			$window = 'licenses_new';
			$manager->insertData($data);
			$id = $manager->getInsertedID();
		} else {
			$window = 'licenses_change';
			$manager->updateData($data,	array('id' => $id));

			// existing license modules need to be removed
			$license_modules->deleteData(array('license' => $id));
		}

		// store license modules to database
		if (count($modules) > 0)
			foreach($modules as $module_name)
				$license_modules->insertData(array(
										'license'	=> $id,
										'module'	=> $module_name
									));

		// prepare and parse result message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_license_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('licenses'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation dialog before deleting
	 */
	private function deleteLicense() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = LicenseManager::getInstance();

		$item = $manager->getSingleItem(array('referer'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_license_delete"),
					'name'			=> $item->referer,
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'licenses_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('licenses_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Delete specified license
	 * Enter description here ...
	 */
	private function deleteLicense_Commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = LicenseManager::getInstance();
		$license_modules_manager = LicenseModulesManager::getInstance();

		$manager->deleteData(array('id' => $id));
		$license_modules_manager->deleteData(array('license' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_license_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('licenses_delete').";".window_ReloadContent('licenses')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Handle tag for displaying modules
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ModuleList($tag_params, $children) {
		$module_list = array();
		$selected_list = array();

		// grab module list from backend module
		if (class_exists('backend'))
			$module_list = backend::getInstance()->getModuleList();

		// if license is specified we need to populate selected list
		if (isset($tag_params['license'])) {
			$manager = LicenseManager::getInstance();
			$license_modules_manager = LicenseModulesManager::getInstance();
			$license = $tag_params['license'];

			if (is_object($manager->getSingleItem(array('id'), array('id' => $license)))) {
				$license_modules = $license_modules_manager->getItems(array('module'), array('license' => $license));

				if (count($license_modules) > 0)
					foreach ($license_modules as $item)
						$selected_list[] = $item->module;
			}
		}

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('module_list_item.xml', $this->path.'templates/');
		}

		if (count($module_list) > 0)
			foreach ($module_list as $module) {
				$params = array(
							'name'		=> $module,
							'in_group'	=> in_array($module, $selected_list) ? 1 : 0
						);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Handle drawing of license list
	 *
	 * @param array $tag_param
	 * @param array $children
	 */
	public function tag_LicenseList($tag_param, $children) {
		$manager = LicenseManager::getInstance();
		$items = $manager->getItems($manager->getFieldNames(), array());

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('list_item.xml', $this->path.'templates/');
		}

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
							'license'		=> $item->license,
							'referer'		=> $item->referer,
							'active'		=> $item->active,
							'item_change'	=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														'licenses_change', 	// window id
														400,				// width
														$this->getLanguageConstant('title_licenses_change'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> url_MakeHyperlink(
													$this->getLanguageConstant('delete'),
													window_Open(
														'licenses_delete', 	// window id
														400,				// width
														$this->getLanguageConstant('title_licenses_delete'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'delete'),
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
	 * Get license for specified referer
	 */
	private function json_GetLicense() {
		define('_OMIT_STATS', 1);
		$result = '';

		if (isset($_REQUEST['referer'])) {
			$referer = escape_chars($_REQUEST['referer']);
			$result = $this->generateLicense($referer);
		}

		print json_encode($result);
	}
}


class LicenseManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('licenses');

		$this->addProperty('id', 'int');
		$this->addProperty('license', 'varchar');
		$this->addProperty('referer', 'varchar');
		$this->addProperty('active', 'boolean');
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

class LicenseModulesManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('license_modules');

		$this->addProperty('id', 'int');
		$this->addProperty('license', 'int');
		$this->addProperty('module', 'varchar');
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
