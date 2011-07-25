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

		// register currency list
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
	 * Handle displaying list of shop items
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CurrencyList($tag_params, $children) {
		$manager = ShopItemManager::getInstance();
		$conditions = array();

		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('item_list_item.xml', $this->path.'templates/');
		}
		
		if (count($items) > 0)
			foreach ($items as $item) {
				$params = $this->getCurrencyForCode($item->currency);

				// add delete link to params
				$params['item_delete'] = url_MakeHyperlink(
										$this->_parent->getLanguageConstant('delete'),
										window_Open(
											'shop_currencies_delete', 	// window id
											270,			// width
											$this->_parent->getLanguageConstant('title_currencies_delete'), // title
											false, false,
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'currencies'),
												array('sub_action', 'delete'),
												array('id', $item->id)
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
