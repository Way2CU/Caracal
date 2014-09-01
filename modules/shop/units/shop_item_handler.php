<?php

require_once('shop_item_manager.php');
require_once('shop_item_membership_manager.php');
require_once('shop_category_manager.php');
require_once('shop_related_items_manager.php');

class ShopItemHandler {
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
				$this->addItem();
				break;

			case 'change':
				$this->changeItem();
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

			case 'search_results':
				$this->showSearchResults();
				break;

			default:
				$this->showItems();
				break;
		}
	}

	/**
	 * Show items management form
	 */
	private function showItems() {
		$template = new TemplateHandler('item_list.xml', $this->path.'templates/');

		$params = array(
					'link_new' => url_MakeHyperlink(
										$this->_parent->getLanguageConstant('add_item'),
										window_Open( // on click open window
											'shop_item_add',
											505,
											$this->_parent->getLanguageConstant('title_item_add'),
											true, true,
											backend_UrlMake($this->name, 'items', 'add')
										)
									),
					'link_categories' => url_MakeHyperlink(
										$this->_parent->getLanguageConstant('manage_categories'),
										window_Open( // on click open window
											'shop_categories',
											580,
											$this->_parent->getLanguageConstant('title_manage_categories'),
											true, true,
											backend_UrlMake($this->name, 'categories')
										)
									)
					);

		// register tag handler
		$template->registerTagHandler('_item_list', $this, 'tag_ItemList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for adding new shop item
	 */
	private function addItem() {
		$template = new TemplateHandler('item_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'items', 'save'),
					'cancel_action'	=> window_Close('shop_item_add')
				);

		// register external tag handlers
		$category_handler = ShopCategoryHandler::getInstance($this->_parent);
		$template->registerTagHandler('_category_list', $category_handler, 'tag_CategoryList');
		
		$size_handler = ShopItemSizesHandler::getInstance($this->_parent);
		$template->registerTagHandler('_size_list', $size_handler, 'tag_SizeList');

		$manufacturer_handler = ShopManufacturerHandler::getInstance($this->_parent);
		$template->registerTagHandler('_manufacturer_list', $manufacturer_handler, 'tag_ManufacturerList');

		$delivery_handler = ShopDeliveryMethodsHandler::getInstance($this->_parent);
		$template->registerTagHandler('_delivery_methods', $delivery_handler, 'tag_DeliveryMethodsList');

		$template->registerTagHandler('_item_list', $this, 'tag_ItemList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for editing existing shop item
	 */
	private function changeItem() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopItemManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			// create template
			$template = new TemplateHandler('item_change.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			// register tag handlers
			$category_handler = ShopCategoryHandler::getInstance($this->_parent);
			$template->registerTagHandler('_category_list', $category_handler, 'tag_CategoryList');
			
			$size_handler = ShopItemSizesHandler::getInstance($this->_parent);
			$template->registerTagHandler('_size_list', $size_handler, 'tag_SizeList');

			$manufacturer_handler = ShopManufacturerHandler::getInstance($this->_parent);
			$template->registerTagHandler('_manufacturer_list', $manufacturer_handler, 'tag_ManufacturerList');

			$delivery_handler = ShopDeliveryMethodsHandler::getInstance($this->_parent);
			$template->registerTagHandler('_delivery_methods', $delivery_handler, 'tag_DeliveryMethodsList');

			$template->registerTagHandler('_item_list', $this, 'tag_ItemList');

			// prepare parameters
			$params = array(
						'id'			=> $item->id,
						'uid'			=> $item->uid,
						'name'			=> $item->name,
						'description'	=> $item->description,
						'gallery'		=> $item->gallery,
						'manufacturer'	=> $item->manufacturer,
						'size_definition'=> $item->size_definition,
						'author'		=> $item->author,
						'views'			=> $item->views,
						'price'			=> $item->price,
						'colors'		=> $item->colors,
						'tax'			=> $item->tax,
						'weight'		=> $item->weight,
						'votes_up'		=> $item->votes_up,
						'votes_down'	=> $item->votes_down,
						'priority'		=> $item->priority,
						'timestamp'		=> $item->timestamp,
						'visible'		=> $item->visible,
						'deleted'		=> $item->deleted,
						'form_action'	=> backend_UrlMake($this->name, 'items', 'save'),
						'cancel_action'	=> window_Close('shop_item_change')
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
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = ShopItemManager::getInstance();
		$membership_manager = ShopItemMembershipManager::getInstance();
		$related_items_manager = ShopRelatedItemsManager::getInstance();
		$delivery_item_relation_manager = ShopDeliveryItemRelationsManager::getInstance();
		$open_editor = "";

		$new_item = is_null($id);

		$data = array(
				'name'				=> $this->_parent->getMultilanguageField('name'),
				'description'		=> $this->_parent->getMultilanguageField('description'),
				'price'				=> isset($_REQUEST['price']) && !empty($_REQUEST['price']) ? fix_chars($_REQUEST['price']) : 0,
				'colors'			=> fix_chars($_REQUEST['colors']),
				'tax'				=> isset($_REQUEST['tax']) && !empty($_REQUEST['tax']) ? fix_chars($_REQUEST['tax']) : 0,
				'weight'			=> isset($_REQUEST['weight']) && !empty($_REQUEST['weight']) ? fix_chars($_REQUEST['weight']) : 0,
				'size_definition'	=> isset($_REQUEST['size_definition']) ? fix_id($_REQUEST['size_definition']) : null,
				'priority'			=> isset($_REQUEST['priority']) ? fix_id($_REQUEST['priority']) : 5,
				'manufacturer'		=> isset($_REQUEST['manufacturer']) && !empty($_REQUEST['manufacturer']) ? fix_id($_REQUEST['manufacturer']) : 0
			);
		
		if ($new_item) {
			// add elements first time
			$data['author'] = $_SESSION['uid'];
			$data['uid'] = $this->generateUID();

			if (class_exists('gallery')) {
				$gallery = gallery::getInstance();
				$gallery_id = $gallery->createGallery($data['name']);
				$data['gallery'] = $gallery_id;

				// create action for opening gallery editor
				$open_editor = window_Open(
									'gallery_images',
									670,
									$gallery->getLanguageConstant('title_images'),
									true, true,
									url_Make(
										'transfer_control',
										'backend_module',
										array('backend_action', 'images'),
										array('module', 'gallery'),
										array('group', $gallery_id)
									)
								);
			}

		} else {
			// remove membership data, we'll update those in a moment
			$membership_manager->deleteData(array('item' => $id));

			// remove delivery methods
			$delivery_item_relation_manager->deleteData(array('item' => $id));
		}

		// store item data
		if ($new_item) {
			// store new data
			$manager->insertData($data);
			$window = 'shop_item_add';
			$id = $manager->getInsertedID();

		} else {
			// update existing data
			$manager->updateData($data, array('id' => $id));
			$window = 'shop_item_change';
		}
		
		// update categories and delivery method selection
		$category_ids = array();
		$category_template = 'category_id';
		$delivery_ids = array();
		$delivery_template = 'delivery_';
		
		foreach ($_REQUEST as $key => $value) {
			if (substr($key, 0, strlen($category_template)) == $category_template && $value == 1) 
				$category_ids[] = fix_id(substr($key, strlen($category_template)-1));

			if (substr($key, 0, strlen($delivery_template)) == $delivery_template)
				$delivery_ids[] = fix_id($value);
		}

		// update membership
		if (count($category_ids) > 0)
			foreach ($category_ids as $category_id) {
				$membership_manager->insertData(array(
										'category'	=> $category_id,
										'item'		=> $id
									));
			}

		// update delivery methods
		if (count($delivery_ids) > 0)
			foreach ($delivery_ids as $delivery_id) 
				if (!empty($delivery_id)) {
					$delivery_item_relation_manager->insertData(array(
											'item'		=> $id,
											'price'		=> $delivery_id
										));
				}

		// store related items
		if (!$new_item) 
			$related_items_manager->deleteData(array('item' => $id));

		$related = array();
		$keys = array_keys($_REQUEST);

		foreach($keys as $key)
			if (substr($key, 0, 7) == 'related')
				$related[] = substr($key, 8);

		if (count($related) > 0) {
			foreach($related as $related_id)
				$related_items_manager->insertData(array(
										'item'		=> $id,
										'related'	=> $related_id
									));
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant('message_item_saved'),
					'button'	=> $this->_parent->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('shop_items').';'.$open_editor
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}
	
	/**
	 * Show confirmation form before removing item
	 */
	private function deleteItem() {
		global $language;
		
		$id = fix_id($_REQUEST['id']);
		$manager = ShopItemManager::getInstance();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->_parent->name);

		$params = array(
					'message'		=> $this->_parent->getLanguageConstant("message_item_delete"),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->_parent->getLanguageConstant("delete"),
					'no_text'		=> $this->_parent->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'shop_item_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'items'),
												array('sub_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('shop_item_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();		
	}
	
	/**
	 * Mark item as deleted. We don't remove items in order
	 * to preserve valid shopping logs.
	 */
	private function deleteItem_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopItemManager::getInstance();
		$membership_manager = ShopItemMembershipManager::getInstance();

		$manager->updateData(array('deleted' => 1), array('id' => $id));
		$membership_manager->deleteData(array('item' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->_parent->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant("message_item_deleted"),
					'button'	=> $this->_parent->getLanguageConstant("close"),
					'action'	=> window_Close('shop_item_delete').";".window_ReloadContent('shop_items')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();		
	}

	/**
	 * Show search results for various backend tools
	 */
	private function showSearchResults() {
		$query = fix_chars($_REQUEST['query']);
		$template = new TemplateHandler('search_results.xml', $this->path.'templates/');

		$params = array(
						'query'	=> $query
					);

		// register tag handler
		$template->registerTagHandler('_item_list', $this, 'tag_ItemList');

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
	 * Handle drawing shop item
	 * 
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Item($tag_params, $children) {
		$manager = ShopItemManager::getInstance();
		$manufacturer_manager = ShopManufacturerManager::getInstance();
		$id = null;
		$gallery = null;
		$conditions = array();

		// prepare conditions
		if (isset($tag_params['id'])) 
			$id = fix_id($tag_params['id']);

		if (isset($tag_params['random']) && isset($tag_params['category'])) {
			if (is_numeric($tag_params['category'])) {
				$category_id = fix_id($tag_params['category']);

			} else {
				// specified id is actually text_id, get real one
				$category_manager = ShopCategoryManager::getInstance();
				$category = $category_manager->getSingleItem(
												array('id'), 
												array('text_id' => fix_chars($tag_params['category']))
											);

				if (!is_object($category)) 
					return;

				$category_id = $category->id;
			}

			$membership_manager = ShopItemMembershipManager::getInstance();

			// get all associated items
			$id_list = array();
			$membership_list = $membership_manager->getItems(array('item'), array('category' => $category_id));

			if (count($membership_list) > 0)
				foreach($membership_list as $membership)
					$id_list[] = $membership->item;

			// get random id from the list
			if (count($id_list) > 0)
				$id = $id_list[array_rand($id_list)];
		}

		if (is_null($id))
			return;

		// get item from database
		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));
		
		// create template handler
		$template = $this->_parent->loadTemplate($tag_params, 'item.xml');
		$template->setMappedModule($this->name);
		
		// register tag handlers
		if (!is_null($gallery)) 
			$template->registerTagHandler('cms:image_list', $gallery, 'tag_ImageList');

		$size_handler = ShopItemSizesHandler::getInstance($this->_parent);
		$template->registerTagHandler('_value_list', $size_handler, 'tag_ValueList');
		$template->registerTagHandler('_color_list', $this, 'tag_ColorList');
			
		// parse template
		if (is_object($item)) {
			// get gallery module
			if (class_exists('gallery'))
				$gallery = gallery::getInstance();
		
			if (!is_null($gallery)) {
				// get manufacturer logo
				$manufacturer_logo_url = '';

				if ($item->manufacturer != 0) {
					$manufacturer = $manufacturer_manager->getSingleItem(
													$manufacturer_manager->getFieldNames(),
													array('id' => $item->manufacturer)
												);

					if (is_object($manufacturer)) 
						$manufacturer_logo_url = $gallery->getImageURL($manufacturer->logo);
				}

				// get urls for image and thumbnail
				$image_url = $gallery->getGroupThumbnailURL($item->gallery, true);
				$thumbnail_url = $gallery->getGroupThumbnailURL($item->gallery); 

			} else {
				// default values if gallery is not enabled
				$image_url = '';
				$thumbnail_url = '';
				$manufacturer_logo_url = '';
			}

			$rating = 0;
			
			$params = array(
						'id'			=> $item->id,
						'uid'			=> $item->uid,
						'name'			=> $item->name,
						'description'	=> $item->description,
						'gallery'		=> $item->gallery,
						'image'			=> $image_url,
						'thumbnail'		=> $thumbnail_url,
						'manufacturer_logo_url' => $manufacturer_logo_url,
						'size_definition' => $item->size_definition,
						'colors'		=> $item->colors,
						'author'		=> $item->author,
						'views'			=> $item->views,
						'price'			=> $item->price,
						'tax'			=> $item->tax,
						'currency'		=> $this->_parent->settings['default_currency'],
						'weight'		=> $item->weight,
						'votes_up'		=> $item->votes_up,
						'votes_down'	=> $item->votes_down,
						'rating'		=> $rating,
						'priority'		=> $item->priority,
						'timestamp'		=> $item->timestamp,
						'visible'		=> $item->visible,
						'deleted'		=> $item->deleted,
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Handle drawing item list
	 *
	 * @param array $tag_params
	 * @param array $chilren
	 */
	public function tag_ItemList($tag_params, $children) {
		global $language; 

		$manager = ShopItemManager::getInstance();
		$conditions = array();
		$page_switch = null;
		$order_by = array('id');
		$order_asc = true;
		$limit = null;

		// create conditions
		if (isset($tag_params['category'])) {

			if (is_numeric($tag_params['category'])) {
				$category_id = fix_id($tag_params['category']);

			} else {
				// specified id is actually text_id, get real one
				$category_manager = ShopCategoryManager::getInstance();
				$category = $category_manager->getSingleItem(
												array('id'), 
												array('text_id' => fix_chars($tag_params['category']))
											);

				if (!is_object($category)) 
					return;

				$category_id = $category->id;
			}

			$membership_manager = ShopItemMembershipManager::getInstance();
			$membership_items = $membership_manager->getItems(
												array('item'), 
												array('category' => $category_id)
											);
				
			$item_ids = array();							
			if (count($membership_items) > 0)
				foreach($membership_items as $membership)
					$item_ids[] = $membership->item;
					
			if (count($item_ids) > 0)
				$conditions['id'] = $item_ids; else
				$conditions['id'] = -1;  // make sure nothing is returned if category is empty
		}

		if (isset($tag_params['related'])) {
			$relation_manager = ShopRelatedItemsManager::getInstance();
			$item_id = fix_id($tag_params['related']);

			$related_items = $relation_manager->getItems(array('related'), array('item' => $item_id));
			$related_item_ids = array();

			if (count($related_items) > 0)
				foreach ($related_items as $relationship)
					$related_item_ids[] = $relationship->related;

			if (count($related_item_ids) > 0)
				$conditions['id'] = $related_item_ids; else
				$conditions['id'] = -1;
		}
		
		if (!(isset($tag_params['show_deleted']) && $tag_params['show_deleted'] == 1)) {
			// force hiding deleted items
			$conditions['deleted'] = 0;
		}

		if (isset($tag_params['filter']) && !empty($tag_params['filter'])) {
			// filter items with name matching
			$conditions['name_'.$language] = array(
								'operator'	=> 'LIKE',
								'value'		=> '%'.fix_chars($tag_params['filter']).'%'
							);
		}

		if (isset($tag_params['paginate'])) {
			$per_page = is_numeric($tag_params['paginate']) ? $tag_params['paginate'] : 10;
			$param = isset($tag_params['page_param']) ? fix_chars($tag_params['page_param']) : null;

			$item_count = $manager->getItemValue('COUNT(id)', $conditions);

			$page_switch = new PageSwitch($param);
			$page_switch->setCurrentAsBaseURL();
			$page_switch->setItemsPerPage($per_page);
			$page_switch->setTotalItems($item_count);

			// get filter params
			$limit = $page_switch->getFilterParams();
		}

		if (isset($tag_params['order_by']))
			$order_by = array(fix_chars($tag_params['order_by']));

		// get items
		$items = $manager->getItems($manager->getFieldNames(), $conditions, $order_by, $order_asc, $limit);

		// create template
		$template = $this->_parent->loadTemplate($tag_params, 'item_list_item.xml');
		$template->registerTagHandler('_color_list', $this, 'tag_ColorList');

		if (count($items) > 0) {
			$gallery = null;
			if (class_exists('gallery'))
				$gallery = gallery::getInstance();

			$manufacturer_manager = ShopManufacturerManager::getInstance();
			
			foreach ($items as $item) {
				if (!is_null($gallery)) {
					// get manufacturer logo
					$manufacturer_logo_url = '';

					if ($item->manufacturer != 0) {
						$manufacturer = $manufacturer_manager->getSingleItem(
														$manufacturer_manager->getFieldNames(),
														array('id' => $item->manufacturer)
													);

						if (is_object($manufacturer)) 
							$manufacturer_logo_url = $gallery->getImageURL($manufacturer->logo);
					}

					// get urls for image and thumbnail
					$image_url = $gallery->getGroupThumbnailURL($item->gallery, true);
					$thumbnail_url = $gallery->getGroupThumbnailURL($item->gallery); 

				} else {
					// default values if gallery is not enabled
					$image_url = '';
					$thumbnail_url = '';
					$manufacturer_logo_url = '';
				}

				$rating = 0;
				
				$params = array(
							'id'			=> $item->id,
							'uid'			=> $item->uid,
							'name'			=> $item->name,
							'description'	=> $item->description,
							'gallery'		=> $item->gallery,
							'size_definition'=> $item->size_definition,
							'colors'		=> $item->colors,
							'image'			=> $image_url,
							'thumbnail'		=> $thumbnail_url,
							'manufacturer_logo_url'	=> $manufacturer_logo_url,
							'author'		=> $item->author,
							'views'			=> $item->views,
							'price'			=> $item->price,
							'tax'			=> $item->tax,
							'currency'		=> $this->_parent->settings['default_currency'],
							'weight'		=> $item->weight,
							'votes_up'		=> $item->votes_up,
							'votes_down'	=> $item->votes_down,
							'rating'		=> $rating,
							'priority'		=> $item->priority,
							'timestamp'		=> $item->timestamp,
							'visible'		=> $item->visible,
							'deleted'		=> $item->deleted,
							'item_change'	=> url_MakeHyperlink(
													$this->_parent->getLanguageConstant('change'),
													window_Open(
														'shop_item_change', 	// window id
														505,				// width
														$this->_parent->getLanguageConstant('title_item_change'), // title
														true, true,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'items'),
															array('sub_action', 'change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> url_MakeHyperlink(
													$this->_parent->getLanguageConstant('delete'),
													window_Open(
														'shop_item_delete', 	// window id
														400,				// width
														$this->_parent->getLanguageConstant('title_item_delete'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'items'),
															array('sub_action', 'delete'),
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

		// draw page switch if needed
		if (!is_null($page_switch)) {
			$params = array();
			$children = array();

			// pick up parameters from original array
			foreach ($tag_params as $key => $value)
				if (substr($key, 0, 12) == 'page_switch_')
					$params[substr($key, 12)] = $value;

			$page_switch->tag_PageSwitch($params, $children);
		}
	}

	/**
	 * Handle printing colors for specified item
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_ColorList($tag_params, $children) {
		$id = null;
		$manager = ShopItemManager::getInstance();

		if (isset($tag_params['id']))
			$id = fix_id($tag_params['id']);

		if (is_null($id))
			return;

		// get specified item
		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (!is_object($item))
			return;

		// load template
		$template = $this->_parent->loadTemplate($tag_params, 'color_preview.xml');

		if (empty($item->colors))
			return;

		$colors = explode(',', $item->colors);

		if (count($colors) > 0)
			foreach ($colors as $color) {
				$data = explode(':', $color);
				$params = array(
						'name'	=> $data[0],
						'value'	=> $data[1]
					);

				$template->setLocalParams($params);
				$template->restoreXML();
				$template->parse();
			}
	}

	/**
	 * Handle request for JSON object
	 */
	public function json_GetItem() {
		$uid = isset($_REQUEST['uid']) ? fix_chars($_REQUEST['uid']) : null;
		$manager = ShopItemManager::getInstance();

		// prepare result
		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'item'			=> array()
				);

		if (!is_null($uid)) {
			// create conditions
			$conditions = array(
							'uid'		=> $uid,
							'deleted'	=> 0,
							'visible'	=> 1
						);

			$item = $manager->getSingleItem($manager->getFieldNames(), $conditions);

			if (is_object($item)) {
				// get item image url
				$thumbnail_url = null;
				if (class_exists('gallery')) {
					$gallery = gallery::getInstance();
					$thumbnail_url = $gallery->getGroupThumbnailURL($item->gallery); 
				}

				$rating = 0;

				$result['item'] = array(
								'id'			=> $item->id,
								'uid'			=> $item->uid,
								'name'			=> $item->name,
								'description'	=> $item->description,
								'gallery'		=> $item->gallery,
								'views'			=> $item->views,
								'price'			=> $item->price,
								'tax'			=> $item->tax,
								'weight'		=> $item->weight,
								'votes_up'		=> $item->votes_up,
								'votes_down'	=> $item->votes_down,
								'rating'		=> $rating,
								'priority'		=> $item->priority,
								'timestamp'		=> $item->timestamp,
								'thumbnail'		=> $thumbnail_url
							);
			} else {
				// there was a problem with reading item from database
				$result['error'] = true;
				$result['error_message'] = $this->_parent->getLanguageConstant('message_error_getting_item');
			}

		} else {
			// invalid ID was specified
			$result['error'] = true;
			$result['error_message'] = $this->_parent->getLanguageConstant('message_error_invalid_id');
		}

		// create JSON object and print it
		define('_OMIT_STATS', 1);
		print json_encode($result);
	}
}

?>
