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
					'link_new' => url_MakeHyperlink(
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

 		$template->registerTagHandler('_category', &$this, 'tag_Category');
 		$template->registerTagHandler('_category_list', &$this, 'tag_CategoryList');
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
		$template->registerTagHandler('_category', &$this, 'tag_Category');
		$template->registerTagHandler('_category_list', &$this, 'tag_CategoryList');

		if (class_exists('gallery')) {
			$gallery = gallery::getInstance();
			$template->registerTagHandler('_image_list', &$gallery, 'tag_ImageList');
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
			$template->registerTagHandler('_category_list', &$this, 'tag_CategoryList');

			if (class_exists('gallery')) {
				$gallery = gallery::getInstance();
				$template->registerTagHandler('_image_list', &$gallery, 'tag_ImageList');
			}

			// prepare parameters
			$params = array(
						'id'			=> $item->id,
						'parent'		=> $item->parent,
						'image'			=> $item->image,
						'title'			=> unfix_chars($item->title),
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
				'title'			=> fix_chars($this->_parent->getMultilanguageField('title')),
				'description'	=> escape_chars($this->_parent->getMultilanguageField('description'))
			);

		// get image if set and gallery is activated
		if (isset($_REQUEST['image']) & class_exists('gallery'))
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
											url_Make(
												'transfer_control',
												'backend_module',
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
		$id = isset($tag_params['id']) ? fix_id($tag_params['id']) : -1;
		$manager = ShopCategoryManager::getInstance();
		$conditions = array();

		// create conditions
		$conditions['id'] = $id;

		// get item from database
		$item = $manager->getSingleItem($manager->getFieldNames(), $conditions);

		// create template handler
		$template = $this->_parent->loadTemplate($tag_params, 'category.xml');
		$template->registerTagHandler('_children', &$this, 'tag_CategoryList');

		// parse template
		if (is_object($item)) {
			$image_url = '';
			$thumbnail_url = '';

			if (class_exists('gallery')) {
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
						'title'			=> $item->title,
						'description'	=> $item->description
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
		$manager = ShopCategoryManager::getInstance();
		$conditions = array();
		$item_category_ids = array();

		// create conditions
		if (isset($tag_params['parent']))
			$conditions['parent'] = fix_id($tag_params['parent']); else
			if (!isset($tag_params['show_all']))
				$conditions['parent'] = 0;

		if (isset($tag_params['level']))
			$level = fix_id($tag_params['level']); else
			$level = 0;

		if (isset($tag_params['exclude'])) {
			$list = fix_id(explode(',', $tag_params['exclude']));
			$conditions['id'] = array('operator' => 'NOT IN', 'value' => $list);
		}
		
		if (isset($tag_params['item_id'])) {
			$membership_manager = ShopItemMembershipManager::getInstance();
			$membership_items = $membership_manager->getItems(
												array('category'), 
												array('item' => fix_id($tag_params['item_id']))
											);
											
			if (count($membership_items) > 0) 
				foreach($membership_items as $membership)
					$item_category_ids[] = $membership->category;
		}

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// create template handler
		$template = $this->_parent->loadTemplate($tag_params, 'category_list_item.xml');
		$template->registerTagHandler('_children', &$this, 'tag_CategoryList');

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				$image_url = '';
				$thumbnail_url = '';

				if (class_exists('gallery')) {
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
							'title'			=> $item->title,
							'description'	=> $item->description,
							'level'			=> $level,
							'in_category'	=> in_array($item->id, $item_category_ids) ? 1 : 0,
							'selected'		=> isset($tag_params['selected']) ? fix_id($tag_params['selected']) : 0,
							'item_change'	=> url_MakeHyperlink(
										$this->_parent->getLanguageConstant('change'),
										window_Open(
											'shop_category_change', 	// window id
											400,			// width
											$this->_parent->getLanguageConstant('title_category_change'), // title
											false, false,
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'categories'),
												array('sub_action', 'change'),
												array('id', $item->id)
											)
										)
									),
							'item_delete'	=> url_MakeHyperlink(
										$this->_parent->getLanguageConstant('delete'),
										window_Open(
											'shop_category_delete', 	// window id
											270,			// width
											$this->_parent->getLanguageConstant('title_category_delete'), // title
											false, false,
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'categories'),
												array('sub_action', 'delete'),
												array('id', $item->id)
											)
										)
									),
							'item_add'		=> url_MakeHyperlink(
										$this->_parent->getLanguageConstant('add'),
										window_Open(
											'shop_category_add', 	// window id
											400,			// width
											$this->_parent->getLanguageConstant('title_category_add'), // title
											false, false,
											url_Make(
												'transfer_control',
												'backend_module',
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
