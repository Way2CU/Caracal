<?php

require_once('item_sizes_manager.php');
require_once('item_size_values_manager.php');

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
	public static function get_instance($parent) {
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
	public function transfer_control($params=array(), $children=array()) {
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
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new' => url_MakeHyperlink(
										$this->_parent->get_language_constant('add_size_definition'),
										window_Open( // on click open window
											'shop_item_size_add',
											370,
											$this->_parent->get_language_constant('title_size_add'),
											true, true,
											backend_UrlMake($this->name, 'sizes', 'add')
										)
									)
					);

		// register tag handler
		$template->register_tag_handler('cms:size_list', $this, 'tag_SizeList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show item size values management form
	 */
	private function showValues() {
		$template = new TemplateHandler('values_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new' => url_MakeHyperlink(
										$this->_parent->get_language_constant('add_size_value'),
										window_Open( // on click open window
											'shop_item_size_values_add',
											370,
											$this->_parent->get_language_constant('title_size_value_add'),
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
		$template->register_tag_handler('cms:value_list', $this, 'tag_ValueList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for adding new shop item size
	 */
	private function addItem() {
		$template = new TemplateHandler('size_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'sizes', 'save'),
					'cancel_action'	=> window_Close('shop_item_size_add')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for adding new shop item size value
	 */
	private function addValue() {
		$template = new TemplateHandler('value_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'definition'	=> fix_id($_REQUEST['definition']),
					'form_action'	=> backend_UrlMake($this->name, 'sizes', 'value_save'),
					'cancel_action'	=> window_Close('shop_item_size_values_add')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for editing existing size value
	 */
	private function changeValue() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopItemSizeValuesManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (is_object($item)) {
			// create template
			$template = new TemplateHandler('value_change.xml', $this->path.'templates/');
			$template->set_mapped_module($this->_parent->name);

			// prepare parameters
			$params = array(
						'id'			=> $item->id,
						'definition'	=> $item->definition,
						'value'			=> $item->value,
						'form_action'	=> backend_UrlMake($this->name, 'sizes', 'value_save'),
						'cancel_action'	=> window_Close('shop_item_size_values_change')
					);

			// parse template
			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Save new or changed item data
	 */
	private function saveItem() {
		$manager = ShopItemSizesManager::get_instance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$name = fix_chars($_REQUEST['name']);

		if (is_null($id)) {
			$window = 'shop_item_size_add';
			$manager->insert_item(array('name' => $name));
		} else {
			$window = 'shop_item_size_change';
			$manager->update_items(array('name' => $name), array('id' => $id));
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->_parent->get_language_constant('message_item_size_saved'),
					'button'	=> $this->_parent->get_language_constant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('shop_item_sizes').';'
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save new or changed item size value
	 */
	private function saveValue() {
		$manager = ShopItemSizeValuesManager::get_instance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$definition = isset($_REQUEST['definition']) ? fix_id($_REQUEST['definition']) : null;
		$value = $this->_parent->get_multilanguage_field('value');

		$data = array(
					'definition'	=> $definition,
					'value'			=> $value
				);

		if (is_null($id)) {
			$manager->insert_item($data);
			$window = 'shop_item_size_values_add';

		} else {
			$manager->update_items($data, array('id' => $id));
			$window = 'shop_item_size_values_change';
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->_parent->get_language_constant('message_item_size_value_saved'),
					'button'	=> $this->_parent->get_language_constant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('shop_item_size_values').';'
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();

	}

	/**
	 * Print confirmation form before removing size
	 */
	private function deleteItem() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopItemSizesManager::get_instance();

		$item = $manager->get_single_item(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->_parent->name);

		$params = array(
					'message'		=> $this->_parent->get_language_constant("message_size_delete"),
					'name'			=> $item->name,
					'yes_text'		=> $this->_parent->get_language_constant("delete"),
					'no_text'		=> $this->_parent->get_language_constant("cancel"),
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

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Perform size definition removal
	 */
	private function deleteItem_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopItemSizesManager::get_instance();
		$values_manager = ShopItemSizeValuesManager::get_instance();

		$manager->delete_items(array('id' => $id));
		$values_manager->delete_items(array('definition' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->_parent->name);

		$params = array(
					'message'	=> $this->_parent->get_language_constant("message_size_deleted"),
					'button'	=> $this->_parent->get_language_constant("close"),
					'action'	=> window_Close('shop_item_size_delete').";".window_ReloadContent('shop_item_sizes')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Print confirmation form before removing size value
	 */
	private function deleteValue() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = ShopItemSizeValuesManager::get_instance();

		$item = $manager->get_single_item(array('value'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->_parent->name);

		$params = array(
					'message'		=> $this->_parent->get_language_constant("message_size_value_delete"),
					'name'			=> $item->value[$language],
					'yes_text'		=> $this->_parent->get_language_constant("delete"),
					'no_text'		=> $this->_parent->get_language_constant("cancel"),
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

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Perform size value removal
	 */
	private function deleteValue_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopItemSizeValuesManager::get_instance();

		$manager->delete_items(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->_parent->name);

		$params = array(
					'message'	=> $this->_parent->get_language_constant("message_size_value_deleted"),
					'button'	=> $this->_parent->get_language_constant("close"),
					'action'	=> window_Close('shop_item_size_values_delete').";".window_ReloadContent('shop_item_size_values')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Generate unique item Id 13 characters long
	 *
	 * @return string
	 */
	private function generateUID() {
		$manager = ShopItemManager::get_instance();

		// generate Id
		$uid = uniqid();

		// check if it already exists in database
		$count = $manager->get_result("SELECT count(*) FROM `shop_items` WHERE `uid`='{$uid}'");

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
		$manager = ShopItemSizesManager::get_instance();
		$conditions = array();

		$selected = isset($tag_params['selected']) ? fix_id($tag_params['selected']) : -1;

		// get items
		$items = $manager->get_items($manager->get_field_names(), $conditions);

		// create template
		$template = $this->_parent->load_template($tag_params, 'size_list_item.xml');
		$template->set_template_params_from_array($children);

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
							'id'			=> $item->id,
							'name'			=> $item->name,
							'selected'		=> $selected,
							'item_delete'	=> url_MakeHyperlink(
													$this->_parent->get_language_constant('delete'),
													window_Open(
														'shop_item_size_delete', 	// window id
														400,				// width
														$this->_parent->get_language_constant('title_size_delete'), // title
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
													$this->_parent->get_language_constant('values'),
													window_Open(
														'shop_item_size_values', 	// window id
														400,				// width
														$this->_parent->get_language_constant('title_size_values'), // title
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

				$template->restore_xml();
				$template->set_local_params($params);
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
		$manager = ShopItemSizeValuesManager::get_instance();
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
		$items = $manager->get_items($manager->get_field_names(), $conditions);

		// create template
		$template = $this->_parent->load_template($tag_params, 'values_list_item.xml');
		$template->set_template_params_from_array($children);

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
													$this->_parent->get_language_constant('change'),
													window_Open(
														'shop_item_size_values_change', 	// window id
														370,				// width
														$this->_parent->get_language_constant('title_size_value_change'), // title
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
													$this->_parent->get_language_constant('delete'),
													window_Open(
														'shop_item_size_values_delete', 	// window id
														400,				// width
														$this->_parent->get_language_constant('title_size_value_delete'), // title
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

				$template->restore_xml();
				$template->set_local_params($params);
				$template->parse();
			}
	}

}

?>
