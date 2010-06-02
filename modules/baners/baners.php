<?php

/**
 * BLANK MODULE
 * 
 * @author MeanEYE
 * @copyright RCF Group,2008.
 */
 
class baners extends Module {

	/**
	 * Constructor
	 *
	 * @return journal
	 */
	function baners() {
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
		switch ($params['action']) {
			case 'print_random_baner':
				$this->printRandomBaner($level, $params);
				break;
		}
		
		// backend control actions
		switch ($params['backend_action']) {
			case 'manage_baners':
				$template = new TemplateHandler("backend_manage.xml", $this->path.'templates/'); 
				$template->setMappedModule($this->name);
				$template->parse($level);
				break;			
			case 'upload':
				$template = new TemplateHandler("backend_upload.xml", $this->path.'templates/'); 
				$template->setMappedModule($this->name);
				$template->parse($level);
				break;			
			case 'upload_save':
				$this->saveUpload();
				break;
			case 'print_baner_list':
				$this->printBanerList($level, $params);
				break;
			case 'delete':
				$template = new TemplateHandler("backend_delete_confirm.xml", $this->path.'templates/'); 
				$template->setMappedModule($this->name);
				$template->parse($level);
				break;
			case 'delete_commit':
				$this->deleteBaner();
				break;
		}
	}

	/**
	 * Removes baner from the system
	 */
	function deleteBaner() {
		$key = fix_chars($_REQUEST['file']);
		$list = $this->getBanerList();
		$file = $list[$key];
		
		unlink($file);
		echo $this->language->getText("message_baner_deleted");
	}
	
	/**
	 * Prints baner list
	 *
	 * @param integer $level
	 * @param array $global_params
	 */
	function printBanerList($level, $global_params) {
		$list = $this->getBanerList();
		$number = 0;

		$template_file = (isset($global_params['template'])) ? $global_params['template'] : 'item_baner.xml';
		$template = new TemplateHandler($template_file, $this->path.'templates/'); 
		$template->setMappedModule($this->name);

		if (count($list) > 0)
		foreach ($list as $key=>$file) {
			$number++;
			$params = array(
				'baner_id'	=> $key, 
				'file'		=> $file,
				'number'	=> $number,
				'link_delete' => url_MakeHyperlink($this->language->getText('delete'), url_MakeFromArray(array(
										array('section', 'backend'),
										array('backend_action', 'delete'),
										array('file', $key),
										array('module', $this->name)
									)))
				);
											
			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);			
		}
	}
	
	/**
	 * Saves uploaded baner to destination directory
	 *
	 */
	function saveUpload() {
		$target = $this->path.'images/'.basename($_FILES['baner']['name']);
		
		if (!preg_match("/\.swf$/i", $_FILES['baner']['name'])) {
			unlink($_FILES['baner']['tmp_name']);
			echo $this->language->getText('message_baner_error');
			return;
		}
		move_uploaded_file($_FILES['baner']['tmp_name'], $target);
		
		echo $this->language->getText('message_baner_saved');
	}
	
	/**
	 * Prints random baner from baners directory
	 *
	 * @param integer $level
	 * @param array $global_params
	 */
	function printRandomBaner($level, $global_params) {
		$list = $this->getBanerList();
		$list_ids = array_keys($list);
		
		$rid = rand(0, count($list)-1);
		
		$baner_id = $list_ids[$rid];
		$baner_file = url_GetFromFilePath($list[$baner_id]);
		
		$template_file = (isset($global_params['template'])) ? $global_params['template'] : 'flash_code.xml';
		$template = new TemplateHandler($template_file, $this->path.'templates/'); 
		
		$params = array(
				'baner_id' 	=> (isset($global_params['component_id'])) ? $global_params['component_id'] : $baner_id,
				'file' 		=> $baner_file
			);
		
		$template->setLocalParams($params);
		$template->setMappedModule($this->name);
		$template->parse($level);
	}
	
	/**
	 * Returns list of available baners
	 *
	 * @return array
	 */
	function getBanerList() {
		$path = $this->path.'images/';

		$result = array();
		if (is_dir($path))
		    if ($dir = opendir($path)) {
		        while (($file = readdir($dir)) !== false) 
		            if (preg_match("/([\w]*)\.swf$/i", $file, $res)) $result[$res[1]] = $path.$file;
		        closedir($dir);
		    }
		return $result;
	}
	
	/**
	 * Event called upon module initialisation
	 */
	function onInit() {
		
	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler;
		
		// register backend
		if ($ModuleHandler->moduleExists('backend')) {
			$backend = $ModuleHandler->getObjectFromName('backend');
			
			$this->menu_group = new backend_MenuGroup("Baners", "", $this->name);
			$this->menu_group->addItem(new backend_MenuItem("Manage", "", "manage_baners", 1));
			
			$backend->addMenu($this->menu_group);
		}
	}	
}