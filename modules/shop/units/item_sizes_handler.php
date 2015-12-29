<?php

require_once('shop_item_sizes_manager.php');
require_once('shop_item_size_values_manager.php');

class ShopItemSizesHandler {
	private static $_instance;
	private $_parent;
	private $name;
	private $path;

	/**
	* Constructor
	*
	* @param object $parent
	*/
	protected function __construct($parent) {
		$this->_parent = $parent;
		$this->name = $this->_parent->name;
		$this->path = $this->_parent->path;
	}

	/**
	* Public function that creates a single instance
	*
	* @param object $parent
	* @return ShopItemSizesHandler
	*/
	public static function getInstance($parent) {
		if (!isset(self::$_instance))
		self::$_instance = new self($parent);

		return self::$_instance;
	}

	/**
	 * Transfer control to group
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params=array(), $children=array()) {
		$action = isset($params['sub_action']) ? $params['sub_action'] : null;

		switch ($action) {
			case 'add':
				$this->addItem();
				break;

			case 'save':
				$this->saveItem();
				break;

			case 'delete':
				$this->deleteItem();
				break;

			case 'delete_commit':
				$this->deleteItem_Commit();
				break;

			case 'values_show':
				$this->showValues();
				break;

			case 'value_add':
				$this->addValue();
				break;

			case 'value_change':
				$this->changeValue();
				break;

			case 'value_save':
				$this->saveValue();
				break;

			case 'value_delete':
				$this->deleteValue();
				break;

			case 'value_delete_commit':
				$this->deleteValue_Commit();
				break;

			default:
				$this->showItems();
				break;
		}
	}

	/**
	 * Show item sizes management form
	 */
	private function showItems() {
		$template = new TemplateHandler('size_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new' => url_MakeHyperlink(
										$this->_parent->getLanguageConstant('add_size_definition'),
										window_Open( // on click open window
											'shop_item_size_add',
											370,
											$this->_parent->getLanguageConstant('title_size_add'),
											true, true,
											backend_UrlMake($this->name, 'sizes', 'add')
										)
									)
					);

		// register tag handler
		$template->registerTagHandler('cms:size_list', $this, 'tag_SizeList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show item size values management form
	 */
	private function showValues() {
		$template = new TemplateHandler('values_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new' => url_MakeHyperlink(
										$this->_parent->getLanguageConstant('add_size_value'),
										window_Open( // on click open window
											'shop_item_size_values_add',
											370,
											$this->_parent->getLanguageConstant('title_size_value_add'),
											true, true,
											url_Make(
												'transfer_control',
												'backend_module',
												array('backend_action', 'sizes'),
												array('sub_action', 'value_add'),
												array('module', $this->_parent->name),
												array('definition', fix_id($_REQUEST['definition']))
											)
										)
									)
					);

		// register tag handler
		$template->registerTagHandler('cms:value_list', $this, 'tag_ValueList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for adding new shop item size
	 */
	private function addItem() {
		$template = new TemplateHandler('size_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'sizes', 'save'),
					'cancel_action'	=> window_Close('shop_item_size_add')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for adding new shop item size value
	 */
	private function addValue() {
		$template = new TemplateHandler('value_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'definition'	=> fix_id($_REQUEST['definition']),
					'form_action'	=> backend_UrlMake($this->name, 'sizes', 'value_save'),
					'cancel_action'	=> window_Close('shop_item_size_values_add')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for editing existing size value
	 */
	private function changeValue() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopItemSizeValuesManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			// create template
			$template = new TemplateHandler('value_change.xml', $this->path.'templates/');
			$template->setMappedModule($this->_parent->name);

			// prepare parameters
			$params = array(
						'id'			=> $item->id,
						'definition'	=> $item->definition,
						'value'			=> $item->value,
						'form_action'	=> backend_UrlMake($this->name, 'sizes', 'value_save'),
						'cancel_action'	=> window_Close('shop_item_size_values_change')
					);

			// parse template
			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save new or changed item data
	 */
	private function saveItem() {
		$manager = ShopItemSizesManager::getInstance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$name = fix_chars($_REQUEST['name']);

		if (is_null($id)) {
			$window = 'shop_item_size_add';
			$manager->insertData(array('name' => $name));
		} else {
			$window = 'shop_item_size_change';
			$manager->updateData(array('name' => $name), array('id' => $id));
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant('message_item_size_saved'),
					'button'	=> $this->_parent->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('shop_item_sizes').';'
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save new or changed item size value
	 */
	private function saveValue() {
		$manager = ShopItemSizeValuesManager::getInstance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$definition = isset($_REQUEST['definition']) ? fix_id($_REQUEST['definition']) : null;
		$value = $this->_parent->getMultilanguageField('value');

		$data = array(
					'definition'	=> $definition,
					'value'			=> $value
				);

		if (is_null($id)) {
			$manager->insertData($data);
			$window = 'shop_item_size_values_add';

		} else {
			$manager->updateData($data, array('id' => $id));
			$window = 'shop_item_size_values_change';
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant('message_item_size_value_saved'),
					'button'	=> $this->_parent->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('shop_item_size_values').';'
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();

	}

	/**
	 * Print confirmation form before removing size
	 */
	private function deleteItem() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopItemSizesManager::getInstance();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->_parent->name);

		$params = array(
					'message'		=> $this->_parent->getLanguageConstant("message_size_delete"),
					'name'			=> $item->name,
					'yes_text'		=> $this->_parent->getLanguageConstant("delete"),
					'no_text'		=> $this->_parent->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'shop_item_size_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'sizes'),
												array('sub_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('shop_item_size_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Perform size definition removal
	 */
	private function deleteItem_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopItemSizesManager::getInstance();
		$values_manager = ShopItemSizeValuesManager::getInstance();

		$manager->deleteData(array('id' => $id));
		$values_manager->deleteData(array('definition' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->_parent->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant("message_size_deleted"),
					'button'	=> $this->_parent->getLanguageConstant("close"),
					'action'	=> window_Close('shop_item_size_delete').";".window_ReloadContent('shop_item_sizes')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Print confirmation form before removing size value
	 */
	private function deleteValue() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = ShopItemSizeValuesManager::getInstance();

		$item = $manager->getSingleItem(array('value'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->_parent->name);

		$params = array(
					'message'		=> $this->_parent->getLanguageConstant("message_size_value_delete"),
					'name'			=> $item->value[$language],
					'yes_text'		=> $this->_parent->getLanguageConstant("delete"),
					'no_text'		=> $this->_parent->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'shop_item_size_values_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'sizes'),
												array('sub_action', 'value_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('shop_item_size_values_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Perform size value removal
	 */
	private function deleteValue_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopItemSizeValuesManager::getInstance();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->_parent->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant("message_size_value_deleted"),
					'button'	=> $this->_parent->getLanguageConstant("close"),
					'action'	=> window_Close('shop_item_size_values_delete').";".window_ReloadContent('shop_item_size_values')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Generate unique item Id 13 characters long
	 *
	 * @return string
	 */
	private function generateUID() {
		$manager = ShopItemManager::getInstance();

		// generate Id
		$uid = uniqid();

		// check if it already exists in database
		$count = $manager->sqlResult("SELECT count(*) FROM `shop_items` WHERE `uid`='{$uid}'");

		if ($count > 0)
			// given how high entropy is we will probably
			// never end up calling function again
			$uid = $self->generateUID();

		return $uid;
	}

	/**
	 * Handle drawing item sizes list
	 *
	 * @param array $tag_params
	 * @param array $chilren
	 */
	public function tag_SizeList($tag_params, $children) {
		$manager = ShopItemSizesManager::getInstance();
		$conditions = array();

		$selected = isset($tag_params['selected']) ? fix_id($tag_params['selected']) : -1;

		// get items
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// create template
		$template = $this->_parent->loadTemplate($tag_params, 'size_list_item.xml');
		$template->setTemplateParamsFromArray($children);

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
							'id'			=> $item->id,
							'name'			=> $item->name,
							'selected'		=> $selected,
							'item_delete'	=> url_MakeHyperlink(
													$this->_parent->getLanguageConstant('delete'),
													window_Open(
														'shop_item_size_delete', 	// window id
														400,				// width
														$this->_parent->getLanguageConstant('title_size_delete'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'sizes'),
															array('sub_action', 'delete'),
															array('id', $item->id)
														)
													)
												),
							'item_values'	=> url_MakeHyperlink(
													$this->_parent->getLanguageConstant('values'),
													window_Open(
														'shop_item_size_values', 	// window id
														400,				// width
														$this->_parent->getLanguageConstant('title_size_values'), // title
														true, true,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'sizes'),
															array('sub_action', 'values_show'),
															array('definition', $item->id)
														)
													)
												)
						);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Handle item size values tag
	 *
	 * @param array $tag_params
	 * @param array $childen
	 */
	public function tag_ValueList($tag_params, $children) {
		$manager = ShopItemSizeValuesManager::getInstance();
		$conditions = array();
		$selected = null;

		// create conditions
		if (isset($tag_params['definition']))
			$conditions['definition'] = fix_id($tag_params['definition']);

		// selected value
		if (isset($tag_params['selected']))
			$selected = fix_id($tag_params['selected']);

		// if no selected value was specified, select first element
		if (isset($tag_params['select_first']))
			$selected = 0;

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// create template
		$template = $this->_parent->loadTemplate($tag_params, 'values_list_item.xml');
		$template->setTemplateParamsFromArray($children);

		// parse template
		$counter = 0;
		if (count($items) > 0)
			foreach ($items as $item) {
				$counter++;

				// check if value should be selected
				$selected_value = false;

				if ($selected > 0)
					$selected_value = $item->id == $selected; else
					$selected_value = $selected == 0 && $counter == 1;

				// prepare parameters
				$params = array(
							'id'			=> $item->id,
							'value'			=> $item->value,
							'selected'		=> $selected_value,
							'item_change'	=> url_MakeHyperlink(
													$this->_parent->getLanguageConstant('change'),
													window_Open(
														'shop_item_size_values_change', 	// window id
														370,				// width
														$this->_parent->getLanguageConstant('title_size_value_change'), // title
														true, true,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'sizes'),
															array('sub_action', 'value_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> url_MakeHyperlink(
													$this->_parent->getLanguageConstant('delete'),
													window_Open(
														'shop_item_size_values_delete', 	// window id
														400,				// width
														$this->_parent->getLanguageConstant('title_size_value_delete'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'sizes'),
															array('sub_action', 'value_delete'),
															array('id', $item->id)
														)
													)
												)
						);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

}

?>
