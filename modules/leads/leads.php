<?php

/**
 * Leads Module
 *
 * General leads module that can be used to gain response from users
 * or gather general data about them. 
 *
 * @author Mladen Mijatov
 */

require_once('units/entries_manager.php');
require_once('units/entry_data_manager.php');
require_once('units/types_manager.php');

class leads extends Module {
	private static $_instance;

	const COLUMN_COUNT = 4;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;
		
		parent::__construct(__FILE__);


		// register backend
		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			if (class_exists('head_tag')) {
				$head_tag = head_tag::getInstance();
				$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/backend.js'), 'type'=>'text/javascript'));
			}

			$leads_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_leads'),
					url_GetFromFilePath($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);

			$leads_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_results'),
								url_GetFromFilePath($this->path.'images/results.svg'),

								window_Open( // on click open window
											'leads_results',
											730,
											$this->getLanguageConstant('title_results'),
											true, true,
											backend_UrlMake($this->name, 'results')
										),
								$level=5
							));

			$leads_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_types'),
								url_GetFromFilePath($this->path.'images/types.svg'),

								window_Open( // on click open window
											'leads_types',
											350,
											$this->getLanguageConstant('title_types'),
											true, true,
											backend_UrlMake($this->name, 'types')
										),
								$level=5
							));

			$backend->addMenu($this->name, $leads_menu);
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
	public function transferControl($params, $children) {

		// global control action
		if (isset($params['action']))
			switch ($params['action']) {
				case 'save_from_ajax':
					$this->saveFromAJAX();
					break;

				default:
					break;
			}

		// backend control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'results':
					$this->showResults();
					break;

				case 'types':
					$this->showTypes();
					break;

				case 'types_new':
					$this->createType();
					break;

				case 'types_change':
					$this->changeType();
					break;

				case 'types_save':
					$this->saveType();
					break;

				case 'types_delete':
					$this->deleteType();
					break;

				case 'types_delete_commit':
					$this->deleteType_commit();
					break;

				case 'export_data':
					$this->exportResults();
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

		$sql = "CREATE TABLE IF NOT EXISTS `leads_entries` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`type` int(11) NOT NULL DEFAULT 0,
					`address` varchar(50) NOT NULL,
					`referral` varchar(255) NOT NULL,
					`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (`id`),
					KEY `type` (`type`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
		$db->query($sql);

		$sql = "CREATE TABLE IF NOT EXISTS `leads_types` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`name` varchar(30) NOT NULL,
					`fields` varchar(255) NOT NULL,
					`unique_address` BOOLEAN NOT NULL DEFAULT '0',
					`send_email` BOOLEAN NOT NULL DEFAULT '0',
					PRIMARY KEY (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
		$db->query($sql);

		$sql = "CREATE TABLE IF NOT EXISTS `leads_entry_data` (
					`entry` int(11) NOT NULL,
					`name` varchar(30) NOT NULL,
					`value` varchar(255) NOT NULL,
					KEY `entry` (`entry`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array('leads_entries', 'leads_entry_data', 'leads_types');
		$db->drop_tables($tables);
	}
	
	/**
	 * Show leads
	 */
	private function showResults() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');

		$params = array();

		$template->registerTagHandler('_results_list', $this, 'tag_ResultsList');
		$template->registerTagHandler('_columns_list', $this, 'tag_ColumnsList');
		$template->registerTagHandler('_types_list', $this, 'tag_TypesList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Export data for specified type
	 */
	private function exportResults() {
		$data = '';
		$type_id = isset($_REQUEST['type']) ? fix_id($_REQUEST['type']) : null;
		$types_manager = LeadsTypesManager::getInstance();
		$entries_manager = LeadsEntriesManager::getInstance();
		$data_manager = LeadsEntryDataManager::getInstance();

		if (is_null($type_id) || $type_id == 0) 
			return;

		// get type from database
		$type = $types_manager->getSingleItem($types_manager->getFieldNames(), array('id' => $type_id));

		// get entries for type
		if (is_object($type)) {
			$data = 'id,ip_address,timestamp,referral;'.$type->fields."\n";
			$entries = $entries_manager->getItems($entries_manager->getFieldNames(), array('type' => $type->id));
			$rows = array();

			if (count($entries) > 0) {
				// populate rows array
				foreach($entries as $entry) {
					$id = $entry->id;
					$rows[$id] = array(
								'ip_address'	=> $entry->address,
								'timestamp'		=> $entry->timestamp,
								'referral'		=> $entry->referral
							);
				}

				// get data
				$raw_data = $data_manager->getItems($data_manager->getFieldNames(), array('entry' => array_keys($rows)));

				if (count($raw_data) > 0)
					foreach($raw_data as $raw) {
						$id = $raw->entry;
						$name = $raw->name;

						$rows[$id][$name] = $raw->value;
					}
			}

			// compile data
			$fields = explode(',', $type->fields);
			foreach ($rows as $id => $entry) {
				$tmp = array();
				
				// add required fields
				$tmp[] = $id;
				$tmp[] = $entry['ip_address'];
				$tmp[] = '"'.$entry['timestamp'].'"';
				$tmp[] = '"'.$entry['referral'].'"';

				foreach ($fields as $field)
					if (array_key_exists($field, $entry))
						$tmp[] = '"'.$entry[$field].'"'; else
						$tmp[] = '';

				$data .= implode(',', $tmp)."\n";
			}
		}

		define('_OMIT_STATS', 1);

		// print headers
    	header('Content-Type: text/csv; charset=utf-8');
    	header('Content-Disposition: attachment; filename="leads_'.str_replace('.', '-', _DOMAIN).'.csv"');
    	header('Content-Length: '.strlen($data));

    	// print data
    	print $data;
	}

	/**
	 * Show lead types form
	 */
	private function showTypes() {
		$template = new TemplateHandler('types_list.xml', $this->path.'templates/');

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('new'),
										'leads_types_new', 350,
										$this->getLanguageConstant('title_types_new'),
										true, false,
										$this->name,
										'types_new'
									),
					);

		$template->registerTagHandler('_types_list', $this, 'tag_TypesList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for creating a new lead type
	 */
	private function createType() {
		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'types_save'),
					'cancel_action'	=> window_Close('leads_types_new')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Change specified type data
	 */
	private function changeType() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = LeadsTypesManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('change.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'id'			=> $item->id,
					'name'			=> $item->name,
					'fields'		=> $item->fields,
					'unique_address' => $item->unique_address,
					'send_email'	=> $item->send_email,
					'form_action'	=> backend_UrlMake($this->name, 'types_save'),
					'cancel_action'	=> window_Close('leads_types_change')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save new lead type or changes to existing
	 */
	private function saveType() {
		// parse and sanitize data
		$id = isset($_REQUEST['id']) ? fix_id(fix_chars($_REQUEST['id'])) : null;
		$name = fix_chars($_REQUEST['name']);
		$fields = fix_chars($_REQUEST['fields']);
		$unique_address = isset($_REQUEST['unique_address']) && ($_REQUEST['unique_address'] == 'on' || $_REQUEST['unique_address'] == '1') ? 1 : 0;
		$send_email = isset($_REQUEST['send_email']) && ($_REQUEST['send_email'] == 'on' || $_REQUEST['send_email'] == '1') ? 1 : 0;

		// get manager instance
		$manager = LeadsTypesManager::getInstance();

		// prepare data
		$data = array(
					'name'				=> $name,
					'fields'			=> $fields,
					'unique_address'	=> $unique_address,
					'send_email'		=> $send_email
				);

		// update or insert new data
		if (is_null($id))
			$manager->insertData($data); else
			$manager->updateData($data, array('id' => $id));

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$window_name = (is_null($id) ? 'leads_types_new' : 'leads_types_change');
		$params = array(
					'message'	=> $this->getLanguageConstant("message_types_saved"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close($window_name).";".window_ReloadContent('leads_types')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show removal confirmation for lead type
	 */
	private function deleteType() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = LeadsTypesManager::getInstance();
		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');

		$params = array(
					'message'		=> $this->getLanguageConstant('message_types_delete'),
					'name'			=> $item->name,
					'yes_text'		=> $this->getLanguageConstant('delete'),
					'no_text'		=> $this->getLanguageConstant('cancel'),
					'yes_action'	=> window_LoadContent(
											'leads_types_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'types_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('leads_types_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Perform lead type removal
	 */
	private function deleteType_commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$type_manager = LeadsTypesManager::getInstance();
		$entries_manager = LeadsEntriesManager::getInstance();
		$entry_data_manager = LeadsEntryDataManager::getInstance();
		$entry_ids = array();

		// get list of entries to remove
		$entries = $entries_manager->getItems(array('id'), array('type' => $id));

		if (count($entries) > 0)
			foreach ($entries as $entry)
				$entry_ids[] = $entry->id;

		// perform data removal
		if (count($entry_ids) > 0)
			$entry_data_manager->deleteData(array('entry' => $entry_ids));
		$entries_manager->deleteData(array('type' => $id));
		$type_manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$window_name = 'leads_types_delete';
		$params = array(
					'message'	=> $this->getLanguageConstant('message_types_deleted'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window_name).";".window_ReloadContent('leads_types')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save data from AJAX request
	 */
	private function saveFromAJAX() {
		$manager = LeadsEntriesManager::getInstance();
		$data_manager = LeadsEntryDataManager::getInstance();
		$type_manager = LeadsTypesManager::getInstance();
		$allow_only_one = false;
		$fields = array();
		$type = null;
		$type_id = null;

		if (isset($_REQUEST['type'])) {
			$type = $type_manager->getSingleItem(
										$type_manager->getFieldNames(),
										array('name' => fix_chars($_REQUEST['type']))
									);

			if (is_object($type))
				$type_id = $type->id;

		} else if (isset($_REQUEST['type_id'])) {
			$type_id = fix_id($_REQUEST['type_id']);
			$type = $type_manager->getSingleItem($type_manager->getFieldNames(), array('id' => $type_id));
		} 

		// we need a type in order to store data
		if (is_null($type_id)) {
			print json_encode(false);
			return;
		}

		// get setting if this type allows only one entry per address
		$allow_only_one = $type->unique_address == 1;
		$fields = explode(',', $type->fields);

		if ($allow_only_one) {
			// get existing entry from database 
			$entry = $manager->getSingleItem(
									$manager->getFieldNames(),
									array(
										'type'		=> $type_id,
										'address' 	=> $_SERVER['REMOTE_ADDR']
									)
								);

			// if entry doesn't exist, create new one
			if (!is_object($entry)) {
				$manager->insertData(array(
								'type'		=> $type_id,
								'address'	=> $_SERVER['REMOTE_ADDR'],
								'referral'	=> isset($_SESSION['lead_referer']) ? $_SESSION['lead_referer'] : ''
							));
				$id = $manager->getInsertedID();
				$entry = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

			} else {
				// entry already exists, we need to get out
				print json_encode(false);
				return;
			}

		} else {
			// create new entry anyway
			$manager->insertData(array(
							'type'		=> $type_id,
							'address'	=> $_SERVER['REMOTE_ADDR'],
							'referral'	=> isset($_SESSION['lead_referer']) ? $_SESSION['lead_referer'] : ''
						));
			$id = $manager->getInsertedID();
			$entry = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));
		}
		
		// prepare data for insertion
		$data = array();
		foreach ($_REQUEST as $key => $value) 
			if (in_array($key, $fields))
				$data[$key] = fix_chars($value);

		// store data
		foreach ($data as $key => $value)
			$data_manager->insertData(array(
					'entry'	=> $entry->id,
					'name'	=> $key,
					'value'	=> $value
				));

		// add referral url to mail body
		if (isset($_SESSION['lead_referer']))
			$data['referral'] = $_SESSION['lead_referer'];

		// send email if requested
		if ($type->send_email && class_exists('contact_form')) {
			$contact_form = contact_form::getInstance();
			$body = $contact_form->makePlainBody($data);
			$html_body = $contact_form->makeHtmlBody($data);
			
			$contact_form->sendFromModule(null, null, $body, $html_body);
		}

		print json_encode(true);
	}

	/**
	 * Handle drawing types tag
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_TypesList($tag_params, $children) {
		$manager = LeadsTypesManager::getInstance();
		$conditions = array();
		$selected = -1;

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// get selected item if specified
		if (isset($tag_params['selected']))
			$selected = fix_id($tag_params['selected']);

		// load template
		$template = $this->loadTemplate($tag_params, 'types_list_item.xml');

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
						'id'		=> $item->id,
						'name'		=> $item->name,
						'fields'	=> $item->fields,
						'selected'	=> $item->id == $selected ? 1 : 0,
						'item_change'	=> url_MakeHyperlink(
												$this->getLanguageConstant('change'),
												window_Open(
													'leads_types_change', 	// window id
													350,							// width
													$this->getLanguageConstant('title_types_change'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'types_change'),
														array('id', $item->id)
													)
												)
											),
						'item_delete'	=> url_MakeHyperlink(
												$this->getLanguageConstant('delete'),
												window_Open(
													'leads_types_delete', 	// window id
													300,							// width
													$this->getLanguageConstant('title_types_delete'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'types_delete'),
														array('id', $item->id)
													)
												)
											),
					);

				$template->setLocalParams($params);
				$template->restoreXML();
				$template->parse();
			}
	}

	/**
	 * Handle drawing results tag
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ResultsList($tag_params, $children) {
		$manager = LeadsEntriesManager::getInstance();
		$data_manager = LeadsEntryDataManager::getInstance();
		$types_manager = LeadsTypesManager::getInstance();
		$conditions = array();
		$type_id = null;

		if (isset($tag_params['type'])) {
			$type_id = fix_id($tag_params['type']);

		} else if (isset($_REQUEST['type'])) {
			$type_id = fix_id($_REQUEST['type']);
		}

		if (is_null($type_id))
			return;

		$fields = $types_manager->getItemValue('fields', array('id' => $type_id));
		$fields = array_slice(explode(',', $fields), 0, leads::COLUMN_COUNT);

		// add type to the conditions
		$conditions['type'] = $type_id;

		// get items from database
		$items = $manager->getItems(array('id'), $conditions);

		// load template
		$template = $this->loadTemplate($tag_params, 'list_item.xml');

		if (count($items) > 0) 
			// prepare list of entries
			$item_ids = array();
			$data = array();

			foreach ($items as $item)
				$item_ids[] = $item->id;

			// get data from database
			$raw_data = $data_manager->getItems(
							$data_manager->getFieldNames(), 
							array('entry' => $item_ids)
						);

			// pack data 
			if (count($raw_data) > 0) {
				foreach ($raw_data as $raw_item_data) {
					$id = $raw_item_data->entry;
					$name = $raw_item_data->name;

					if (!array_key_exists($id, $data))
						$data[$id] = array();

					if (in_array($name, $fields)) {
						$number = array_search($name, $fields);
						$data[$id]["field_{$number}"] = $raw_item_data->value;
					}
				}

				// display data
				foreach ($data as $id => $params) {
					$template->setLocalParams($params);
					$template->restoreXML();
					$template->parse();
				}
			}
	}

	/**
	 * Handle drawing columns
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ColumnsList($tag_params, $children) {
		$manager = LeadsTypesManager::getInstance();
		$id = null;

		if (isset($tag_params['type']))
			$id = fix_id($tag_params['type']);

		if (is_null($id))
			return;

		$type = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($type)) {
			$template = $this->loadTemplate($tag_params, 'column.xml');
			$fields = array_slice(explode(',', $type->fields), 0, leads::COLUMN_COUNT);

			foreach ($fields as $field) {
				$params = array(
						'name'	=> $field,
						'title'	=> ucwords($field)
					);

				$template->setLocalParams($params);
				$template->restoreXML();
				$template->parse();
			}
		}
	}
}
