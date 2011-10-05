<?php

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
				break;

			case 'save':
				$this->saveItem();
				break;

			case 'delete':
				break;

			case 'delete_commit':
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
		$template->setMappedModule($this->name);

		$params = array(
					'link_new' => url_MakeHyperlink(
										$this->_parent->getLanguageConstant('add_item'),
										window_Open( // on click open window
											'shop_item_add',
											490,
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
		$template->registerTagHandler('_item_list', &$this, 'tag_ItemList');

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

		$currency_module = ShopCurrenciesHandler::getInstance($this->_parent);
		$template->registerTagHandler('_currency_list', &$currency_module, 'tag_CurrencyList');

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
			$template->registerTagHandler('_category_list', &$this, 'tag_CategoryList');

			// prepare parameters
			$params = array(
						'id'			=> $item->id,

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
	 * Save new or changed item data
	 */
	private function saveItem() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = ShopItemManager::getInstance();
		$open_editor = "";

		$data = array(
				'name'			=> $this->_parent->getMultilanguageField('name'),
				'description'	=> $this->_parent->getMultilanguageField('description'),
				'price'			=> isset($_REQUEST['price']) ? fix_chars($_REQUEST['price']) : 0,
			);

		if (is_null($id)) {
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

		}

		if (isset($_REQUEST['category'])) {
			// update default category
		}

		// store item data
		if (is_null($id)) {
			// store new data
			$manager->insertData($data);
			$window = 'shop_item_add';

		} else {
			// update existing data
			$manager->updateData($data, array('id' => $id));
			$window = 'shop_item_change';
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
	 * Handle drawing item list
	 *
	 * @param array $tag_params
	 * @param array $chilren
	 */
	public function tag_ItemList($tag_params, $children) {
		$manager = ShopItemManager::getInstance();
		$conditions = array();

		// create conditions
		if (isset($tag_params['category'])) {
		}

		// get items
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// create template
		$template = $this->_parent->loadTemplate($tag_params, 'item_list_item.xml');
		$template->setMappedModule($this->name);

		if (count($items) > 0)
			foreach ($items as $item) {
				$rating = 0;
				$params = array(
							'id'			=> $item->id,
							'uid'			=> $item->uid,
							'name'			=> $item->name,
							'description'	=> $item->description,
							'gallery'		=> $item->gallery,
							'author'		=> $item->author,
							'views'			=> $item->views,
							'price'			=> $item->price,
							'votes_up'		=> $item->votes_up,
							'votes_down'	=> $item->votes_down,
							'rating'		=> $rating,
							'timestamp'		=> $item->timestamp,
							'visible'		=> $item->visible,
							'deleted'		=> $item->deleted,
							'item_change'	=> '',
							'item_delete'	=> ''
						);

				$template->restoreXML();
				$template->setLocalParams($params);
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
				// item was found, prepare result
				$result['item'] = array(
								'id'			=> $item->id,
								'uid'			=> $item->uid,
								'name'			=> $item->name,
								'description'	=> $item->description,
								'gallery'		=> $item->gallery,
								'views'			=> $item->views,
								'price'			=> $item->price,
								'votes_up'		=> $item->votes_up,
								'votes_down'	=> $item->votes_down,
								'rating'		=> $rating,
								'timestamp'		=> $item->timestamp,
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
