<?php

/**
 * Currency converter and manager
 *
 * http://www.google.com/ig/calculator?hl=en&q=100EUR%3D%3Frsd
 *
 */

class ShopCurrenciesHandler {
	private static $_instance;
	private $_parent;
	private $_cache = null;
	private $name;
	private $path;

	private $update_url = 'http://www.currency-iso.org/dl_iso_table_a1.xml';

	/**
	* Constructor
	*/
	protected function __construct($parent) {
		$this->_parent = $parent;
		$this->name = $this->_parent->name;
		$this->path = $this->_parent->path;
	}

	/**
	 * Update currency cache
	 */
	private function __update_cache($force=False) {
		if (!is_null($this->_cache) && !$force) return;

		$filename = $this->path.'data/iso_currencies.xml';

		if (file_exists($filename)) {
			// get XML file
			$data = file_get_contents($filename);

			$xml = new XMLParser($data, $filename);
			$xml->Parse();

			$currencies = array();

			// create list with unique data
			foreach($xml->document->tagChildren as $iso_currency) {
				// extract data
				$entity = $iso_currency->tagChildren[0]->tagData;
				$currency = $iso_currency->tagChildren[1]->tagData;
				$alphabetic_code = $iso_currency->tagChildren[2]->tagData;
				$numeric_code = $iso_currency->tagChildren[3]->tagData;
				$minor_unit = $iso_currency->tagChildren[4]->tagData;

				if ($numeric_code == '' || $alphabetic_code == 'XXX') continue;

				$currencies[] = array(
									'entity'			=> $entity,
									'currency'			=> $currency,
									'alphabetic_code'	=> $alphabetic_code,
									'numeric_code'		=> $numeric_code,
									'minor_unit'		=> $minor_unit
								);

				// update cache
				$this->_cache = $currencies;
			}
		}
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
				$this->addCurrency();
				break;

			case 'save':
				$this->saveCurrency();
				break;

			case 'delete':
				$this->deleteCurrency();
				break;

			case 'delete_commit':
				$this->deleteCurrency_Commit();
				break;

			case 'update':
				$this->updateCurrencyList();
				break;

			default:
				$this->showCurrencies();
				break;
		}
	}

	/**
	 * Show currencies management form
	 */
	private function showCurrencies() {
		$template = new TemplateHandler('currency_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new' => url_MakeHyperlink(
										$this->_parent->getLanguageConstant('add_currency'),
										window_Open( // on click open window
											'shop_currencies_add',
											320,
											$this->_parent->getLanguageConstant('title_currencies_add'),
											true, true,
											backend_UrlMake($this->name, 'currencies', 'add')
										)
									),
					'link_update' => url_MakeHyperlink(
										$this->_parent->getLanguageConstant('update_currencies'),
										window_Open( // on click open window
											'shop_currencies_update',
											270,
											$this->_parent->getLanguageConstant('title_currencies_update'),
											true, true,
											backend_UrlMake($this->name, 'currencies', 'update')
										)
									)
					);

 		$template->registerTagHandler('_currency_list', &$this, 'tag_CurrencyList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for adding new shop item
	 */
	private function addCurrency() {
		$template = new TemplateHandler('currency_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'currencies', 'save'),
					'cancel_action'	=> window_Close('shop_currencies_add')
				);

		$template->registerTagHandler('_currency_list', &$this, 'tag_IsoCurrencyList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save new currency
	 */
	private function saveCurrency() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = ShopCurrenciesManager::getInstance();

		$data = array(
					'currency' => fix_chars($_REQUEST['currency'])
				);

		$manager->insertData($data);

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant('message_currency_saved'),
					'button'	=> $this->_parent->getLanguageConstant('close'),
					'action'	=> window_Close('shop_currencies_add').";".window_ReloadContent('shop_currencies'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation dialog before deleting currency
	 */
	private function deleteCurrency() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = ShopCurrenciesManager::getInstance();

		$item = $manager->getSingleItem(array('currency'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->_parent->getLanguageConstant("message_currency_delete"),
					'name'			=> $item->currency,
					'yes_text'		=> $this->_parent->getLanguageConstant("delete"),
					'no_text'		=> $this->_parent->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'shop_currencies_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'currencies'),
												array('sub_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('shop_currencies_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Delete specified currency
	 */
	private function deleteCurrency_Commit() {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = ShopCurrenciesManager::getInstance();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant("message_currency_deleted"),
					'button'	=> $this->_parent->getLanguageConstant("close"),
					'action'	=> window_Close('shop_currencies_delete').";"
									.window_ReloadContent('shop_currencies')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Update currency list from ISO web site
	 */
	private function updateCurrencyList() {
		$data = file_get_contents($this->update_url);
		$filename = $this->path.'data/iso_currencies.xml';

		// store new data
		file_put_contents($filename, $data);

		// update cache
		$this->__update_cache(True);

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->_parent->getLanguageConstant("message_currency_list_updated"),
					'button'	=> $this->_parent->getLanguageConstant("close"),
					'action'	=> window_Close('shop_currencies_update')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Handle displaying list of stored currencies
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CurrencyList($tag_params, $children) {
		$manager = ShopCurrenciesManager::getInstance();
		$conditions = array();

		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('currency_list_item.xml', $this->path.'templates/');
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

	/**
	 * Handle displaying list of ISO defined currencies
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_IsoCurrencyList($tag_params, $children) {
		// whether to to print only unique currencies
		$only_unique = isset($tag_params['unique']) && $tag_params['unique'] == '1';

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('iso_currency.xml', $this->path.'templates/');
		}

		// update cache
		$this->__update_cache();

		$displayed = array();

		foreach ($this->_cache as $currency_data) {
			if ($only_unique && in_array($currency_data['numeric_code'], $displayed)) continue;
			
			// store displayed currency
			$displayed[] = $currency_data['numeric_code'];

			// parse template
			$template->restoreXML();
			$template->setLocalParams($currency_data);
			$template->parse();
		}
	}

	/**
	 * Get currency data for specified aphabetic code.
	 *
	 * @param string $code
	 * @return array
	 */
	public function getCurrencyForCode($code) {
		$this->__update_cache();

		$result = null;

		foreach ($this->_cache as $currency) {
			if ($currency['alphabetic_code'] == $code) {
				$result = $currency;
				break;
			}
		}

		return $result;
	}
}

?>
