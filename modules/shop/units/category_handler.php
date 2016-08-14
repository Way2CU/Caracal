<?php

/**
 * Currency converter and manager
 *
 * http://www.google.com/ig/calculator?hl=en&q=100EUR%3D%3Frsd
 *
 */

class ShopCategoryHandler {
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
				$this->addCategory();
				break;

			case 'change':
				$this->changeCategory();
				break;

			case 'save':
				$this->saveCategory();
				break;

			case 'delete':
				$this->deleteCategory();
				break;

			case 'delete_commit':
				$this->deleteCategory_Commit();
				break;

			default:
				$this->showCategories();
				break;
		}
	}

	/**
	 * Show categories management form
	 */
	private function showCategories() {
		$template = new TemplateHandler('category_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new' => URL::make_hyperlink(
										$this->_parent->getLanguageConstant('add_category'),
										window_Open( // on click open window
											'shop_category_add',
											400,
											$this->_parent->getLanguageConstant('title_category_add'),
											true, true,
											backend_UrlMake($this->name, 'categories', 'add')
										)
									)
				);

 		$template->registerTagHandler('cms:category', $this, 'tag_Category');
 		$template->registerTagHandler('cms:category_list', $this, 'tag_CategoryList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for adding new category
	 */
	private function addCategory() {
		$template = new TemplateHandler('category_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'parent'		=> isset($_REQUEST['parent']) ? fix_id($_REQUEST['parent']) : null,
					'form_action'	=> backend_UrlMake($this->name, 'categories', 'save'),
					'cancel_action'	=> window_Close('shop_category_add')
				);

		// register tag handlers
		$template->registerTagHandler('cms:category', $this, 'tag_Category');
		$template->registerTagHandler('cms:category_list', $this, 'tag_CategoryList');

		if (ModuleHandler::is_loaded('gallery')) {
			$gallery = gallery::getInstance();
			$template->registerTagHandler('cms:image_list', $gallery, 'tag_ImageList');
		}

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show for for changing existing category
	 */
	private function changeCategory() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopCategoryManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			// create template
			$template = new TemplateHandler('category_change.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			// register tag handlers
			$template->registerTagHandler('cms:category_list', $this, 'tag_CategoryList');

			if (ModuleHandler::is_loaded('gallery')) {
				$gallery = gallery::getInstance();
				$template->registerTagHandler('cms:image_list', $gallery, 'tag_ImageList');
			}

			// prepare parameters
			$params = array(
						'id'			=> $item->id,
						'parent'		=> $item->parent,
						'image'			=> $item->image,
						'title'			=> $item->title,
						'text_id'		=> $item->text_id,
						'description'	=> $item->description,
						'form_action'	=> backend_UrlMake($this->name, 'categories', 'save'),
						'cancel_action'	=> window_Close('shop_category_change')
					);

			// parse template
			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Save new or changed category data
	 */
	private function saveCategory() {
		$manager = ShopCategoryManager::getInstance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$data = array(
				'parent'		=> fix_id($_REQUEST['parent']),
				'text_id'		=> fix_chars($_REQUEST['text_id']),
				'title'			=> $this->_parent->getMultilanguageField('title'),
				'description'	=> $this->_parent->getMultilanguageField('description')
			);

		// get image if set and gallery is activated
		if (isset($_REQUEST['image']) && ModuleHandler::is_loaded('gallery'))
			$data['image'] = fix_id($_REQUEST['image']);

		if (is_null($id)) {
			// write new data
			$manager->insertData($data);

			$window = 'shop_category_add';

		} else {
			// update existing data
			$manager->updateData($data, array('id' => $id));

			$window = 'shop_category_change';
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant('message_category_saved'),
					'button'	=> $this->_parent->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('shop_categories'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation form before deleting category
	 */
	private function deleteCategory() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopCategoryManager::getInstance();

		$title = $manager->getItemValue('title', array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->_parent->getLanguageConstant('message_category_delete'),
					'name'			=> $title,
					'yes_text'		=> $this->_parent->getLanguageConstant('delete'),
					'no_text'		=> $this->_parent->getLanguageConstant('cancel'),
					'yes_action'	=> window_LoadContent(
											'shop_category_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'categories'),
												array('sub_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('shop_category_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Perform category removal
	 */
	private function deleteCategory_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopCategoryManager::getInstance();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant('message_category_deleted'),
					'button'	=> $this->_parent->getLanguageConstant('close'),
					'action'	=> window_Close('shop_category_delete').";"
									.window_ReloadContent('shop_categories')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Tag handler for single category item
	 *
	 * @param array $tag_params
	 * @param arrat $children
	 */
	public function tag_Category($tag_params, $children) {
		$manager = ShopCategoryManager::getInstance();
		$conditions = array();

		// create conditions
		if (isset($tag_params['id']))
			$conditions['id'] = fix_id($tag_params['id']);

		if (isset($tag_params['text_id']))
			$conditions['text_id'] = fix_chars($tag_params['text_id']);

		// get item from database
		$item = $manager->getSingleItem($manager->getFieldNames(), $conditions);

		// create template handler
		$template = $this->_parent->loadTemplate($tag_params, 'category.xml');
		$template->setTemplateParamsFromArray($children);
		$template->registerTagHandler('cms:children', $this, 'tag_CategoryList');

		// parse template
		if (is_object($item)) {
			$image_url = '';
			$thumbnail_url = '';

			// get number of children for this category
			$child_count = $manager->getItemValue('count(*)', array('parent' => $item->id));

			// get image urls
			if (ModuleHandler::is_loaded('gallery')) {
				$gallery = gallery::getInstance();
				$gallery_manager = GalleryManager::getInstance();
				$image = $gallery_manager->getSingleItem(
												array('filename'),
												array('id' => $item->image)
											);

				if (!is_null($image)) {
					$image_url = $gallery->getImageURL($image);
					$thumbnail_url = $gallery->getThumbnailURL($image);
				}
			}

			$params = array(
						'id'			=> $item->id,
						'parent'		=> $item->parent,
						'image_id'		=> $item->image,
						'image'			=> $image_url,
						'thumbnail'		=> $thumbnail_url,
						'text_id'		=> $item->text_id,
						'title'			=> $item->title,
						'description'	=> $item->description,
						'has_children'	=> $child_count > 0
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Tag handler for category list
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CategoryList($tag_params, $children) {
		global $language;

		$manager = ShopCategoryManager::getInstance();
		$conditions = array();
		$order_by = array();
		$order_asc = true;
		$item_category_ids = array();
		$item_id = isset($tag_params['item_id']) ? fix_id($tag_params['item_id']) : null;

		// create conditions
		if (isset($tag_params['parent_id'])) {
			// set parent from tag parameter
			$conditions['parent'] = fix_id($tag_params['parent_id']);

		} else if (isset($tag_params['parent'])) {
			// get parent id from specified text id
			$text_id = fix_chars($tag_params['parent']);
			$parent = $manager->getSingleItem(array('id'), array('text_id' => $text_id));

			if (is_object($parent))
				$conditions['parent'] = $parent->id; else
				$conditions['parent'] = -1;

		} else {
			if (!isset($tag_params['show_all']))
				$conditions['parent'] = 0;
		}

		if (isset($tag_params['level']))
			$level = fix_id($tag_params['level']); else
			$level = 0;

		if (isset($tag_params['exclude'])) {
			$list = fix_id(explode(',', $tag_params['exclude']));
			$conditions['id'] = array('operator' => 'NOT IN', 'value' => $list);
		}

		if (!is_null($item_id)) {
			$membership_manager = ShopItemMembershipManager::getInstance();
			$membership_items = $membership_manager->getItems(
												array('category'),
												array('item' => $item_id)
											);

			if (count($membership_items) > 0)
				foreach($membership_items as $membership)
					$item_category_ids[] = $membership->category;
		}

		// get order list
		if (isset($tag_params['order_by']))
			$order_by = fix_chars(explode(',', $tag_params['order_by'])); else
			$order_by = array('title_'.$language);

		if (isset($tag_params['order_ascending']))
			$order_asc = $tag_params['order_asc'] == '1' or $tag_params['order_asc'] == 'yes'; else

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions, $order_by, $order_asc);

		// create template handler
		$template = $this->_parent->loadTemplate($tag_params, 'category_list_item.xml');
		$template->setTemplateParamsFromArray($children);
		$template->registerTagHandler('cms:children', $this, 'tag_CategoryList');

		// initialize index
		$index = 0;

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				$image_url = '';
				$thumbnail_url = '';

				// get number of children for this category
				$child_count = $manager->getItemValue('count(*)', array('parent' => $item->id));

				// get image urls
				if (ModuleHandler::is_loaded('gallery')) {
					$gallery = gallery::getInstance();
					$gallery_manager = GalleryManager::getInstance();
					$image = $gallery_manager->getSingleItem(
													array('filename'),
													array('id' => $item->image)
												);

					if (!is_null($image)) {
						$image_url = $gallery->getImageURL($image);
						$thumbnail_url = $gallery->getThumbnailURL($image);
					}
				}

				$params = array(
							'id'			=> $item->id,
							'index'			=> $index++,
							'item_id'		=> $item_id,
							'parent'		=> $item->parent,
							'image_id'		=> $item->image,
							'image'			=> $image_url,
							'thumbnail'		=> $thumbnail_url,
							'text_id'		=> $item->text_id,
							'title'			=> $item->title,
							'description'	=> $item->description,
							'has_children'	=> $child_count > 0,
							'level'			=> $level,
							'in_category'	=> in_array($item->id, $item_category_ids) ? 1 : 0,
							'selected'		=> isset($tag_params['selected']) ? fix_id($tag_params['selected']) : 0,
							'item_change'	=> URL::make_hyperlink(
										$this->_parent->getLanguageConstant('change'),
										window_Open(
											'shop_category_change', 	// window id
											400,			// width
											$this->_parent->getLanguageConstant('title_category_change'), // title
											false, false,
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'categories'),
												array('sub_action', 'change'),
												array('id', $item->id)
											)
										)
									),
							'item_delete'	=> URL::make_hyperlink(
										$this->_parent->getLanguageConstant('delete'),
										window_Open(
											'shop_category_delete', 	// window id
											270,			// width
											$this->_parent->getLanguageConstant('title_category_delete'), // title
											false, false,
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'categories'),
												array('sub_action', 'delete'),
												array('id', $item->id)
											)
										)
									),
							'item_add'		=> URL::make_hyperlink(
										$this->_parent->getLanguageConstant('add'),
										window_Open(
											'shop_category_add', 	// window id
											400,			// width
											$this->_parent->getLanguageConstant('title_category_add'), // title
											false, false,
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'categories'),
												array('sub_action', 'add'),
												array('parent', $item->id)
											)
										)
									),
						);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}
}

?>
