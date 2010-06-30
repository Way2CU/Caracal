<?php

/**
 * Code Poject Module
 * 
 * This module provides simple URL redirection based on supplied code.
 *
 * @author MeanEYE.rcf
 */

class code_project extends Module {

	/**
	 * Constructor
	 *
	 * @return code_project
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
				case 'redirect':
					$this->redirect($level);
					break;
							
				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'codes_manage':
					$this->showCodes($level);
					break;
					
				case 'codes_add':
					$this->addCode($level);
					break;
					
				case 'codes_change':
					$this->changeCode($level);
					break;
					
				case 'codes_save':
					$this->saveCode($level);
					break;
					
				case 'codes_delete':
					$this->deleteCode($level);
					break;
					
				case 'codes_delete_commit':
					$this->deleteCode_Commit($level);
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
			CREATE TABLE `project_codes` (
				`id` INT NOT NULL AUTO_INCREMENT ,
				`code` VARCHAR( 50 ) NOT NULL ,
				`url` VARCHAR( 255 ) NOT NULL ,
				PRIMARY KEY ( `id` ) ,
				INDEX ( `code` ) 
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	function onDisable() {
		global $db, $db_active;

		$sql = "DROP TABLE IF EXISTS `project_codes`;";
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
			//$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/_blank.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/_blank.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if ($ModuleHandler->moduleExists('backend')) {
			$backend = $ModuleHandler->getObjectFromName('backend');

			$codes_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_codes'),
					url_GetFromFilePath($this->path.'images/icon.png'),
					'javascript:void(0);',
					$level=5
				);
				
			$codes_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_codes_manage'),
								url_GetFromFilePath($this->path.'images/manage.png'),
								window_Open( // on click open window
											'codes_manage',
											450,
											$this->getLanguageConstant('title_codes_manage'),
											true, true,
											backend_UrlMake($this->name, 'codes_manage')
										),
								$level=5
							));
											
			$backend->addMenu($this->name, $codes_menu);
		}
	}

	/**
	 * Show code management window
	 * @param integer $level
	 */
	function showCodes($level) {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> backend_WindowHyperlink(
										$this->getLanguageConstant('add'),
										'codes_add', 400,
										$this->getLanguageConstant('title_codes_add'),
										true, false,
										$this->name,
										'codes_add'
									),
					);

		$template->registerTagHandler('_code_list', &$this, 'tag_CodeList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}
	
	/**
	 * Show content of a form used for creation of new `code` object
	 * @param integer $level
	 */
	function addCode($level) {
		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'codes_save'),
					'cancel_action'	=> window_Close('codes_add')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}
	
	/**
	 * Show content of a form in editing state for sepected `code` object
	 * @param integer $level
	 */
	function changeCode($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new CodeManager();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $item->id,
					'code'			=> unfix_chars($item->code),
					'url'			=> unfix_chars($item->url),
					'form_action'	=> backend_UrlMake($this->name, 'codes_save'),
					'cancel_action'	=> window_Close('codes_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}
	
	
	/**
	 * Save changes existing (or new) to `code` object and display result
	 * @param integer $level
	 */
	function saveCode($level) {
		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;

		$data = array(
			'code' 			=> fix_chars($_REQUEST['code']),
			'url' 			=> fix_chars($_REQUEST['url']),
		);

		$manager = new CodeManager();

		if (!is_null($id)) {
			$manager->updateData($data, array('id' => $id));
			$window_name = 'codes_change';
		} else {
			$manager->insertData($data);
			$window_name = 'codes_add';
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_code_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window_name).";".
									window_ReloadContent('codes_manage')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}
		
	/**
	 * Present user with confirmation dialog before removal of specified `code` object
	 * @param integer $level
	 */
	function deleteCode($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new CodeManager();

		$item = $manager->getSingleItem(array('code'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_code_delete"),
					'name'			=> $item->code,
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'codes_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'codes_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('codes_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}	
	
	/**
	 * Remove specified `code` object and inform user about operation status
	 * @param integer $level
	 */
	function deleteCode_Commit($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = new CodeManager();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_code_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('codes_delete').";".window_ReloadContent('codes_manage')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}	
	
	/**
	 * Redirect user based on specified code
	 * @param integer $level
	 */
	function redirect($level) {
		$code = fix_chars($_REQUEST['code']);
		$manager = new CodeManager();
		$url = $manager->getItemValue("url", array("code" => $code));
		
		$_SESSION['request_code'] = $code;
		
		print url_SetRefresh($url, 0);
	}

	/**
	 * Tag handler for printing code lists
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	function tag_CodeList($level, $params, $children) {
		$manager = new CodeManager();
		$conditions = array();

		$items = $manager->getItems(
								$manager->getFieldNames(),
								$conditions,
								array('id')
							);

		$template = new TemplateHandler('list_item.xml', $this->path.'templates/');

		$template->setMappedModule($this->name);

		if (count($items) > 0)
		foreach ($items as $item) {
			$params = array(
						'id'				=> $item->id,
						'code'				=> $item->code,
						'url'				=> $item->url,
						'item_change'		=> url_MakeHyperlink(
												$this->getLanguageConstant('change'),
												window_Open(
													'codes_change', 	// window id
													400,				// width
													$this->getLanguageConstant('title_codes_change'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'codes_change'),
														array('id', $item->id)
													)
												)
											),
						'item_delete'		=> url_MakeHyperlink(
												$this->getLanguageConstant('delete'),
												window_Open(
													'codes_delete', 	// window id
													400,				// width
													$this->getLanguageConstant('title_codes_delete'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'codes_delete'),
														array('id', $item->id)
													)
												)
											),
						'item_open'			=> url_MakeHyperlink(
												$this->getLanguageConstant('open'),
												$item->url,
												'', '',
												'_blank'
											),
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}
	
}


class CodeManager extends ItemManager {
	function __construct() {
		parent::ItemManager('project_codes');
		
		$this->addProperty('id', 'int');
		$this->addProperty('code', 'varchar');
		$this->addProperty('url', 'varchar');		
	}
}

