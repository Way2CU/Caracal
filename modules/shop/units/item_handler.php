<?php

/**
 * Shop item handler class. This class manages all the operatiosn on shop items
 * and displaying them.
 *
 * Triggered events:
 * - Callback for `item-added`:
 *		function ($item_id)
 *
 *		Called after new item has been added to the shop.
 *
 * - Callback for `item-changed`:
 *		function ($item_id)
 *
 *		Called after existing item has been changed.
 *
 * - Callback for `item-deleted`:
 *		- function ($item_id)
 *
 *		Called after item has been removed.
 *
 * Author: Mladen Mijatov
 */
namespace Modules\Shop\Item;

require_once('item_manager.php');
require_once('item_membership_manager.php');
require_once('item_remark_manager.php');
require_once('category_manager.php');
require_once('related_items_manager.php');
require_once('property_handler.php');

use Core\Events;
use TemplateHandler;
use ModuleHandler;
use URL;
use gallery;
use shop;

use ShopCategoryHandler;
use ShopItemSizesHandler;
use ShopManufacturerHandler;
use ShopItemMembershipManager;
use ShopRelatedItemsManager;
use ShopManufacturerManager;
use ShopCategoryManager;
use Modules\Shop\Property\Handler as PropertyHandler;


class Handler {
	private static $_instance;
	private $parent;
	private $name;
	private $path;

	const SUB_ACTION = 'items';

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		$this->parent = $parent;
		$this->name = $this->parent->name;
		$this->path = $this->parent->path;

		// register item related events with system
		Events::register('shop', 'item-added', 1);
		Events::register('shop', 'item-changed', 1);
		Events::register('shop', 'item-deleted', 1);
	}

	/**
	 * Public function that creates a single instance
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
	public function transfer_control($params = array(), $children = array()) {
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
					'link_new' => URL::make_hyperlink(
										$this->parent->get_language_constant('add_item'),
										window_Open( // on click open window
											'shop_item_add',
											700,
											$this->parent->get_language_constant('title_item_add'),
											true, true,
											backend_UrlMake($this->name, self::SUB_ACTION, 'add')
										)
									),
					'link_categories' => URL::make_hyperlink(
										$this->parent->get_language_constant('manage_categories'),
										window_Open( // on click open window
											'shop_categories',
											550,
											$this->parent->get_language_constant('title_manage_categories'),
											true, true,
											backend_UrlMake($this->name, 'categories')
										)
									)
					);

		// register tag handlers
		$template->register_tag_handler('cms:item_list', $this, 'tag_ItemList');

		$manufacturer_handler = ShopManufacturerHandler::get_instance($this->parent);
		$template->register_tag_handler('cms:manufacturer_list', $manufacturer_handler, 'tag_ManufacturerList');

		$category_handler = ShopCategoryHandler::get_instance($this->parent);
		$template->register_tag_handler('cms:category_list', $category_handler, 'tag_CategoryList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for adding new shop item
	 */
	private function addItem() {
		$template = new TemplateHandler('item_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'uid'			=> $this->generateUID(),
					'form_action'	=> backend_UrlMake($this->name, self::SUB_ACTION, 'save'),
					'cancel_action'	=> window_Close('shop_item_add')
				);

		// register external tag handlers
		$category_handler = ShopCategoryHandler::get_instance($this->parent);
		$template->register_tag_handler('cms:category_list', $category_handler, 'tag_CategoryList');

		$size_handler = ShopItemSizesHandler::get_instance($this->parent);
		$template->register_tag_handler('cms:size_list', $size_handler, 'tag_SizeList');

		$manufacturer_handler = ShopManufacturerHandler::get_instance($this->parent);
		$template->register_tag_handler('cms:manufacturer_list', $manufacturer_handler, 'tag_ManufacturerList');

		$template->register_tag_handler('cms:item_list', $this, 'tag_ItemList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for editing existing shop item
	 */
	private function changeItem() {
		$id = fix_id($_REQUEST['id']);
		$manager = Manager::get_instance();
		$remark_manager = RemarkManager::get_instance();

		// get item from the database
		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (!is_object($item))
			return;

		// try to load remark for specified item
		$data = $remark_manager->get_single_item(array('remark'), array('item' => $id));
		$remark = is_object($data) ? $data->remark : '';

		// create template
		$template = new TemplateHandler('item_change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		// register tag handlers
		$category_handler = ShopCategoryHandler::get_instance($this->parent);
		$size_handler = ShopItemSizesHandler::get_instance($this->parent);
		$manufacturer_handler = ShopManufacturerHandler::get_instance($this->parent);

		$template->register_tag_handler('cms:category_list', $category_handler, 'tag_CategoryList');
		$template->register_tag_handler('cms:size_list', $size_handler, 'tag_SizeList');
		$template->register_tag_handler('cms:manufacturer_list', $manufacturer_handler, 'tag_ManufacturerList');
		$template->register_tag_handler('cms:item_list', $this, 'tag_ItemList');

		// prepare parameters
		$params = array(
					'id'              => $item->id,
					'uid'             => $item->uid,
					'name'            => $item->name,
					'description'     => $item->description,
					'gallery'         => $item->gallery,
					'manufacturer'    => $item->manufacturer,
					'size_definition' => $item->size_definition,
					'author'          => $item->author,
					'views'           => $item->views,
					'price'           => $item->price,
					'discount'        => $item->discount,
					'colors'          => $item->colors,
					'tags'            => $item->tags,
					'tax'             => $item->tax,
					'weight'          => $item->weight,
					'votes_up'        => $item->votes_up,
					'votes_down'      => $item->votes_down,
					'priority'        => $item->priority,
					'created'         => date('Y-m-d\TH:i:s', strtotime($item->timestamp)),
					'expires'         => date('Y-m-d\TH:i:s', strtotime($item->expires)),
					'visible'         => $item->visible,
					'deleted'         => $item->deleted,
					'remark'          => $remark,
					'form_action'     => backend_UrlMake($this->name, self::SUB_ACTION, 'save'),
					'cancel_action'   => window_Close('shop_item_change')
				);

		// parse template
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save new or changed item data
	 */
	private function saveItem() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = Manager::get_instance();
		$remark_manager = RemarkManager::get_instance();
		$membership_manager = ShopItemMembershipManager::get_instance();
		$related_items_manager = ShopRelatedItemsManager::get_instance();
		$open_editor = '';

		$new_item = is_null($id);

		$data = array(
				'name'            => $this->parent->get_multilanguage_field('name'),
				'description'     => $this->parent->get_multilanguage_field('description'),
				'price'           => isset($_REQUEST['price']) && !empty($_REQUEST['price']) ? fix_chars($_REQUEST['price']) : 0,
				'discount'        => isset($_REQUEST['discount']) && !empty($_REQUEST['discount']) ? fix_chars($_REQUEST['discount']) : 0,
				'colors'          => fix_chars($_REQUEST['colors']),
				'tags'            => escape_chars($_REQUEST['tags']),
				'tax'             => isset($_REQUEST['tax']) && !empty($_REQUEST['tax']) ? fix_chars($_REQUEST['tax']) : 0,
				'weight'          => isset($_REQUEST['weight']) && !empty($_REQUEST['weight']) ? fix_chars($_REQUEST['weight']) : 0,
				'size_definition' => isset($_REQUEST['size_definition']) ? fix_id($_REQUEST['size_definition']) : null,
				'priority'        => isset($_REQUEST['priority']) ? fix_id($_REQUEST['priority']) : 5,
				'manufacturer'    => isset($_REQUEST['manufacturer']) && !empty($_REQUEST['manufacturer']) ? fix_id($_REQUEST['manufacturer']) : 0,
				'visible'         => $this->parent->get_boolean_field('visible') ? 1 : 0,
				'uid'             => isset($_REQUEST['uid']) ? fix_chars($_REQUEST['uid']) : $this->generateUID(),
				'expires'         => date('Y-m-d H:i:s', strtotime(fix_chars($_REQUEST['expires'])))
			);

		if ($new_item) {
			// add elements first time
			$data['author'] = $_SESSION['uid'];

			if (ModuleHandler::is_loaded('gallery')) {
				$gallery = gallery::get_instance();
				$gallery_id = $gallery->createGallery($data['name']);
				$data['gallery'] = $gallery_id;

				// create action for opening gallery editor
				$open_editor = window_Open(
									'gallery_images',
									670,
									$gallery->get_language_constant('title_images'),
									true, true,
									URL::make_query(
										'backend_module',
										'transfer_control',
										array('backend_action', 'images'),
										array('module', 'gallery'),
										array('group', $gallery_id)
									)
								);
			}

		} else {
			// remove membership data, we'll update those in a moment
			$membership_manager->delete_items(array('item' => $id));

			// remove remarks as well
			$remark_manager->delete_items(array('item' => $id));
		}

		// store item data
		if ($new_item) {
			// store new data
			$manager->insert_item($data);
			$window = 'shop_item_add';
			$id = $manager->get_inserted_id();
			Events::trigger('shop', 'item-added', $id);

		} else {
			// update existing data
			$manager->update_items($data, array('id' => $id));
			$window = 'shop_item_change';
			Events::trigger('shop', 'item-changed', $id);
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
				$membership_manager->insert_item(array(
										'category'	=> $category_id,
										'item'		=> $id
									));
			}

		// update remark
		$remark_manager->insert_item(array(
					'item'   => $id,
					'remark' => escape_chars($_REQUEST['remark'])
				));

		// store related items
		if (!$new_item)
			$related_items_manager->delete_items(array('item' => $id));

		$related = array();
		$keys = array_keys($_REQUEST);

		foreach($keys as $key)
			if (substr($key, 0, 7) == 'related')
				$related[] = substr($key, 8);

		if (count($related) > 0) {
			foreach($related as $related_id)
				$related_items_manager->insert_item(array(
										'item'		=> $id,
										'related'	=> $related_id
									));
		}

		// store properties
		$properties_handler = PropertyHandler::get_instance($this->parent);
		$properties_handler->save_properties($id);

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->parent->get_language_constant('message_item_saved'),
					'button'	=> $this->parent->get_language_constant('close'),
					'action'	=> window_Close($window).';'.window_ReloadContent('shop_items').';'.$open_editor
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show confirmation form before removing item
	 */
	private function deleteItem() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = Manager::get_instance();

		$item = $manager->get_single_item(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->parent->name);

		$params = array(
					'message'		=> $this->parent->get_language_constant('message_item_delete'),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->parent->get_language_constant('delete'),
					'no_text'		=> $this->parent->get_language_constant('cancel'),
					'yes_action'	=> window_LoadContent(
											'shop_item_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', self::SUB_ACTION),
												array('sub_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('shop_item_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Mark item as deleted. We don't remove items in order
	 * to preserve valid shopping logs.
	 */
	private function deleteItem_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = Manager::get_instance();
		$membership_manager = ShopItemMembershipManager::get_instance();

		$manager->update_items(array('deleted' => 1), array('id' => $id));
		$membership_manager->delete_items(array('item' => $id));
		Events::trigger('shop', 'item-deleted', $id);

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->parent->name);

		$params = array(
					'message'	=> $this->parent->get_language_constant('message_item_deleted'),
					'button'	=> $this->parent->get_language_constant('close'),
					'action'	=> window_Close('shop_item_delete').';'.window_ReloadContent('shop_items')
				);

		$template->restore_xml();
		$template->set_local_params($params);
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
		$template->register_tag_handler('cms:item_list', $this, 'tag_ItemList');

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
		$manager = Manager::get_instance();

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
	 * Handle drawing shop item
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Item($tag_params, $children) {
		$shop = shop::get_instance();
		$manager = Manager::get_instance();
		$manufacturer_manager = ShopManufacturerManager::get_instance();
		$id = null;
		$gallery = null;
		$conditions = array();

		// prepare conditions
		if (isset($tag_params['id']))
			$conditions['id'] = fix_id($tag_params['id']);

		if (isset($tag_params['uid']))
			$conditions['uid'] = fix_chars($tag_params['uid']);

		if (isset($tag_params['random']) && isset($tag_params['category'])) {
			if (is_numeric($tag_params['category'])) {
				$category_id = fix_id($tag_params['category']);

			} else {
				// specified id is actually text_id, get real one
				$category_manager = ShopCategoryManager::get_instance();
				$category = $category_manager->get_single_item(
												array('id'),
												array('text_id' => fix_chars($tag_params['category']))
											);

				if (!is_object($category))
					return;

				$category_id = $category->id;
			}

			$membership_manager = ShopItemMembershipManager::get_instance();

			// get all associated items
			$id_list = array();
			$membership_list = $membership_manager->get_items(array('item'), array('category' => $category_id));

			if (count($membership_list) > 0)
				foreach($membership_list as $membership)
					$id_list[] = $membership->item;

			// get random id from the list
			if (count($id_list) > 0)
				$conditions['id'] = $id_list[array_rand($id_list)];
		}

		// option to show or hide expired
		if (isset($tag_params['show_expired']))
			if ($tag_params['show_expired'] == 0) {
				$conditions['expires'] = array(
						'operator' => '>=',
						'value'    => date('Y-m-d H:i:s')
					);

			} else {
				$conditions['expires'] = array(
						'operator' => 'IS NOT',
						'value'    => 'NULL'
					);
			}

		if (empty($conditions))
			return;

		// get item from database
		$item = $manager->get_single_item($manager->get_field_names(), $conditions);

		if (!is_object($item))
			return;

		// update item views count
		if (isset($tag_params['update_view_count']) && $tag_params['update_view_count'] == 1)
			$manager->update_items(array('views' => $item->views + 1), array('id' => $item->id));

		// create template handler
		$template = $this->parent->load_template($tag_params, 'item.xml');
		$template->set_template_params_from_array($children);
		$template->set_mapped_module($this->name);

		// register tag handlers
		if (!is_null($gallery))
			$template->register_tag_handler('cms:image_list', $gallery, 'tag_ImageList');

		$size_handler = ShopItemSizesHandler::get_instance($this->parent);
		$template->register_tag_handler('cms:value_list', $size_handler, 'tag_ValueList');
		$template->register_tag_handler('cms:color_list', $this, 'tag_ColorList');

		// parse template
		if (ModuleHandler::is_loaded('gallery'))
			$gallery = gallery::get_instance();

		if (!is_null($gallery)) {
			// get manufacturer logo
			$manufacturer_logo_url = '';

			if ($item->manufacturer != 0) {
				$manufacturer = $manufacturer_manager->get_single_item(
												$manufacturer_manager->get_field_names(),
												array('id' => $item->manufacturer)
											);

				if (is_object($manufacturer))
					$manufacturer_logo_url = $gallery->getImageURL($manufacturer->logo);
			}

			// get urls for image and thumbnail
			$image_url = gallery::getGroupImageById($item->gallery);

		} else {
			// default values if gallery is not enabled
			$image_url = '';
			$manufacturer_logo_url = '';
		}

		$rating = 0;
		$variation_id = $shop->generateVariationId($item->uid);

		$params = array(
					'id'                    => $item->id,
					'uid'                   => $item->uid,
					'variation_id'          => $variation_id,
					'cid'                   => $item->uid.'/'.$variation_id,
					'name'                  => $item->name,
					'description'           => $item->description,
					'gallery'               => $item->gallery,
					'image'                 => $image_url,
					'manufacturer'          => $item->manufacturer,
					'manufacturer_logo_url' => $manufacturer_logo_url,
					'size_definition'       => $item->size_definition,
					'colors'                => $item->colors,
					'author'                => $item->author,
					'views'                 => $item->views,
					'price'                 => $item->price,
					'discount'              => $item->discount,
					'discount_price'        => $item->discount ? number_format($item->price * ((100 - $item->discount) / 100), 2) : $item->price,
					'tax'                   => $item->tax,
					'currency'              => $this->parent->settings['default_currency'],
					'weight'                => $item->weight,
					'votes_up'              => $item->votes_up,
					'votes_down'            => $item->votes_down,
					'rating'                => $rating,
					'priority'              => $item->priority,
					'timestamp'             => $item->timestamp,
					'visible'               => $item->visible,
					'deleted'               => $item->deleted,
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Handle drawing item list
	 *
	 * @param array $tag_params
	 * @param array $chilren
	 */
	public function tag_ItemList($tag_params, $children) {
		global $language, $section;

		$shop = shop::get_instance();
		$manager = Manager::get_instance();
		$conditions = array();
		$page_switch = null;
		$order_by = array('id');
		$order_asc = true;
		$limit = null;

		// create conditions
		if (isset($_REQUEST['manufacturer']) && !empty($_REQUEST['manufacturer']))
			$conditions['manufacturer'] = fix_id($_REQUEST['manufacturer']);

		if (isset($tag_params['manufacturer']) && !empty($tag_params['manufacturer']))
			$conditions['manufacturer'] = fix_id($tag_params['manufacturer']);

		if (isset($tag_params['priority']) && !empty($tag_params['priority']))
			$conditions['priority'] = fix_id($tag_params['priority']);

		if (isset($tag_params['category'])) {
			$categories = explode(',', $tag_params['category']);

			if (is_numeric($categories[0])) {
				$category_id = fix_id($categories);

			} else {
				// specified id is actually text_id, get real one
				$category_manager = ShopCategoryManager::get_instance();
				$category_list = $category_manager->get_items(
												array('id'),
												array('text_id' => fix_chars($categories))
											);

				if (count($category_list) == 0)
					return;

				// populate list of categories
				$category_id = array();
				foreach ($category_list as $category)
					$category_id[] = $category->id;
			}

			$membership_manager = ShopItemMembershipManager::get_instance();
			$membership_items = $membership_manager->get_items(
												array('item'),
												array('category' => $category_id)
											);

			$item_ids = array();
			if (count($membership_items) > 0) {
				// accumulate item membership counts
				$item_counts = array();
				foreach($membership_items as $membership)
					if (isset($item_counts[$membership->item]))
						$item_counts[$membership->item]++; else
						$item_counts[$membership->item] = 1;

				// remove all the item ids which don't belong to all categories
				$required_count = count($categories);
				foreach ($item_counts as $id => $count)
					if ($count == $required_count)
						$item_ids[] = $id;
			}

			if (count($item_ids) > 0)
				$conditions['id'] = $item_ids; else
				$conditions['id'] = -1;  // make sure nothing is returned if category is empty
		}

		// option to show or hide expired items
		if (isset($tag_params['show_expired']))
			if ($tag_params['show_expired'] == 0) {
				$conditions['expires'] = array(
						'operator' => '>=',
						'value'    => date('Y-m-d H:i:s')
					);
			} else {
				$conditions['expires'] = array(
						'operator' => 'IS NOT',
						'value'    => 'NULL'
					);
			}

		if (isset($tag_params['related'])) {
			$item_id = -1;
			$relation_manager = ShopRelatedItemsManager::get_instance();

			if (is_numeric($tag_params['related'])) {
				// get item id as is
				$item_id = fix_id($tag_params['related']);

			} else {
				// find item id based on specified text id
				$item = $manager->get_single_item(array('id'), array('uid' => fix_chars($tag_params['related'])));

				if (is_object($item))
					$item_id = $item->id;
			}

			$related_items = $relation_manager->get_items(array('related'), array('item' => $item_id));
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

		$conditions['visible'] = 1;
		if (isset($tag_params['show_hidden']) && $tag_params['show_hidden'] == 1) {
			// force skipping hidden items
			unset($conditions['visible']);
		}

		if (isset($tag_params['filter']) && !empty($tag_params['filter'])) {
			// filter items with name matching
			$conditions['name_'.$language] = array(
								'operator'	=> 'LIKE',
								'value'		=> '%'.fix_chars($tag_params['filter']).'%'
							);
		}

		if (isset($tag_params['limit']))
			$limit = fix_id($tag_params['limit']);

		if (isset($tag_params['paginate'])) {
			$per_page = is_numeric($tag_params['paginate']) ? $tag_params['paginate'] : 10;
			$param = isset($tag_params['page_param']) ? fix_chars($tag_params['page_param']) : null;

			$item_count = $manager->get_item_value('COUNT(id)', $conditions);

			$page_switch = new PageSwitch($param);
			$page_switch->setCurrentAsBaseURL();
			$page_switch->setItemsPerPage($per_page);
			$page_switch->setTotalItems($item_count);

			// get filter params
			$limit = $page_switch->getFilterParams();
		}

		if (isset($tag_params['order_by']))
			$order_by = fix_chars(explode(',', $tag_params['order_by']));

		if (isset($tag_params['order_asc']))
			$order_asc = $tag_params['order_asc'] == 1;

		if (isset($tag_params['random']) && $tag_params['random'] == 1)
			$order_by = array('RAND()');

		// get items
		$items = $manager->get_items($manager->get_field_names(), $conditions, $order_by, $order_asc, $limit);

		// create template
		$size_handler = ShopItemSizesHandler::get_instance($this->parent);
		$template = $this->parent->load_template($tag_params, 'item_list_item.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:color_list', $this, 'tag_ColorList');
		$template->register_tag_handler('cms:value_list', $size_handler, 'tag_ValueList');

		// make sure we have items
		if (count($items) == 0)
			return;

		// prepare template for parsing
		$gallery = null;
		if (ModuleHandler::is_loaded('gallery'))
			$gallery = gallery::get_instance();

		$manufacturer_manager = ShopManufacturerManager::get_instance();

		// time marker after which all added items are considered new
		$days_until_old = 7;
		if (isset($tag_params['days_until_old']))
			$days_until_old = fix_id($tag_params['days_until_old']);
		$new_timestamp = time() - ($days_until_old * 24 * 60 * 60);

		foreach ($items as $item) {
			if (!is_null($gallery)) {
				// get manufacturer logo
				$manufacturer_logo_url = '';

				if ($item->manufacturer != 0) {
					$manufacturer = $manufacturer_manager->get_single_item(
													$manufacturer_manager->get_field_names(),
													array('id' => $item->manufacturer)
												);

					if (is_object($manufacturer))
						$manufacturer_logo_url = $gallery->getImageURL($manufacturer->logo);
				}

				// get urls for image and thumbnail
				$image_url = gallery::getGroupImageById($item->gallery);

			} else {
				// default values if gallery is not enabled
				$image_url = '';
				$manufacturer_logo_url = '';
			}

			$rating = 0;
			$variation_id = $shop->generateVariationId($item->uid);

			$params = array(
						'id'                    => $item->id,
						'uid'                   => $item->uid,
						'variation_id'          => $variation_id,
						'cid'                   => $item->uid.'/'.$variation_id,
						'name'                  => $item->name,
						'description'           => $item->description,
						'gallery'               => $item->gallery,
						'size_definition'       => $item->size_definition,
						'colors'                => $item->colors,
						'image'                 => $image_url,
						'manufacturer'          => $item->manufacturer,
						'manufacturer_logo_url' => $manufacturer_logo_url,
						'author'                => $item->author,
						'views'                 => $item->views,
						'price'                 => $item->price,
						'discount'              => $item->discount,
						'discount_price'        => $item->discount ? number_format($item->price * ((100 - $item->discount) / 100), 2) : $item->price,
						'tax'                   => $item->tax,
						'currency'              => $this->parent->settings['default_currency'],
						'weight'                => $item->weight,
						'votes_up'              => $item->votes_up,
						'votes_down'            => $item->votes_down,
						'rating'                => $rating,
						'priority'              => $item->priority,
						'timestamp'             => $item->timestamp,
						'is_new'                => strtotime($item->timestamp) >= $new_timestamp,
						'expires'               => strtotime($item->expires),
						'visible'               => $item->visible,
						'deleted'               => $item->deleted
					);

			if ($section == 'backend' || $section == 'backend_module') {
				$params['item_change'] = URL::make_hyperlink(
							$this->parent->get_language_constant('change'),
							window_Open(
								'shop_item_change', 	// window id
								700,				// width
								$this->parent->get_language_constant('title_item_change'), // title
								true, true,
								URL::make_query(
									'backend_module',
									'transfer_control',
									array('module', $this->name),
									array('backend_action', self::SUB_ACTION),
									array('sub_action', 'change'),
									array('id', $item->id)
								))
						);

				$params['item_delete'] = URL::make_hyperlink(
							$this->parent->get_language_constant('delete'),
							window_Open(
								'shop_item_delete', 	// window id
								400,				// width
								$this->parent->get_language_constant('title_item_delete'), // title
								false, false,
								URL::make_query(
									'backend_module',
									'transfer_control',
									array('module', $this->name),
									array('backend_action', self::SUB_ACTION),
									array('sub_action', 'delete'),
									array('id', $item->id)
								))
						);
			}

			// add images link
			if (!is_null($gallery)) {
				$open_gallery_window = window_Open(
									'gallery_images',
									670,
									$gallery->get_language_constant('title_images'),
									true, true,
									URL::make_query(
										'backend_module',
										'transfer_control',
										array('backend_action', 'images'),
										array('module', 'gallery'),
										array('group', $item->gallery)
									)
								);
				$params['item_images'] = URL::make_hyperlink(
													$this->parent->get_language_constant('images'),
													$open_gallery_window
												);
			} else {
				$params['item_images'] = '';
			}

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
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
		$manager = Manager::get_instance();

		if (isset($tag_params['id']))
			$id = fix_id($tag_params['id']);

		if (is_null($id))
			return;

		// get specified item
		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (!is_object($item))
			return;

		// load template
		$template = $this->parent->load_template($tag_params, 'color_preview.xml');
		$template->set_template_params_from_array($children);

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

				$template->set_local_params($params);
				$template->restore_xml();
				$template->parse();
			}
	}

	/**
	 * Handle request for JSON object
	 */
	public function json_GetItem() {
		$uid = isset($_REQUEST['uid']) ? fix_chars($_REQUEST['uid']) : null;
		$manager = Manager::get_instance();

		// get thumbnail options
		$thumbnail_size = isset($_REQUEST['thumbnail_size']) ? fix_id($_REQUEST['thumbnail_size']) : 100;
		$thumbnail_constraint = isset($_REQUEST['thumbnail_constraint']) ? fix_id($_REQUEST['thumbnail_constraint']) : Thumbnail::CONSTRAIN_BOTH;

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

			$item = $manager->get_single_item($manager->get_field_names(), $conditions);

			if (is_object($item)) {
				// get item image url
				$thumbnail_url = null;
				if (ModuleHandler::is_loaded('gallery'))
					$thumbnail_url = gallery::getGroupThumbnailById(
											$item->gallery,
											null,
											$thumbnail_size,
											$thumbnail_constraint
										);

				$rating = 0;

				$result['item'] = array(
								'id'             => $item->id,
								'uid'            => $item->uid,
								'name'           => $item->name,
								'description'    => $item->description,
								'gallery'        => $item->gallery,
								'views'          => $item->views,
								'price'          => $item->price,
								'discount'       => $item->discount,
								'discount_price' => $item->discount ? $item->price * ((100 - $item->discount) / 100) : $item->price,
								'tax'            => $item->tax,
								'weight'         => $item->weight,
								'votes_up'       => $item->votes_up,
								'votes_down'     => $item->votes_down,
								'rating'         => $rating,
								'priority'       => $item->priority,
								'timestamp'      => $item->timestamp,
								'thumbnail'      => $thumbnail_url
							);
			} else {
				// there was a problem with reading item from database
				$result['error'] = true;
				$result['error_message'] = $this->parent->get_language_constant('message_error_getting_item');
			}

		} else {
			// invalid ID was specified
			$result['error'] = true;
			$result['error_message'] = $this->parent->get_language_constant('message_error_invalid_id');
		}

		// create JSON object and print it
		define('_OMIT_STATS', 1);
		print json_encode($result);
	}
}

?>
