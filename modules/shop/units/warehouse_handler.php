<?php

/**
 * Handle shop warehouses
 */

require_once('warehouse_manager.php');


class ShopWarehouseHandler {
	private static $_instance;
	private $_parent;
	private $name;
	private $path;

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		$this->_parent = $parent;
		$this->name = $this->_parent->name;
		$this->path = $this->_parent->path;
	}

	/**
	 * Public function that creates a single instance
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
	public function transfer_control($params = array(), $children = array()) {
		$action = isset($params['sub_action']) ? $params['sub_action'] : null;

		switch ($action) {
			case 'add':
				$this->addWarehouse();
				break;

			case 'change':
				$this->changeWarehouse();
				break;

			case 'save':
				$this->saveWarehouse();
				break;

			case 'delete':
				$this->deleteWarehouse();
				break;

			case 'delete_commit':
				$this->deleteWarehouse_Commit();
				break;

			default:
				$this->showWarehouses();
				break;
		}
	}

	/**
	 * Show list of discounts
	 */
	private function showWarehouses() {
		$template = new TemplateHandler('warehouse_list.xml', $this->path.'templates/');

		$params = array(
					'warehouse_new' => url_MakeHyperlink(
										$this->_parent->get_language_constant('add_warehouse'),
										window_Open( // on click open window
											'shop_warehouse_add',
											300,
											$this->_parent->get_language_constant('title_warehouse_add'),
											true, true,
											backend_UrlMake($this->name, 'warehouses', 'add')
										)
									),
					);

		$template->registerTagHandler('cms:warehouse_list', $this, 'tag_WarehouseList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for adding new discount
	 */
	private function addWarehouse() {
		$template = new TemplateHandler('warehouse_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'warehouses', 'save'),
					'cancel_action'	=> window_Close('shop_warehouse_add')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for changing existing discount
	 */
	private function changeWarehouse() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopWarehouseManager::getInstance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('warehouse_change.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
					'id'		=> $item->id,
					'name'		=> $item->name,
					'street'	=> $item->street,
					'street2'	=> $item->street2,
					'city'		=> $item->city,
					'zip'		=> $item->zip,
					'country'	=> $item->country,
					'state'		=> $item->state,
					'form_action'	=> backend_UrlMake($this->name, 'warehouses', 'save'),
					'cancel_action'	=> window_Close('shop_warehouse_change')
				);

			$template->setLocalParams($params);
			$template->restoreXML();
			$template->parse();
		}
	}

	/**
	 * Save new or changed discount data
	 */
	private function saveWarehouse() {
		// get data
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$name = fix_chars($_REQUEST['name']);
		$street = fix_chars($_REQUEST['street']);
		$street2 = fix_chars($_REQUEST['street2']);
		$city = fix_chars($_REQUEST['city']);
		$zip = fix_chars($_REQUEST['zip']);
		$country = fix_chars($_REQUEST['country']);
		$state = fix_chars($_REQUEST['state']);

		$data = array(
				'name'		=> $name,
				'street'	=> $street,
				'street2'	=> $street2,
				'city'		=> $city,
				'zip'		=> $zip,
				'country'	=> $country,
				'state'		=> $state
			);

		// get instance of warehouse manager
		$manager = ShopWarehouseManager::getInstance();

		if (is_null($id)) {
			$manager->insert_item($data);
			$window = 'shop_warehouse_add';

		} else {
			$manager->update_items($data, array('id' => $id));
			$window = 'shop_warehouse_change';
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->_parent->get_language_constant('message_warehouse_saved'),
					'button'	=> $this->_parent->get_language_constant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('shop_warehouses')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * show confirmation form before deleting discount
	 */
	private function deleteWarehouse() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopWarehouseManager::getInstance();

		$item = $manager->get_single_item(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->_parent->name);

		$params = array(
					'message'		=> $this->_parent->get_language_constant("message_warehouse_delete"),
					'name'			=> $item->name,
					'yes_text'		=> $this->_parent->get_language_constant("delete"),
					'no_text'		=> $this->_parent->get_language_constant("cancel"),
					'yes_action'	=> window_LoadContent(
											'shop_warehouse_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'warehouses'),
												array('sub_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('shop_warehouse_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * actually delete discount from the system
	 */
	private function deleteWarehouse_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopWarehouseManager::getInstance();

		$manager->delete_items(array('id' => $id));

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->_parent->name);

		$params = array(
					'message'	=> $this->_parent->get_language_constant("message_warehouse_deleted"),
					'button'	=> $this->_parent->get_language_constant("close"),
					'action'	=> window_Close('shop_warehouse_delete').";".window_ReloadContent('shop_warehouses')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Handle tag list tag drawing
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Warehouse($tag_params, $children) {
	}

	/**
	 * Handle tag list tag drawing
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_WarehouseList($tag_params, $children) {
		$manager = ShopWarehouseManager::getInstance();
		$conditions = array();

		$template = $this->_parent->load_template($tag_params, 'warehouse_list_item.xml');
		$template->setTemplateParamsFromArray($children);

		$items = $manager->get_items($manager->get_field_names(), $conditions);

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
						'name'		=> $item->name,
						'street'	=> $item->street,
						'street2'	=> $item->street2,
						'city'		=> $item->city,
						'zip'		=> $item->zip,
						'country'	=> $item->country,
						'state'		=> $item->state,
						'item_change'	=> url_MakeHyperlink(
												$this->_parent->get_language_constant('change'),
												window_Open(
													'shop_warehouse_change', 	// window id
													300,				// width
													$this->_parent->get_language_constant('title_warehouse_change'), // title
													true, true,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'warehouses'),
														array('sub_action', 'change'),
														array('id', $item->id)
													)
												)
											),
						'item_delete'	=> url_MakeHyperlink(
												$this->_parent->get_language_constant('delete'),
												window_Open(
													'shop_warehouse_delete', 	// window id
													400,				// width
													$this->_parent->get_language_constant('title_warehouse_delete'), // title
													false, false,
													url_Make(
														'transfer_control',
														'backend_module',
														array('module', $this->name),
														array('backend_action', 'warehouses'),
														array('sub_action', 'delete'),
														array('id', $item->id)
													)
												)
											)
					);

				$template->setLocalParams($params);
				$template->restoreXML();
				$template->parse();
			}
	}

}

?>
