<?php

/**
 * Shop Module
 *
 * @author MeanEYE.rcf
 */

require_once('units/payment_method.php');
require_once('units/shop_item_handler.php');
require_once('units/shop_category_handler.php');
require_once('units/shop_currencies_handler.php');
require_once('units/shop_item_sizes_handler.php');
require_once('units/shop_item_size_values_manager.php');
require_once('units/shop_transactions_manager.php');
require_once('units/shop_transaction_items_manager.php');
require_once('units/shop_buyers_manager.php');
require_once('units/shop_buyer_addresses_manager.php');


class TransactionType {
	const SUBSCRIPTION = 0;
	const SHOPPING_CART = 1;
	const DONATION = 2;
}


class TransactionStatus {
	const PENDING = 0;
	const DENIED = 1;
	const COMPLETED = 2;
}


class shop extends Module {
	private static $_instance;
	private $payment_methods;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// create payment providers container
		$this->payment_methods = array();

		// load module style and scripts
		if (class_exists('head_tag') && $section != 'backend') {
			$head_tag = head_tag::getInstance();
			$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/checkout.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/shopping_cart.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/shopping_cart.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if (class_exists('backend') && $section == 'backend') {
			$head_tag = head_tag::getInstance();
			$backend = backend::getInstance();

			if (class_exists('head_tag'))
				$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/multiple_images.js'), 'type'=>'text/javascript'));

			$shop_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_shop'),
					url_GetFromFilePath($this->path.'images/icon.png'),
					'javascript:void(0);',
					5  // level
				);

			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_items'),
								url_GetFromFilePath($this->path.'images/items.png'),
								window_Open( // on click open window
											'shop_items',
											580,
											$this->getLanguageConstant('title_manage_items'),
											true, true,
											backend_UrlMake($this->name, 'items')
										),
								5  // level
							));
			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_categories'),
								url_GetFromFilePath($this->path.'images/categories.png'),
								window_Open( // on click open window
											'shop_categories',
											490,
											$this->getLanguageConstant('title_manage_categories'),
											true, true,
											backend_UrlMake($this->name, 'categories')
										),
								5  // level
							));
			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_item_sizes'),
								url_GetFromFilePath($this->path.'images/item_sizes.png'),
								window_Open( // on click open window
											'shop_item_sizes',
											400,
											$this->getLanguageConstant('title_manage_item_sizes'),
											true, true,
											backend_UrlMake($this->name, 'sizes')
										),
								5  // level
							));
							
			$shop_menu->addSeparator(5);
						
			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_special_offers'),
								url_GetFromFilePath($this->path.'images/special_offers.png'),
								window_Open( // on click open window
											'shop_special_offers',
											490,
											$this->getLanguageConstant('title_special_offers'),
											true, true,
											backend_UrlMake($this->name, 'special_offers')
										),
								5  // level
							));

			$shop_menu->addSeparator(5);

			// payment methods menu
			$methods_menu = new backend_MenuItem(
								$this->getLanguageConstant('menu_payment_methods'),
								url_GetFromFilePath($this->path.'images/payment_methods.png'),
								'javascript: void(0);', 5
							);

			$shop_menu->addChild('shop_payment_methods', $methods_menu);

			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_currencies'),
								url_GetFromFilePath($this->path.'images/currencies.png'),
								window_Open( // on click open window
											'shop_currencies',
											350,
											$this->getLanguageConstant('title_currencies'),
											true, true,
											backend_UrlMake($this->name, 'currencies')
										),
								5  // level
							));

			$shop_menu->addSeparator(5);

			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_purchases'),
								url_GetFromFilePath($this->path.'images/purchase.png'),
								window_Open( // on click open window
											'shop_purchases',
											490,
											$this->getLanguageConstant('title_purchases'),
											true, true,
											backend_UrlMake($this->name, 'purchases')
										),
								5  // level
							));
			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_stocks'),
								url_GetFromFilePath($this->path.'images/stock.png'),
								window_Open( // on click open window
											'shop_stocks',
											490,
											$this->getLanguageConstant('title_stocks'),
											true, true,
											backend_UrlMake($this->name, 'stocks')
										),
								5  // level
							));

			$backend->addMenu($this->name, $shop_menu);
		}
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show_item':
					$handler = ShopItemHandler::getInstance($this);
					$handler->tag_Item($params, $children);
					break;

				case 'show_item_list':
					$handler = ShopItemHandler::getInstance($this);
					$handler->tag_ItemList($params, $children);
					break;
					
				case 'show_category':
					$handler = ShopCategoryHandler::getInstance($this);
					$handler->tag_Category($params, $children);
					break;
					
				case 'show_category_list':
					$handler = ShopCategoryHandler::getInstance($this);
					$handler->tag_CategoryList($params, $children);
					break;

				case 'show_completed_message':
					$this->tag_CompletedMessage($params, $children);
					break;

				case 'show_canceled_message':
					$this->tag_CanceledMessage($params, $children);
					break;
					
				case 'checkout':
					$this->showCheckout();
					break;

				case 'checkout_completed':
					$this->showCheckoutCompleted();
					break;

				case 'checkout_canceled':
					$this->showCheckoutCanceled();
					break;

				case 'handle_payment':
					$this->handlePayment();
					break;
					
				case 'json_get_item':
					$handler = ShopItemHandler::getInstance($this);
					$handler->json_GetItem();
					break;

				case 'json_get_currency':
					$this->json_GetCurrency();
					break;

				case 'json_get_payment_methods':
					$this->json_GetPaymentMethods();
					break;

				case 'json_add_item_to_shopping_cart':
					$this->json_AddItemToCart();
					break;

				case 'json_remove_item_from_shopping_cart':
					$this->json_RemoveItemFromCart();
					break;

				case 'json_change_item_quantity':
					$this->json_ChangeItemQuantity();
					break;

				case 'json_clear_shopping_cart':
					$this->json_ClearCart();
					break;

				case 'json_get_shopping_cart':
					$this->json_ShowCart();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action'])) {
			$action = $params['backend_action'];

			switch ($action) {
				case 'items':
					$handler = ShopItemHandler::getInstance($this);
					$handler->transferControl($params, $children);
					break;

				case 'currencies':
					$handler = ShopCurrenciesHandler::getInstance($this);
					$handler->transferControl($params, $children);
					break;

				case 'categories':
					$handler = ShopCategoryHandler::getInstance($this);
					$handler->transferControl($params, $children);
					break;
					
				case 'sizes':
					$handler = ShopItemSizesHandler::getInstance($this);
					$handler->transferControl($params, $children);
					break;

				case 'special_offers':

				case 'purchases':

				case 'stocks':

				case 'payment_methods':

				default:
					break;
			}
		}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db_active, $db;

		$list = MainLanguageHandler::getInstance()->getLanguages(false);

		// create shop items table
		$sql = "
			CREATE TABLE `shop_items` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`uid` VARCHAR(13) NOT NULL,";

		foreach($list as $language)
			$sql .= "`name_{$language}` VARCHAR( 255 ) NOT NULL DEFAULT '',";

		foreach($list as $language)
			$sql .= "`description_{$language}` TEXT NOT NULL ,";

		$sql .= "
				`gallery` INT(11) NOT NULL,
				`size_definition` INT(11) NULL,
				`author` INT(11) NOT NULL,
				`views` INT(11) NOT NULL,
				`price` DECIMAL(8,2) NOT NULL,
				`tax` DECIMAL(3,2) NOT NULL,
				`weight` DECIMAL(8,2) NOT NULL,
				`votes_up` INT(11) NOT NULL,
				`votes_down` INT(11) NOT NULL,
				`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`visible` BOOLEAN NOT NULL DEFAULT '1',
				`deleted` BOOLEAN NOT NULL DEFAULT '0',
				PRIMARY KEY ( `id` ),
				KEY `visible` (`visible`),
				KEY `deleted` (`deleted`),
				KEY `uid` (`uid`),
				KEY `author` (`author`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		// create shop currencies table
		$sql = "
			CREATE TABLE `shop_item_membership` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`category` INT(11) NOT NULL,
				`item` INT(11) NOT NULL,
				PRIMARY KEY ( `id` ),
				KEY `category` (`category`),
				KEY `item` (`item`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		// create shop currencies table
		$sql = "
			CREATE TABLE `shop_currencies` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`currency` VARCHAR(5) NOT NULL,
				PRIMARY KEY ( `id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
		// create shop item sizes table
		$sql = "
			CREATE TABLE `shop_item_sizes` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`name` VARCHAR(25) NOT NULL,
				PRIMARY KEY ( `id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
		// create shop item size values table
		$sql = "
			CREATE TABLE `shop_item_size_values` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`definition` int(11) NOT NULL,";
				
		foreach($list as $language)
			$sql .= "`value_{$language}` VARCHAR( 50 ) NOT NULL DEFAULT '',";
			
		$sql .= "PRIMARY KEY ( `id` ),
				KEY `definition` (`definition`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		// create shop categories table
		$sql = "
			CREATE TABLE `shop_categories` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`parent` INT(11) NOT NULL DEFAULT '0',
				`image` INT(11),";

		foreach($list as $language)
			$sql .= "`title_{$language}` VARCHAR( 255 ) NOT NULL DEFAULT '',";

		foreach($list as $language)
			$sql .= "`description_{$language}` TEXT NOT NULL ,";

		$sql .="
				PRIMARY KEY ( `id` ),
				KEY `parent` (`parent`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
		// create shop buyers table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_buyers` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `first_name` varchar(64) NOT NULL,
				  `last_name` varchar(64) NOT NULL,
				  `email` varchar(127) NOT NULL,
				  `uid` varchar(50) NOT NULL,
				  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
		// create shop buyer addresses table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_buyer_addresses` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `buyer` int(11) NOT NULL,
				  `name` varchar(128) NOT NULL,
				  `street` varchar(200) NOT NULL,
				  `city` varchar(40) NOT NULL,
				  `zip` varchar(20) NOT NULL,
				  `state` varchar(40) NOT NULL,
				  `country` varchar(64) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `buyer` (`buyer`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
		// create shop transactions table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_transactions` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `buyer` int(11) NOT NULL,
				  `address` int(11) NOT NULL,
				  `uid` varchar(20) NOT NULL,
				  `type` smallint(6) NOT NULL,
				  `status` smallint(6) NOT NULL,
				  `custom` varchar(200) NOT NULL,
				  `currency` int(11) NOT NULL,
				  `handling` decimal(8,2) NOT NULL,
				  `shipping` decimal(8,2) NOT NULL,
				  `fee` decimal(8,2) NOT NULL,
				  `tax` decimal(8,2) NOT NULL,
				  `gross` decimal(8,2) NOT NULL,
				  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				  PRIMARY KEY (`id`),
				  KEY `buyer` (`buyer`),
				  KEY `address` (`address`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";		
		if ($db_active == 1) $db->query($sql);
		
		// create shop transaction items table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_transaction_items` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `transaction` int(11) NOT NULL,
				  `item` int(11) NOT NULL,
				  `price` DECIMAL(8,2) NOT NULL,
				  `tax` DECIMAL(8,2) NOT NULL,
				  `amount` int(11) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `transaction` (`transaction`),
				  KEY `item` (`item`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
		// create shop stock table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_stock` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `item` int(11) NOT NULL,
				  `size` int(11) DEFAULT NULL,
				  `amount` int(11) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `item` (`item`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db_active, $db;

		$tables = array(
					'shop_items',
					'shop_currencies',
					'shop_categories',
					'shop_item_membership',
					'shop_item_sizes',
					'shop_item_size_values',
					'shop_buyers',
					'shop_buyer_addresses',
					'shop_transactions',
					'shop_transaction_items',
					'shop_stock'
				);
		
		$sql = "DROP TABLE IF EXISTS `".join('`, `', $tables)."`;";
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Method used by payment providers to register them selfs
	 *
	 * @param string $name
	 * @param object $module
	 */
	public function registerPaymentMethod($name, $module) {
		$this->payment_methods[$name] = $module;
	}

	/**
	 * Show checkout form
	 */
	public function showCheckout() {
		$method = isset($_REQUEST['method']) ? fix_chars($_REQUEST['method']) : null;

		// try finding a correct payment method if it's not specified
		if (is_null($method)) {
		}

		// parse checkout template
		if (!is_null($method)) {
			$template = new TemplateHandler('checkout.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
					);

			// register tag handler
			$template->registerTagHandler('_checkout_form', &$this, 'tag_CheckoutForm');

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Show message for completed checkout and empty shopping cart
	 */
	private function showCheckoutCompleted() {
		$template = new TemplateHandler('checkout_completed.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('_completed_message', &$this, 'tag_CompletedMessage');

		$params = array(
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show message for canceled checkout
	 */
	private function showCheckoutCanceled() {
		$template = new TemplateHandler('checkout_canceled.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('_canceled_message', &$this, 'tag_CanceledMessage');

		$params = array(
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Handle payment notification
	 */
	private function handlePayment() {
		$method_name = isset($_REQUEST['method']) ? fix_chars($_REQUEST['method']) : null;

		// return if no payment method is specified
		if (is_null($method_name) || !array_key_exists($method_name, $this->payment_methods))
			return;

		// verify payment
		$method = $this->payment_methods[$method_name];
		if ($method->verify_payment()) {
			// get data from payment method
			$payment_info = $method->get_payment_info();
			$transaction_info = $method->get_transaction_info();
			$buyer_info = $method->get_buyer_info();
			$items_info = $method->get_items();

			// get managers
			$items_manager = ShopItemManager::getInstance();
			$transactions_manager = ShopTransactionsManager::getInstance();
			$transaction_items_manager = ShopTransactionItemsManager::getInstance();
			$buyers_manager = ShopBuyersManager::getInstance();
			$buyer_addresses_manager = ShopBuyerAddressesManager::getInstance();
			$currencies_manager = ShopCurrenciesManager::getInstance();

			// try to get existing transaction
			$transaction = $transactions_manager->getSingleItem(
											array('id'),
											array('uid' => $transaction_info['id'])
										);

			if (is_object($transaction)) {
				// transaction already exists, we need to update status only
				$transactions_manager->updateData(
											array('status'	=> $transaction_info['status']),
											array('id'		=> $transaction->id)
										);

			} else {
				// transaction doesn't exist, try to get a buyer
				$buyer = $buyers_manager->getSingleItem(
												array('id'), 
												array('email' => $buyer_info['email'])
											);

				if (is_object($buyer)) {
					// buyer already exists in database
					$buyer_id = $buyer->id;

				} else {
					// new buyer, record data
					$data = $buyer_info;
					unset($data['address']);

					$buyers_manager->insertData($data);
					$buyer_id = $buyers_manager->getInsertedID();
				}

				// check if specified address for this buyer already exists
				$address = $buyer_addresses_manager->getSingleItem(
												array('id'),
												array(
													'buyer'	=> $buyer_id,
													'name'	=> $buyer_info['address']['name']
												)
											);

				if (is_object($address)) {
					// address is known to us, use it
					$address_id = $address->id;

				} else {
					// new address, record data
					$data = $buyer_info['address'];
					$data['buyer'] = $buyer_id;

					$buyer_addresses_manager->insertData($data);
					$address_id = $buyer_addresses_manager->getInsertedID();
				}

				// get currency based on code
				$currency = $currencies_manager->getSingleItem(
												array('id'),
												array('currency' => $payment_info['currency'])
											);

				if (is_object($currency))
					$currency_id = $currency->id; else
					$currency_id = 0;

				// record transaction and its items
				$data = array_merge($payment_info, $transaction_info);
				
				unset($data['id']);
				$data['address'] = $address_id;
				$data['buyer'] = $buyer_id;
				$data['currency'] = $currency_id;
				$data['uid'] = $transaction_info['id'];

				define('SQL_DEBUG', 1);
				$transactions_manager->insertData($data);
				$transaction_id = $transactions_manager->getInsertedID();

				
				trigger_error(print_r($items_info, true));

				// store items
				foreach ($items_info as $item_info) {
					$item = $items_manager->getSingleItem(
												array('id'), 
												array('uid' => $item_info['uid'])
											);

					// only record items that are valid
					if (is_object($item)) {
						$data = array(
								'transaction'	=> $transaction_id,
								'item'			=> $item->id,
								'price'			=> $item_info['price'],
								'tax'			=> $item_info['tax'],
								'amount'		=> $item_info['quantity']
							);
						$transaction_items_manager->insertData($data);
					}
				}
			}
		}
	}

	/**
	 * Return default currency using JSON object
	 */
	private function json_GetCurrency() {
		define('_OMIT_STATS', 1);

		print json_encode($this->getDefaultCurrency());
	}

	/**
	 * Show shopping card in form of JSON object
	 */
	private function json_ShowCart() {
		$manager = ShopItemManager::getInstance();
		$values_manager = ShopItemSizeValuesManager::getInstance();
		$gallery = class_exists('gallery') ? gallery::getInstance() : null;

		$result = array();

		// get shopping cart from session
		$result['cart'] = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$result['size_values'] = array();
		$result['count'] = count($result['cart']);
		$result['currency'] = $this->getDefaultCurrency();

		// colect ids from session
		$ids = array_keys($result['cart']);

		// get items from database and prepare result
		$items = $manager->getItems($manager->getFieldNames(), array('uid' => $ids));
		$values = $values_manager->getItems($values_manager->getFieldNames(), array());

		if (count($items) > 0) 
			foreach ($items as $item) {
				// get item image url
				$thumbnail_url = !is_null($gallery) ? $gallery->getGroupThumbnailURL($item->gallery) : '';

				$uid = $item->uid;
				$result['cart'][$uid]['name'] = $item->name;
				$result['cart'][$uid]['weight'] = $item->weight;
				$result['cart'][$uid]['price'] = $item->price;
				$result['cart'][$uid]['tax'] = $item->tax;
				$result['cart'][$uid]['image'] = $thumbnail_url;
			}
		
		if (count($values) > 0) 
			foreach ($values as $value) {
				$result['size_values'][$value->id] = array(
											'definition'	=> $value->definition,
											'value'			=> $value->value
										);
			}
			
		// prevent stats from displaying
		define('_OMIT_STATS', 1);

		print json_encode($result);
	}

	/**
	 * Clear shopping cart and return result in form of JSON object
	 */
	private function json_ClearCart() {
		// prevent stats from displaying
		define('_OMIT_STATS', 1);

		$_SESSION['shopping_cart'] = array();

		print json_encode(true);
	}

	/**
	 * Add item to shopping cart using JSON request
	 */
	private function json_AddItemToCart() {
		$uid = fix_chars($_REQUEST['uid']);
		$size = isset($_REQUEST['size']) ? fix_id($_REQUEST['size']) : null;
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();

		// try to get item from database
		$manager = ShopItemManager::getInstance();
		$item = $manager->getSingleItem($manager->getFieldNames(), array('uid' => $uid));

		// default result is false
		$result = null;

		if (is_object($item)) {
			if (array_key_exists($uid, $cart)) {
				// update existing item count
				$cart[$uid]['quantity']++;

			} else {
				// add new item to shopping cart
				$cart[$uid] = array(
							'uid'		=> $uid,
							'quantity'	=> 1,
							'sizes'		=> array()
						);
			}

			if (!is_null($size)) 
				if (array_key_exists($size, $cart[$uid]['sizes'])) {
					// increase existing size quantity
					$cart[$uid]['sizes'][$size]++;

				} else {
					// create new size quantity
					$cart[$uid]['sizes'][$size] = 1;
				}

			// get item image url
			$thumbnail_url = null;
			if (class_exists('gallery')) {
				$gallery = gallery::getInstance();
				$thumbnail_url = $gallery->getGroupThumbnailURL($item->gallery); 
			}

			// prepare result
			$result = $cart[$uid];
			$result['name'] = $item->name;
			$result['weight'] = $item->weight;
			$result['price'] = $item->price;
			$result['tax'] = $item->tax;
			$result['image'] = $thumbnail_url;

			// update shopping cart
			$_SESSION['shopping_cart'] = $cart;
		}

		define('_OMIT_STATS', 1);
		print json_encode($result);
	}

	/**
	 * Remove item from shopping cart using JSON request
	 */
	private function json_RemoveItemFromCart() {
		$uid = fix_chars($_REQUEST['uid']);
		$size = isset($_REQUEST['size']) ? fix_id($_REQUEST['size']) : null;
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$result = false;

		if (array_key_exists($uid, $cart)) {
			if (is_null($size)) {
				// remove item without size
				unset($cart[$uid]);

			} else {
				// remove specified size
				if (array_key_exists($size, $cart[$uid]['sizes']))
					unset($cart[$uid]['sizes'][$size]);

				$cart[$uid]['quantity'] = array_sum($cart[$uid]['sizes']);
			}

			$_SESSION['shopping_cart'] = $cart;
			$result = true;
		}

		define('_OMIT_STATS', 1);
		print json_encode($result);
	}

	private function json_ChangeItemQuantity() {
		$uid = fix_chars($_REQUEST['uid']);
		$size = isset($_REQUEST['size']) ? fix_id($_REQUEST['size']) : null;
		$quantity = fix_id($_REQUEST['quantity']);
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$result = false;

		if (array_key_exists($uid, $cart)) {
			if (is_null($size)) {
				// update quanity for item without sizes
				$cart[$uid]['quantity'] = $quantity;

			} else {
				// update quantity for item size
				if (array_key_exists($size, $cart[$uid]['sizes']))
					$cart[$uid]['sizes'][$size] = $quantity;
				
				$cart[$uid]['quantity'] = array_sum($cart[$uid]['sizes']);
			}

			$_SESSION['shopping_cart'] = $cart;
			$result = true;
		}

		define('_OMIT_STATS', 1);
		print json_encode($result);
	}

	/**
	 * Retrieve list of payment methods
	 */
	private function json_GetPaymentMethods() {
		$result = array();

		// prepare data for printing
		foreach ($this->payment_methods as $payment_method)	
			$result[] = array(
					'name'	=> $payment_method->get_name(),
					'title'	=> $payment_method->get_title(),
					'icon'	=> $payment_method->get_icon_url()
				);

		// prevent statistics from showing
		define('_OMIT_STATS', '1');

		// print data
		print json_encode($result);
	}

	/**
	 * Save default currency to module settings
	 * @param string $currency
	 */
	public function saveDefaultCurrency($currency) {
		$this->saveSetting('default_currency', $currency);
	}

	/**
	 * Return default currency
	 * @return string
	 */
	public function getDefaultCurrency() {
		return $this->settings['default_currency'];
	}

	/**
	 * Handle drawing checkout form
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CheckoutForm($tag_params, $children) {
		$manager = ShopItemManager::getInstance();
		$method = null;

		// try to get specified payment method
		if (isset($tag_params['method']) && array_key_exists($tag_params['method'], $this->payment_methods))
			$method = $this->payment_methods[fix_chars($tag_params['method'])];

		// try to get fallback method
		if (is_null($method) && count($this->payment_methods) > 0) {
			$keys = array_keys($this->payment_methods);
			$method = $this->payment_methods[$keys[0]];
		}

		// we didn't manage to get any payment method, bail out
		if (is_null($method))
			return;

		// load template
		$template = $this->loadTemplate($tag_params, 'checkout_form.xml');
		$template->setMappedModule($this->name);

		$template->registerTagHandler('_checkout_items', &$this, 'tag_CheckoutItems');

		// colect ids from session
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$ids = array_keys($cart);

		// get items from database and prepare result
		$items = $manager->getItems($manager->getFieldNames(), array('uid' => $ids));

		// prepare params
		$shipping = 0;
		$total_money = 0;
		$total_weight = 0;
		$items_for_checkout = array();

		foreach ($items as $item) {
			// include item data in summary
			$tax = $item->tax;
			$price = $item->price;
			$uid = $item->uid;
			
			$total_money += ($price * (1 + ($tax / 100))) * $cart[$uid]['quantity']; 
			$total_weight += $item->weight * $cart[$uid]['quantity'];

			// add item to array for payment method
			$db_item = array(
					'name'		=> $item->name,
					'price'		=> $item->price,
					'tax'		=> $item->tax,
					'weight'	=> $item->weight
				);
			$items_for_checkout[] = array_merge($db_item, $cart[$uid]);
		}

		// get fields from payment method
		$base_url = 'http://'.$_SERVER['HTTP_HOST'];
		$return_url = urlencode($base_url.url_Make('checkout_completed', 'shop'));
		$cancel_url = urlencode($base_url.url_Make('checkout_canceled', 'shop'));

		$checkout_fields = $method->new_payment(
									$items_for_checkout,
									$this->getDefaultCurrency(),
									$return_url,
									$cancel_url
								);

		// parse template
		$params = array(
					'checkout_url'		=> $method->get_url(),
					'checkout_fields'	=> $checkout_fields,
					'checkout_name'		=> $method->get_title(),
					'sub-total'			=> number_format($total_money, 2),
					'shipping'			=> number_format($shipping, 2),
					'total_weight'		=> number_format($total_weight, 2),
					'total'				=> number_format($total_money + $shipping, 2),
					'currency'			=> $this->getDefaultCurrency()
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Handle drawing checkout items
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CheckoutItems($tag_params, $children) {
		global $language;

		$manager = ShopItemManager::getInstance();
		$value_manager = ShopItemSizeValuesManager::getInstance();

		// colect ids from session
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$ids = array_keys($cart);

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), array('uid' => $ids));
		$values = $value_manager->getItems($value_manager->getFieldNames(), array());

		// prepare sizes cache
		$size_values = array();
		
		if (count($values) > 0)
			foreach ($values as $value)
				$size_values[$value->id] = $value->value;

		// load template
		$template = $this->loadTemplate($tag_params, 'checkout_form_item.xml');
		$template->setMappedModule($this->name);

		// parse template
		if (count($items) > 0)
			foreach ($items as $item) {
				$cart_item = $cart[$item->uid];

				if (count($cart_item['sizes'])) {
					// process more item sizes for same item
					foreach ($cart_item['sizes'] as $size_definition => $quantity) {
						$total = ($item->price * (1 + ($item->tax / 100))) * $quantity;
						$size_value = $size_values[$size_definition][$language];

						$params = array(
									'name'		=> $item->name,
									'size'		=> $size_value,
									'quantity'	=> $quantity,
									'price'		=> number_format($item->price, 2),
									'tax'		=> number_format($item->tax, 2),
									'weight'	=> number_format($item->weight, 2),
									'total'		=> number_format($total, 2)
								);

						$template->restoreXML();
						$template->setLocalParams($params);
						$template->parse();
					}

				} else {
					// process only one item size
					$quantity = $cart[$item->uid]['quantity'];
					$total = ($item->price * (1 + ($item->tax / 100))) * $quantity;

					$params = array(
								'name'		=> $item->name,
								'size'		=> '',
								'quantity'	=> $quantity,
								'price'		=> number_format($item->price, 2),
								'tax'		=> number_format($item->tax, 2),
								'weight'	=> number_format($item->weight, 2),
								'total'		=> number_format($total, 2)
							);

					$template->restoreXML();
					$template->setLocalParams($params);
					$template->parse();
				}
			}
	}

	/**
	 * Show message for completed checkout operation
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CompletedMessage($tag_params, $children) {
		// kill shopping cart
		$_SESSION['shopping_cart'] = array();

		// show message
		$template = $this->loadTemplate($tag_params, 'checkout_message.xml');
		$template->setMappedModule($this->name);
		
		$params = array(
					'message'		=> $this->getLanguageConstant('message_checkout_completed'),
					'button_text'	=> $this->getLanguageConstant('button_take_me_back'),
					'button_action'	=> url_Make('', 'home')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show message for canceled checkout operation
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CanceledMessage($tag_params, $children) {
		// show message
		$template = $this->loadTemplate($tag_params, 'checkout_message.xml');
		$template->setMappedModule($this->name);
		
		$params = array(
					'message'		=> $this->getLanguageConstant('message_checkout_canceled'),
					'button_text'	=> $this->getLanguageConstant('button_take_me_back'),
					'button_action'	=> url_Make('', 'home')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}
}
