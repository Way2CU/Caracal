<?php

require_once('shop_delivery_methods_manager.php');

class ShopDeliveryMethodsHandler {
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
	public function transferControl($params = array(), $children = array()) {
		$action = isset($params['sub_action']) ? $params['sub_action'] : null;

		switch ($action) {
			case 'add':
				$this->addMethod();
				break;

			case 'change':
				$this->changeMethod();
				break;

			case 'save':
				$this->saveMethod();
				break;

			case 'delete':
				$this->deleteMethod();
				break;

			case 'delete_commit':
				$this->deleteMethod_Commit();
				break;

			case 'prices':
				$this->showPrices();
				break;

			case 'add_price':
				$this->addPrice();
				break;

			case 'change_price':
				$this->changePrice();
				break;

			case 'save_price':
				$this->savePrice();
				break;

			case 'delete_price':
				$this->deletePrice();
				break;

			case 'delete_price_commit':
				$this->deletePrice_Commit();
				break;

			default:
				$this->showMethods();
				break;
		}
	}

	/**
	 * Show delivery methods form
	 */
	private function showMethods() {
		$template = new TemplateHandler('delivery_methods_list.xml', $this->path.'templates/');

		$params = array(
					'link_new' => url_MakeHyperlink(
										$this->_parent->getLanguageConstant('add_delivery_method'),
										window_Open( // on click open window
											'shop_delivery_method_add',
											370,
											$this->_parent->getLanguageConstant('title_delivery_method_add'),
											true, true,
											backend_UrlMake($this->name, 'delivery_methods', 'add')
										)
									)
				);

		// register tag handler
		$template->registerTagHandler('_delivery_methods', &$this, 'tag_DeliveryMethodsList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for adding new delivery method
	 */
	private function addMethod() {
		$template = new TemplateHandler('delivery_method_add.xml', $this->path.'templates/');

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'delivery_methods', 'save'),
					'cancel_action'	=> window_Close('shop_delivery_method_add')
				);

		$template->setLocalParams($params);
		$template->restoreXML();
		$template->parse();
	}

	/**
	 * Show form for changing delivery method data
	 */
	private function changeMethod() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopDeliveryMethodsManager::getInstance();

		$method = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($method)) {
			$template = new TemplateHandler('delivery_method_change.xml', $this->path.'templates/');

			$params = array(
					'id'			=> $method->id,
					'name'			=> $method->name,
					'international'	=> $method->international,
					'domestic'		=> $method->domestic,
					'form_action'	=> backend_UrlMake($this->name, 'delivery_methods', 'save'),
					'cancel_action'	=> window_Close('shop_delivery_method_change')
				);

			$template->setLocalParams($params);
			$template->restoreXML();
			$template->parse();
		}
	}

	/**
	 * Save new or changed method data
	 */
	private function saveMethod() {
		$manager = ShopDeliveryMethodsManager::getInstance();
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;

		$data = array(
				'name'			=> $this->_parent->getMultilanguageField('name'),
				'international'	=> isset($_REQUEST['international']) && ($_REQUEST['international'] == 'on' || $_REQUEST['international'] == '1') ? 1 : 0,
				'domestic'		=> isset($_REQUEST['domestic']) && ($_REQUEST['domestic'] == 'on' || $_REQUEST['domestic'] == '1') ? 1 : 0,
			);

		if (is_null($id)) {
			$manager->insertData($data);
			$window = 'shop_delivery_method_add';

		} else {
			$manager->updateData($data, array('id' => $id));
			$window = 'shop_delivery_method_change';
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant('message_delivery_method_saved'),
					'button'	=> $this->_parent->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('shop_delivery_methods')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation form for removing delivery method
	 */
	private function deleteMethod() {
		global $language;

		$manager = ShopDeliveryMethodsManager::getInstance();
		$id = fix_id($_REQUEST['id']);

		$method = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->_parent->name);

		$params = array(
					'message'		=> $this->_parent->getLanguageConstant("message_delivery_method_delete"),
					'name'			=> $method->name[$language],
					'yes_text'		=> $this->_parent->getLanguageConstant("delete"),
					'no_text'		=> $this->_parent->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'shop_delivery_method_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'delivery_methods'),
												array('sub_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('shop_delivery_method_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();		
	}

	/**
	 * Perform delivery method removal
	 */
	private function deleteMethod_Commit() {
		$manager = ShopDeliveryMethodsManager::getInstance();
		$prices_manager = ShopDeliveryMethodPricesManager::getInstance();
		$id = fix_id($_REQUEST['id']);

		$manager->deleteData(array('id' => $id));
		$prices_manager->deleteData(array('method' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->_parent->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant("message_delivery_method_deleted"),
					'button'	=> $this->_parent->getLanguageConstant("close"),
					'action'	=> window_Close('shop_delivery_method_delete').";".window_ReloadContent('shop_delivery_methods')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();		
	}

	/**
	 * Show list of prices for specified method
	 */
	private function showPrices() {
		$manager = ShopDeliveryMethodPricesManager::getInstance();
		$id = fix_id($_REQUEST['id']);

		$params = array(
				'method'	=> $id,
				'link_new' => url_MakeHyperlink(
									$this->_parent->getLanguageConstant('add_delivery_price'),
									window_Open( // on click open window
										'shop_delivery_price_add',
										370,
										$this->_parent->getLanguageConstant('title_delivery_method_price_add'),
										true, true,
										url_Make(
											'transfer_control',
											'backend_module',
											array('module', $this->name),
											array('backend_action', 'delivery_methods'),
											array('sub_action', 'add_price'),
											array('id', $id)
										)
									)
								)
			);

		$template = new TemplateHandler('delivery_method_prices_list.xml', $this->path.'templates/');

		// register tag handler
		$template->registerTagHandler('_delivery_prices', &$this, 'tag_DeliveryPricesList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for adding price to a method
	 */
	private function addPrice() {
		$template = new TemplateHandler('delivery_method_price_add.xml', $this->path.'templates/');

		$params = array(
			'method'		=> fix_id($_REQUEST['id']),
			'form_action'	=> backend_UrlMake($this->name, 'delivery_methods', 'save_price'),
			'cancel_action'	=> window_Close('shop_delivery_price_add')
		);

		$template->setLocalParams($params);
		$template->restoreXML();
		$template->parse();
	}

	/**
	 * Show form for changing price
	 */
	private function changePrice() {
		$manager = ShopDeliveryMethodPricesManager::getInstance();
		$id = fix_id($_REQUEST['id']);
		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		$template = new TemplateHandler('delivery_method_price_change.xml', $this->path.'templates/');

		$params = array(
			'id'			=> $item->id,
			'value'			=> $item->value,
			'method'		=> $item->method,
			'form_action'	=> backend_UrlMake($this->name, 'delivery_methods', 'save_price'),
			'cancel_action'	=> window_Close('shop_delivery_price_change')
		);

		$template->setLocalParams($params);
		$template->restoreXML();
		$template->parse();
	}

	/**
	 * Save new price for delivery method
	 */
	private function savePrice() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = ShopDeliveryMethodPricesManager::getInstance();

		$data = array(
			'value'		=> fix_chars($_REQUEST['value'])
		);

		// method is optional when editing
		if (isset($_REQUEST['method']))
			$data['method'] = fix_id($_REQUEST['method']);

		if (is_null($id)) {
			$manager->insertData($data);
			$window = 'shop_delivery_price_add';

		} else {
			$manager->updateData($data, array('id' => $id));
			$window = 'shop_delivery_price_change';
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant('message_delivery_price_saved'),
					'button'	=> $this->_parent->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('shop_delivery_method_prices')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation form for price removal
	 */
	private function deletePrice() {
		global $language;

		$manager = ShopDeliveryMethodPricesManager::getInstance();
		$id = fix_id($_REQUEST['id']);

		$item = $manager->getSingleItem(array('value'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->_parent->name);

		$params = array(
					'message'		=> $this->_parent->getLanguageConstant("message_delivery_price_delete"),
					'name'			=> $item->value,
					'yes_text'		=> $this->_parent->getLanguageConstant("delete"),
					'no_text'		=> $this->_parent->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'shop_delivery_price_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'delivery_methods'),
												array('sub_action', 'delete_price_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('shop_delivery_price_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();		
	}

	/**
	 * Perform price removal
	 */
	private function deletePrice_Commit() {
		$manager = ShopDeliveryMethodPricesManager::getInstance();
		$id = fix_id($_REQUEST['id']);

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->_parent->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant("message_delivery_price_deleted"),
					'button'	=> $this->_parent->getLanguageConstant("close"),
					'action'	=> window_Close('shop_delivery_price_delete').";".window_ReloadContent('shop_delivery_method_prices')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();		
	}

	/**
	 * Handle drawing list of delivery methods
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_DeliveryMethodsList($tag_params, $children) {
		$manager = ShopDeliveryMethodsManager::getInstance();
		$conditions = array();
		$item_id = -1;
		$selected = -1;

		if (isset($tag_params['item']))
			$item_id = fix_id($tag_params['item']);

		if (isset($tag_params['selected']))
			$selected = fix_id($tag_params['selected']);

		// delivery method list needs to be filtered by the items in shooping cart
		if (isset($tag_params['shopping_cart']) && $tag_params['shopping_cart'] == 1) {
			$relations_manager = ShopDeliveryItemRelationsManager::getInstance();
			$prices_manager = ShopDeliveryMethodPricesManager::getInstance();
			$items_manager = ShopItemManager::getInstance();

			$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
			$uid_list = array_keys($cart);

			if (count($uid_list) == 0)
				return;

			// shopping cart contains only UIDs, we need IDs
			$id_list = array();
			$items = $items_manager->getItems(array('id'), array('uid' => $uid_list));

			if (count($items) > 0)
				foreach ($items as $item)
					$id_list[] = $item->id;

			// get item relations to delivery methods
			$relations = $relations_manager->getItems(
								$relations_manager->getFieldNames(), 
								array('item' => $id_list)
							);

			$price_list = array();

			if (count($relations) > 0)
				foreach ($relations as $relation) 
					$price_list[] = $relation->price;

			$relations = $prices_manager->getItems(array('method'), array('id' => $price_list));
			$method_count = array();

			if (count($relations) > 0)
				foreach ($relations as $relation) {
					$key = $relation->method;

					if (!array_key_exists($key, $method_count))
						$method_count[$key] = 0;

					$method_count[$key]++;
				}

			// We compare number of items with method associated with
			// that item. Methods that have number same as number of items
			// are supported and we include them in list.
			$border_count = count($id_list);
			$valid_methods = array();

			if (count($method_count) > 0)
				foreach ($method_count as $id => $count) 
					if ($count == $border_count)
						$valid_methods[] = $id;

			if (count($valid_methods) > 0) 
				$conditions['id'] = $valid_methods; else
				$conditions ['id'] = -1;

			// filter by location
			$shop_location = isset($this->_parent->settings['shop_location']) ? $this->_parent->settings['shop_location'] : '';

			if (!empty($shop_location)) {
				$same_country = $shop_location == $_SESSION['buyer']['country'];

				if ($same_country)
					$conditions['domestic'] = 1; else
					$conditions['international'] = 1;
			}
		}

		// get template
		$template = $this->_parent->loadTemplate($tag_params, 'delivery_methods_list_item.xml');
		$template->registerTagHandler('_price_list', &$this, 'tag_DeliveryPricesList');

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		if (count($items) > 0) 
			foreach($items as $item) {
				$params = array(
					'id'					=> $item->id,
					'name'					=> $item->name,
					'international'			=> $item->international,
					'international_char'	=> $item->international ? CHAR_CHECKED : CHAR_UNCHECKED,
					'domestic'				=> $item->domestic,
					'domestic_char'			=> $item->domestic ? CHAR_CHECKED : CHAR_UNCHECKED,
					'item'					=> $item_id,
					'selected'				=> $selected == $item->id ? 1 : 0,
					'item_change'	=> url_MakeHyperlink(
						$this->_parent->getLanguageConstant('change'),
						window_Open(
							'shop_delivery_method_change', 	// window id
							370,				// width
							$this->_parent->getLanguageConstant('title_delivery_method_change'), // title
							true, true,
							url_Make(
								'transfer_control',
								'backend_module',
								array('module', $this->name),
								array('backend_action', 'delivery_methods'),
								array('sub_action', 'change'),
								array('id', $item->id)
							)
						)
					),
					'item_delete'	=> url_MakeHyperlink(
						$this->_parent->getLanguageConstant('delete'),
						window_Open(
							'shop_delivery_method_delete', 	// window id
							400,				// width
							$this->_parent->getLanguageConstant('title_delivery_method_delete'), // title
							false, false,
							url_Make(
								'transfer_control',
								'backend_module',
								array('module', $this->name),
								array('backend_action', 'delivery_methods'),
								array('sub_action', 'delete'),
								array('id', $item->id)
							)
						)
					),
					'item_prices'	=> url_MakeHyperlink(
						$this->_parent->getLanguageConstant('prices'),
						window_Open(
							'shop_delivery_method_prices', 	// window id
							370,				// width
							$this->_parent->getLanguageConstant('title_delivery_method_prices'), // title
							true, false,
							url_Make(
								'transfer_control',
								'backend_module',
								array('module', $this->name),
								array('backend_action', 'delivery_methods'),
								array('sub_action', 'prices'),
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

	/**
	 * Handle drawing list of delivery method prices
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_DeliveryPricesList($tag_params, $children) {
		$manager = ShopDeliveryMethodPricesManager::getInstance();
		$conditions = array();
		$relations = array();

		// prepare filtering conditions
		if (isset($tag_params['method'])) 
			$conditions['method'] = fix_id($tag_params['method']);

		if (isset($_REQUEST['method']))
			$conditions['method'] = fix_id($_REQUEST['method']);

		// get relations with shop item
		if (isset($tag_params['item'])) {
			$relations_manager = ShopDeliveryItemRelationsManager::getInstance();
			$item_id = fix_id($tag_params['item']);

			$raw_relations = $relations_manager->getItems(array('price'), array('item' => $item_id));

			if (count($raw_relations) > 0)
				foreach ($raw_relations as $relation)
					$relations[] = $relation->price;
		}

		// get template
		$template = $this->_parent->loadTemplate($tag_params, 'delivery_method_prices_list_item.xml');

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
						'id'		=> $item->id,
						'value'		=> $item->value,
						'method'	=> isset($conditions['method']) ? $conditions['method'] : 0,
						'selected'	=> in_array($item->id, $relations) ? 1 : 0,
						'item_change'	=> url_MakeHyperlink(
							$this->_parent->getLanguageConstant('change'),
							window_Open(
								'shop_delivery_price_change', 	// window id
								370,				// width
								$this->_parent->getLanguageConstant('title_delivery_method_price_change'), // title
								true, true,
								url_Make(
									'transfer_control',
									'backend_module',
									array('module', $this->name),
									array('backend_action', 'delivery_methods'),
									array('sub_action', 'change_price'),
									array('id', $item->id)
								)
							)
						),
						'item_delete'	=> url_MakeHyperlink(
							$this->_parent->getLanguageConstant('delete'),
							window_Open(
								'shop_delivery_price_delete', 	// window id
								400,				// width
								$this->_parent->getLanguageConstant('title_delivery_method_price_delete'), // title
								false, false,
								url_Make(
									'transfer_control',
									'backend_module',
									array('module', $this->name),
									array('backend_action', 'delivery_methods'),
									array('sub_action', 'delete_price'),
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
}

?>
