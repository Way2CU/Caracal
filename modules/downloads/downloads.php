<?php

/**
 * Downloads Module
 *
 * Module providing easy-to-manage downloads section.
 *
 * Author: Mladen Mijatov
 */

class downloads extends Module {
	private static $_instance;

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
			// add backend specific script
			if (class_exists('head_tag')) {
				$head_tag = head_tag::getInstance();
				$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/downloads_toolbar.js'), 'type'=>'text/javascript'));
			}

			// create main menu entries
			$backend = backend::getInstance();

			$downloads_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_downloads'),
					url_GetFromFilePath($this->path.'images/icon.png'),
					'javascript:void(0);',
					$level=5
				);

			$downloads_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_upload_file'),
								url_GetFromFilePath($this->path.'images/upload.png'),
								window_Open( // on click open window
											'upload_file',
											400,
											$this->getLanguageConstant('title_upload_file'),
											true, true,
											backend_UrlMake($this->name, 'upload')
										),
								5  // level
							));

			$downloads_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_manage'),
								url_GetFromFilePath($this->path.'images/manage.png'),
								window_Open( // on click open window
											'downloads',
											450,
											$this->getLanguageConstant('title_manage'),
											true, true,
											backend_UrlMake($this->name, 'list')
										),
								5  // level
							));

			$backend->addMenu($this->name, $downloads_menu);
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
				case 'get':
					$this->redirectDownload();
					break;

				case 'json_list':
					$this->json_DownloadsList();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'upload':
					$this->uploadFile();
					break;

				case 'upload_save':
					$this->uploadFile_Save();
					break;

				case 'list':
					$this->showDownloads();
					break;

				case 'change':
					$this->changeData();
					break;

				case 'save':
					$this->saveData();
					break;

				case 'delete':
					$this->deleteDownload();
					break;

				case 'delete_commit':
					$this->deleteDownload_Commit();
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

		$sql = "
			CREATE TABLE `downloads` (
				`id` INT NOT NULL AUTO_INCREMENT ,";

		foreach($list as $language)
			$sql .= "`name_{$language}` VARCHAR( 100 ) NOT NULL ,";

		foreach($list as $language)
			$sql .= "`description_{$language}` TEXT NOT NULL ,";

		$sql .= "`count` INT NOT NULL DEFAULT  '0',
				`filename` VARCHAR( 100 ) NOT NULL ,
				`size` INT NOT NULL ,
				`visible` BOOLEAN NOT NULL DEFAULT  '1',
				`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
				PRIMARY KEY (  `id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array('downloads');
		$db->drop_tables($tables);
	}


	/**
	 * Show downloads management form
	 */
	private function showDownloads() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('menu_upload_file'),
										'upload_file', 400,
										$this->getLanguageConstant('title_upload_file'),
										true, false,
										$this->name,
										'upload'
									)
					);

		$template->registerTagHandler('_downloads_list', $this, 'tag_DownloadsList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Provides a form for uploading files
	 */
	private function uploadFile() {
		$template = new TemplateHandler('upload.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'upload_save'),
					'cancel_action'	=> window_Close('upload_file')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save uploaded file to database and rename it (if needed)
	 */
	private function uploadFile_Save() {

		$result = $this->_saveUpload('file');

		if (!$result['error']) {
			$manager =  DownloadsManager::getInstance();

			$data = array(
					'name'			=> fix_chars($this->getMultilanguageField('name')),
					'description' 	=> escape_chars($this->getMultilanguageField('description')),
					'filename'		=> $result['filename'],
					'size'			=> $_FILES['file']['size'],
					'visible'		=> isset($_REQUEST['visible']) ? 1 : 0
				);

			$manager->insertData($data);
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $result['message'],
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('upload_file').";".window_ReloadContent('downloads')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Print form for changing data
	 */
	private function changeData() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = DownloadsManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $item->id,
					'name'			=> unfix_chars($item->name),
					'description'	=> $item->description,
					'filename'		=> $item->filename,
					'visible'		=> $item->visible,
					'form_action'	=> backend_UrlMake($this->name, 'save'),
					'cancel_action'	=> window_Close('downloads_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save changes of download file
	 */
	private function saveData() {
		$manager = DownloadsManager::getInstance();

		$id = fix_id($_REQUEST['id']);
		$data = array(
				'name'			=> fix_chars($this->getMultilanguageField('name')),
				'description' 	=> escape_chars($this->getMultilanguageField('description')),
				'visible'		=> fix_id($_REQUEST['visible'])
			);

		$manager->updateData($data, array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_file_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('downloads_change').";".window_ReloadContent('downloads')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation dialog for delete
	 */
	private function deleteDownload() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = DownloadsManager::getInstance();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant('message_file_delete'),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->getLanguageConstant('delete'),
					'no_text'		=> $this->getLanguageConstant('cancel'),
					'yes_action'	=> window_LoadContent(
											'downloads_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('downloads_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Complete removal of specified image
	 */
	private function deleteDownload_Commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));

		$manager = DownloadsManager::getInstance();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_file_deleted'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('downloads_delete').";".window_ReloadContent('downloads')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Record download count and redirect to existing file
	 */
	private function redirectDownload() {
		define('_OMIT_STATS', 1);

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = DownloadsManager::getInstance();

		if (!is_null($id)) {
			$item = $manager->getSingleItem(array('count', 'filename'), array('id' => $id));

			// update count
			$manager->updateData(array('count' => $item->count + 1), array('id' => $id));

			// redirect
			$url = $this->_getDownloadURL($item);
			header("Location: {$url}");

		} else {
			die('Invalid download ID!');
		}
	}

	/**
	 * Handle _download tag
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Download($tag_params, $children) {
	}

	/**
	 * Handle _downloads_list tag
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_DownloadsList($tag_params, $children) {
		$manager = DownloadsManager::getInstance();
		$conditions = array();

		if (!isset($tag_params['show_invisible']))
			$conditions['visible'] = 1;

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('list_item.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);
		$template->registerTagHandler('_download', $this, 'tag_Download');

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
							'id'			=> $item->id,
							'name'			=> $item->name,
							'description'	=> $item->description,
							'filename'		=> $item->filename,
							'size'			=> $item->size,
							'count'			=> $item->count,
							'visible'		=> $item->visible,
							'timestamp'		=> $item->timestamp,
							'item_change'	=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														'downloads_change', 		// window id
														400,						// width
														$this->getLanguageConstant('title_change'), // title
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
														'downloads_delete', 	// window id
														400,						// width
														$this->getLanguageConstant('title_delete'), // title
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
	 * Handle JSON request for list of downloads.
	 */
	private function json_DownloadsList() {
		define('_OMIT_STATS', 1);

		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'items'			=> array()
				);

		if ($this->checkLicense()) {
			// valid license or local API requested data
			$manager = DownloadsManager::getInstance();
			$conditions = array();

			$items = $manager->getItems($manager->getFieldNames(), $conditions);

			if (count($items) > 0)
				foreach($items as $item) {
					$result['items'][] = array(
								'id'			=> $item->id,
								'name'			=> $item->name,
								'description'	=> $item->description,
								'count'			=> $item->count,
								'filename'		=> $item->filename,
								'size'			=> $item->size,
								'visible'		=> $item->visible,
								'timestamp'		=> $item->timestamp,
								'download_url'	=> url_Make('get', $this->name, array('id', $item->id))
							);
				}
		} else {
			// invalid license
			$result['error'] = true;
			$result['error_message'] = $this->getLanguageConstant('message_license_error');
		}

		print json_encode($result);
	}

	/**
	 * Get apropriate file name from original
	 */
	private function _getFileName($filename) {
		$result = $filename;

		// check if file with the same name already exists
		if (file_exists($this->path.'files/'.$filename)) {
			$info = pathinfo($filename);
			$result = time().'_'.$info['basename'];
		}

		return $result;
	}

	/**
	 * Return absolute URL for file download
	 *
	 * @param resource $item
	 * @return string
	 */
	private function _getDownloadURL($item) {
		return url_GetFromFilePath($this->path.'files/'.$item->filename);
	}

	/**
	 * Store file in new location
	 */
	private function _saveUpload($field_name) {
		$result = array(
					'error'		=> false,
					'message'	=> '',
				);

		if (is_uploaded_file($_FILES[$field_name]['tmp_name'])) {
			// prepare data for recording
			$file_name = $this->_getFileName(fix_chars(basename($_FILES[$field_name]['name'])));

			if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $this->path.'files/'.$file_name)) {
				// file was moved properly, record new data
				$result['filename'] = $file_name;
				$result['message'] = $this->getLanguageConstant('message_file_uploaded');

			} else {
				// error moving file to new location. folder permissions?
				$result['error'] = true;
				$result['message'] = $this->getLanguageConstant('message_file_save_error');
			}

		} else {
			// there was an error during upload, notify user
			$result['error'] = true;
			$result['message'] = $this->getLanguageConstant('message_file_upload_error');
		}

		return $result;
	}
}


class DownloadsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('downloads');

		$this->addProperty('id', 'int');
		$this->addProperty('name', 'ml_varchar');
		$this->addProperty('description', 'ml_text');
		$this->addProperty('count', 'int');
		$this->addProperty('filename', 'varchar');
		$this->addProperty('size', 'int');
		$this->addProperty('visible', 'boolean');
		$this->addProperty('timestamp', 'timestamp');
	}

	/**
	 * Override function in order to remove required files along with database data
	 * @param array $conditionals
	 */
	function deleteData($conditionals) {
		$items = $this->getItems(array('filename'), $conditionals);

		$path = dirname(__FILE__).'/files/';

		if (count($items) > 0)
			foreach ($items as $item)
				unlink($path.$item->filename);

		parent::deleteData($conditionals);
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
