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

// 		$template->registerTagHandler('_news_list', &$this, 'tag_NewsList');
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

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}
	
}

?>