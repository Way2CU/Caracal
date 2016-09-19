<?php

/**
 * Downloads Module
 *
 * Module providing easy-to-manage downloads section.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;


class downloads extends Module {
	private static $_instance;

	public $file_path = null;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section, $site_path;

		parent::__construct(__FILE__);

		// create directories for storing files
		$this->file_path = _BASEPATH.'/'.$site_path.'downloads/';

		if (!file_exists($this->file_path))
			if (mkdir($this->file_path, 0775, true) === false) {
				trigger_error('Downloads: Error creating storage directory.', E_USER_WARNING);
				return;
			}

		// register backend
		if (ModuleHandler::is_loaded('backend')) {
			// add backend specific script
			if (ModuleHandler::is_loaded('head_tag')) {
				$head_tag = head_tag::get_instance();
				$head_tag->addTag('script', array('src'=>URL::from_file_path($this->path.'include/downloads_toolbar.js'), 'type'=>'text/javascript'));
			}

			// create main menu entries
			$backend = backend::get_instance();

			$downloads_menu = new backend_MenuItem(
					$this->get_language_constant('menu_downloads'),
					URL::from_file_path($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);

			$downloads_menu->addChild(null, new backend_MenuItem(
								$this->get_language_constant('menu_upload_file'),
								URL::from_file_path($this->path.'images/upload.svg'),
								window_Open( // on click open window
											'upload_file',
											400,
											$this->get_language_constant('title_upload_file'),
											true, true,
											backend_UrlMake($this->name, 'upload')
										),
								5  // level
							));

			$downloads_menu->addChild(null, new backend_MenuItem(
								$this->get_language_constant('menu_manage'),
								URL::from_file_path($this->path.'images/manage.svg'),
								window_Open( // on click open window
											'downloads',
											520,
											$this->get_language_constant('title_manage'),
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
	public static function get_instance() {
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
	public function transfer_control($params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'get':
					$this->redirectDownload();
					break;

				case 'show':
					$this->tag_Download($params, $children);
					break;

				case 'show_list':
					$this->tag_DownloadsList($params, $children);
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
	public function on_init() {
		global $db;

		$list = Language::get_languages(false);

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
	public function on_disable() {
		global $db;

		$tables = array('downloads');
		$db->drop_tables($tables);
	}


	/**
	 * Show downloads management form
	 */
	private function showDownloads() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->get_language_constant('menu_upload_file'),
										'upload_file', 400,
										$this->get_language_constant('title_upload_file'),
										true, false,
										$this->name,
										'upload'
									)
					);

		$template->register_tag_handler('_downloads_list', $this, 'tag_DownloadsList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Provides a form for uploading files
	 */
	private function uploadFile() {
		$template = new TemplateHandler('upload.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'upload_save'),
					'cancel_action'	=> window_Close('upload_file')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save uploaded file to database and rename it (if needed)
	 */
	private function uploadFile_Save() {
		$result = $this->saveUpload('file');

		if (!$result['error']) {
			$manager =  DownloadsManager::get_instance();

			$data = array(
					'name'			=> $this->get_multilanguage_field('name'),
					'description' 	=> $this->get_multilanguage_field('description'),
					'filename'		=> $result['filename'],
					'size'			=> $_FILES['file']['size'],
					'visible'		=> isset($_REQUEST['visible']) ? 1 : 0
				);

			$manager->insert_item($data);
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $result['message'],
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('upload_file').";".window_ReloadContent('downloads')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Print form for changing data
	 */
	private function changeData() {
		$id = fix_id($_REQUEST['id']);
		$manager = DownloadsManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		$template = new TemplateHandler('change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'id'			=> $item->id,
					'name'			=> unfix_chars($item->name),
					'description'	=> $item->description,
					'filename'		=> $item->filename,
					'visible'		=> $item->visible,
					'form_action'	=> backend_UrlMake($this->name, 'save'),
					'cancel_action'	=> window_Close('downloads_change')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save changes of download file
	 */
	private function saveData() {
		$manager = DownloadsManager::get_instance();

		$id = fix_id($_REQUEST['id']);
		$data = array(
				'name'			=> $this->get_multilanguage_field('name'),
				'description' 	=> $this->get_multilanguage_field('description'),
				'visible'		=> fix_id($_REQUEST['visible'])
			);

		$manager->update_items($data, array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_file_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('downloads_change').";".window_ReloadContent('downloads')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show confirmation dialog for delete
	 */
	private function deleteDownload() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = DownloadsManager::get_instance();

		$item = $manager->get_single_item(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant('message_file_delete'),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->get_language_constant('delete'),
					'no_text'		=> $this->get_language_constant('cancel'),
					'yes_action'	=> window_LoadContent(
											'downloads_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('downloads_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Complete removal of specified image
	 */
	private function deleteDownload_Commit() {
		$id = fix_id($_REQUEST['id']);

		$manager = DownloadsManager::get_instance();

		$manager->delete_items(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_file_deleted'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('downloads_delete').";".window_ReloadContent('downloads')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Record download count and redirect to existing file
	 */
	private function redirectDownload() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = DownloadsManager::get_instance();

		if (!is_null($id)) {
			$item = $manager->get_single_item(array('count', 'filename'), array('id' => $id));

			// update count
			$manager->update_items(array('count' => $item->count + 1), array('id' => $id));

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
		$manager = DownloadsManager::get_instance();
		$conditions = array();
		$order_by = array();
		$order_asc = true;

		$template = $this->load_template($tag_params, 'download.xml');
		$template->set_template_params_from_array($children);

		if (isset($tag_params['latest']) && $tag_params['latest'] == 1) {
			$order_by = array('id');
			$order_asc = false;
		}

		$item = $manager->get_single_item($manager->get_field_names(), $conditions, $order_by, $order_asc);

		if (is_object($item)) {
			$params = array(
						'id'			=> $item->id,
						'name'			=> $item->name,
						'description'	=> $item->description,
						'filename'		=> $item->filename,
						'size'			=> $item->size,
						'count'			=> $item->count,
						'visible'		=> $item->visible,
						'timestamp'		=> $item->timestamp,
						'url'			=> URL::make_query($this->name, 'get', array('id', $item->id))
					);

			$template->set_local_params($params);
			$template->restore_xml();
			$template->parse();
		}
	}

	/**
	 * Handle _downloads_list tag
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_DownloadsList($tag_params, $children) {
		$manager = DownloadsManager::get_instance();
		$conditions = array();

		if (!isset($tag_params['show_invisible']))
			$conditions['visible'] = 1;

		// get items from database
		$items = $manager->get_items($manager->get_field_names(), $conditions);

		$template = $this->load_template($tag_params, 'list_item.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('_download', $this, 'tag_Download');
		$template->register_tag_handler('cms:download', $this, 'tag_Download');

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
							'url'			=> URL::make_query($this->name, 'get', array('id', $item->id)),
							'item_change'	=> URL::make_hyperlink(
													$this->get_language_constant('change'),
													window_Open(
														'downloads_change', 		// window id
														400,						// width
														$this->get_language_constant('title_change'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> URL::make_hyperlink(
													$this->get_language_constant('delete'),
													window_Open(
														'downloads_delete', 	// window id
														400,						// width
														$this->get_language_constant('title_delete'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'delete'),
															array('id', $item->id)
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
	 * Handle JSON request for list of downloads.
	 */
	private function json_DownloadsList() {
		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'items'			=> array()
				);

		if ($this->checkLicense()) {
			// valid license or local API requested data
			$manager = DownloadsManager::get_instance();
			$conditions = array();

			$items = $manager->get_items($manager->get_field_names(), $conditions);

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
								'download_url'	=> URL::make_query($this->name, 'get', array('id', $item->id))
							);
				}
		} else {
			// invalid license
			$result['error'] = true;
			$result['error_message'] = $this->get_language_constant('message_license_error');
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
		return URL::from_file_path($this->file_path.$item->filename);
	}

	/**
	 * Store file in new location
	 */
	private function saveUpload($field_name) {
		$result = array(
					'error'		=> false,
					'message'	=> '',
				);

		if (is_uploaded_file($_FILES[$field_name]['tmp_name'])) {
			// prepare data for recording
			$file_name = $this->_getFileName(fix_chars(basename($_FILES[$field_name]['name'])));

			if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $this->file_path.$file_name)) {
				// file was moved properly, record new data
				$result['filename'] = $file_name;
				$result['message'] = $this->get_language_constant('message_file_uploaded');

			} else {
				// error moving file to new location. folder permissions?
				$result['error'] = true;
				$result['message'] = $this->get_language_constant('message_file_save_error');
			}

		} else {
			// there was an error during upload, notify user
			$result['error'] = true;
			$result['message'] = $this->get_language_constant('message_file_upload_error');
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

		$this->add_property('id', 'int');
		$this->add_property('name', 'ml_varchar');
		$this->add_property('description', 'ml_text');
		$this->add_property('count', 'int');
		$this->add_property('filename', 'varchar');
		$this->add_property('size', 'int');
		$this->add_property('visible', 'boolean');
		$this->add_property('timestamp', 'timestamp');
	}

	/**
	 * Override function in order to remove required files along with database data
	 *
	 * @param array $conditionals
	 * @param integer $limit
	 */
	function delete_items($conditionals, $limit=null) {
		$items = $this->get_items(array('filename'), $conditionals);

		$path = downloads::get_instance()->file_path;

		if (count($items) > 0)
			foreach ($items as $item)
				unlink($path.$item->filename);

		parent::delete_items($conditionals, $limit);
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}
