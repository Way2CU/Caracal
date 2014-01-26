<?php

/**
 * Shop Module
 * 
 * Complete online shopping solution integration. This module provides
 * only the basic framework for online shopps. Payment and delivery methods
 * need to be added additionally.
 *
 * Author: Mladen Mijatov
 */

require_once('units/payment_method.php');
require_once('units/delivery_method.php');
require_once('units/shop_item_handler.php');
require_once('units/shop_category_handler.php');
require_once('units/shop_currencies_handler.php');
require_once('units/shop_item_sizes_handler.php');
require_once('units/shop_item_size_values_manager.php');
require_once('units/shop_transactions_manager.php');
require_once('units/shop_transactions_handler.php');
/* require_once('units/shop_stock_handler.php'); */
require_once('units/shop_warehouse_handler.php');
require_once('units/shop_transaction_items_manager.php');
require_once('units/shop_buyers_manager.php');
require_once('units/shop_delivery_address_manager.php');
require_once('units/shop_related_items_manager.php');
require_once('units/shop_manufacturer_handler.php');
require_once('units/shop_delivery_methods_handler.php');


class TransactionType {
	const SUBSCRIPTION = 0;
	const SHOPPING_CART = 1;
	const DONATION = 2;
}


class TransactionStatus {
	const PENDING = 0;
	const DENIED = 1;
	const COMPLETED = 2;
	const CANCELED = 3;
	const SHIPPING = 4;
	const SHIPPED = 5;
	const LOST = 6;
	const DELIVERED = 7;
}


class PackageType {
	const BOX_10 = 0;
	const BOX_20 = 1;
	const BOX = 2;
	const ENVELOPE = 3;
	const PAK = 4;
	const TUBE = 5;
	const USER_PACKAGING = 6;
}


class User {
	const EXISTING = 0;
	const CREATE = 1;
	const GUEST = 2;
}


class RecurringPayment {
	const DAY = 0;
	const WEEK = 1;
	const MONTH = 2;
	const YEAR = 3;
}


class shop extends Module {
	private static $_instance;
	private $payment_methods;
	private $delivery_methods;

	private $excluded_properties = array(
					'size_value', 'color_value', 'count'
				);

	private $search_params = array();

	const BUYER_SECRET = 'oz$9=7if~db/MP|BBN>)63T}6w{D6no[^79L]9>8(8wrv6:$/n63YsvCa<BR4379De1d035wvi]]iqA<P=3gHNv1H';

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// create methods storage
		$this->payment_methods = array();
		$this->delivery_methods = array();

		// create events
		$this->event_handler = new EventHandler();
		$this->event_handler->registerEvent('shopping-cart-changed');
		$this->event_handler->registerEvent('shipping-information-entered');
		$this->event_handler->registerEvent('payment-method-selected');
		$this->event_handler->registerEvent('billing-information-entered');
		$this->event_handler->registerEvent('payment-completed');

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

			if (class_exists('head_tag')) {
				$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/multiple_images.js'), 'type'=>'text/javascript'));
				$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/backend.js'), 'type'=>'text/javascript'));
			}

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

			$recurring_plans_menu = new backend_MenuItem(
								$this->getLanguageConstant('menu_recurring_plans'),
								url_GetFromFilePath($this->path.'images/recurring_plans.png'),
								'javascript: void(0);', 5
							);
			$shop_menu->addChild('shop_recurring_plans', $recurring_plans_menu);

			$shop_menu->addSeparator(5);

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

			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_manufacturers'),
								url_GetFromFilePath($this->path.'images/manufacturers.png'),
								window_Open( // on click open window
											'shop_manufacturers',
											400,
											$this->getLanguageConstant('title_manufacturers'),
											true, true,
											backend_UrlMake($this->name, 'manufacturers')
										),
								5  // level
							));

			// delivery methods menu
			$delivery_menu = new backend_MenuItem(
								$this->getLanguageConstant('menu_delivery_methods'),
								url_GetFromFilePath($this->path.'images/delivery.png'),
								'javascript: void(0);', 5
							);

			$shop_menu->addChild('shop_delivery_methods', $delivery_menu);

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
								$this->getLanguageConstant('menu_transactions'),
								url_GetFromFilePath($this->path.'images/transactions.png'),
								window_Open( // on click open window
											'shop_transactions',
											590,
											$this->getLanguageConstant('title_transactions'),
											true, true,
											backend_UrlMake($this->name, 'transactions')
										),
								5  // level
							));
			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_warehouses'),
								url_GetFromFilePath($this->path.'images/warehouse.png'),
								window_Open( // on click open window
											'shop_warehouses',
											490,
											$this->getLanguageConstant('title_warehouses'),
											true, true,
											backend_UrlMake($this->name, 'warehouses')
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

			$shop_menu->addSeparator(5);
			$shop_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_settings'),
								url_GetFromFilePath($this->path.'images/settings.png'),

								window_Open( // on click open window
											'shop_settings',
											400,
											$this->getLanguageConstant('title_settings'),
											true, true,
											backend_UrlMake($this->name, 'settings')
										),
								$level=5
							));	

			$backend->addMenu($this->name, $shop_menu);
		}

		// register search
		if (class_exists('search')) {
			$search = search::getInstance();
			$search->registerModule('shop', $this);
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
	 * Get search results when asked by search module
	 * 
	 * @param array $query
	 * @param integer $threshold
	 * @return array
	 */
	public function getSearchResults($query, $threshold) {
		global $language;

		$manager = ShopItemManager::getInstance();
		$result = array();
		$conditions = array(
						'visible'	=> 1,
						'deleted'	=> 0,
					);
		$query = mb_strtolower($query);
		$query_words = mb_split("\s", $query);

		// include pre-configured options
		if (isset($this->search_params['category'])) {
			$membership_manager = ShopItemMembershipManager::getInstance();
			$category = $this->search_params['category'];
			$item_ids = array();

			if (!is_numeric($category)) {
				$category_manager = ShopCategoryManager::getInstance();
				$raw_category = $category_manager->getSingleItem(
											array('id'),
											array('text_id' => $category)
										);

				if (is_object($raw_category))
					$category = $raw_category->id; else
					$category = -1;
			}

			// get list of item ids 
			$membership_list = $membership_manager->getItems(
											array('item'), 
											array('category' => $category)
										);

			if (count($membership_list) > 0) {
				foreach($membership_list as $membership)
					$item_ids[] = $membership->item;

				$conditions['id'] = $item_ids;
			}
		}

		// get all items and process them
		$items = $manager->getItems(
								array(
									'id',
									'name'
								),
								$conditions
							);

		// search through items
		if (count($items) > 0)
			foreach ($items as $item) {
				$title = mb_strtolower($item->name[$language]);
				$score = 0;

				foreach ($query_words as $query_word) 
					if (is_numeric(mb_strpos($title, $query_word)))
						$score += 10;

				// add item to result list
				if ($score >= $threshold)
					$result[] = array(
							'score'			=> $score,
							'title'			=> $title,
							'description'	=> limit_words($item->description[$language], 200),
							'id'			=> $item->id,
							'type'			=> 'item',
							'module'		=> $this->name
						);
			}

		return $result;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params, $children) {
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

				case 'show_checkout_form':
					$this->tag_CheckoutForm($params, $children);
					break;

				case 'show_payment_methods':
					$this->tag_PaymentMethodsList($params, $children);
					break;

				case 'configure_search':
					$this->configureSearch($params, $children);
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

				case 'show_checkout_items':
					$this->tag_CheckoutItems($params, $children);
					break;

				case 'handle_payment':
					$this->handlePayment();
					break;
					
				case 'set_item_as_cart':
					$this->setItemAsCart($params, $children);
					break;

				case 'set_cart_from_template':
					$this->setCartFromTemplate($params, $children);
					break;

				case 'set_recurring_plan':
					$this->setRecurringPlan($params, $children);
					break;

				case 'include_scripts':
					$this->includeScripts($params, $children);
					break;

				case 'json_get_item':
					$handler = ShopItemHandler::getInstance($this);
					$handler->json_GetItem();
					break;

				case 'json_get_currency':
					$this->json_GetCurrency();
					break;

				case 'json_get_account_info':
					$this->json_GetAccountInfo();
					break;

				case 'json_get_account_exists':
					$this->json_GetAccountExists();
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

				case 'json_update_transaction_status':
					$handler = ShopTransactionsHandler::getInstance($this);
					$handler->json_UpdateTransactionStatus();
					break;

				case 'json_get_shopping_cart_summary':
					$this->json_GetShoppingCartSummary();
					break;

				case 'json_save_remark':
					$this->json_SaveRemark();
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

				case 'transactions':
					$handler = ShopTransactionsHandler::getInstance($this);
					$handler->transferControl($params, $children);
					break;

				case 'manufacturers':
					$handler = ShopManufacturerHandler::getInstance($this);
					$handler->transferControl($params, $children);
					break;

				case 'special_offers':
					break;

				case 'warehouses':
					$handler = ShopWarehouseHandler::getInstance($this);
					$handler->transferControl($params, $children);
					break;

				case 'stocks':
					break;

				case 'settings':
					$this->showSettings();
					break;

				case 'settings_save':
					$this->saveSettings();
					break;

				default:
					break;
			}
		}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db;

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
				`manufacturer` INT(11) NOT NULL,
				`size_definition` INT(11) NULL,
				`colors` VARCHAR(255) NOT NULL DEFAULT '',
				`author` INT(11) NOT NULL,
				`views` INT(11) NOT NULL,
				`price` DECIMAL(8,2) NOT NULL,
				`tax` DECIMAL(3,2) NOT NULL,
				`weight` DECIMAL(8,2) NOT NULL,
				`votes_up` INT(11) NOT NULL,
				`votes_down` INT(11) NOT NULL,
				`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`priority` INT(4) NOT NULL DEFAULT '5',
				`visible` BOOLEAN NOT NULL DEFAULT '1',
				`deleted` BOOLEAN NOT NULL DEFAULT '0',
				PRIMARY KEY ( `id` ),
				KEY `visible` (`visible`),
				KEY `deleted` (`deleted`),
				KEY `uid` (`uid`),
				KEY `author` (`author`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop currencies table
		$sql = "
			CREATE TABLE `shop_item_membership` (
				`category` INT(11) NOT NULL,
				`item` INT(11) NOT NULL,
				KEY `category` (`category`),
				KEY `item` (`item`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create table for related shop items
		$sql = "
			CREATE TABLE IF NOT EXISTS `shop_related_items` (
				`item` int(11) NOT NULL,
				`related` int(11) NOT NULL,
				KEY `item` (`item`,`related`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		$db->query($sql);

		// create shop currencies tableshop_related_items
		$sql = "
			CREATE TABLE `shop_currencies` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`currency` VARCHAR(5) NOT NULL,
				PRIMARY KEY ( `id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
		
		// create shop item sizes table
		$sql = "
			CREATE TABLE `shop_item_sizes` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`name` VARCHAR(25) NOT NULL,
				PRIMARY KEY ( `id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
		
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
		$db->query($sql);

		// create shop categories table
		$sql = "
			CREATE TABLE `shop_categories` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`text_id` VARCHAR(32) NOT NULL,
				`parent` INT(11) NOT NULL DEFAULT '0',
				`image` INT(11),";

		foreach($list as $language)
			$sql .= "`title_{$language}` VARCHAR( 255 ) NOT NULL DEFAULT '',";

		foreach($list as $language)
			$sql .= "`description_{$language}` TEXT NOT NULL ,";

		$sql .="
				PRIMARY KEY ( `id` ),
				KEY `parent` (`parent`),
				KEY `text_id` (`text_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
		
		// create shop buyers table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_buyers` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `first_name` varchar(64) NOT NULL,
				  `last_name` varchar(64) NOT NULL,
				  `email` varchar(127) NOT NULL,
				  `password` varchar(200) NOT NULL,
				  `validated` BOOLEAN NOT NULL DEFAULT '0',
				  `guest` BOOLEAN NOT NULL DEFAULT '0',
				  `uid` varchar(50) NOT NULL,
				  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
		
		// create shop buyer addresses table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_delivery_address` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `buyer` int(11) NOT NULL,
				  `name` varchar(128) NOT NULL,
				  `street` varchar(200) NOT NULL,
				  `street2` varchar(200) NOT NULL,
				  `phone` varchar(200) NOT NULL,
				  `city` varchar(40) NOT NULL,
				  `zip` varchar(20) NOT NULL,
				  `state` varchar(40) NOT NULL,
				  `country` varchar(64) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `buyer` (`buyer`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
		
		// create shop transactions table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_transactions` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `buyer` int(11) NOT NULL,
				  `address` int(11) NOT NULL,
				  `uid` varchar(30) NOT NULL,
				  `type` smallint(6) NOT NULL,
				  `status` smallint(6) NOT NULL,
				  `currency` int(11) NOT NULL,
				  `handling` decimal(8,2) NOT NULL,
				  `shipping` decimal(8,2) NOT NULL,
				  `delivery_method` varchar(255) NOT NULL,
				  `remark` text NOT NULL,
				  `token` varchar(255) NOT NULL,
				  `total` decimal(8,2) NOT NULL,
				  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				  PRIMARY KEY (`id`),
				  KEY `buyer` (`buyer`),
				  KEY `address` (`address`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";		
		$db->query($sql);
		
		// create shop transaction items table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_transaction_items` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `transaction` int(11) NOT NULL,
				  `item` int(11) NOT NULL,
				  `price` DECIMAL(8,2) NOT NULL,
				  `tax` DECIMAL(8,2) NOT NULL,
				  `amount` int(11) NOT NULL,
				  `description` varchar(500) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `transaction` (`transaction`),
				  KEY `item` (`item`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
		
		// create shop stock table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_warehouse` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `name` varchar(60) NOT NULL,
				  `street` varchar(200) NOT NULL,
				  `street2` varchar(200) NOT NULL,
				  `city` varchar(40) NOT NULL,
				  `zip` varchar(20) NOT NULL,
				  `country` varchar(64) NOT NULL,
				  `state` varchar(40) NOT NULL,
				  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop stock table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_stock` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `item` int(11) NOT NULL,
				  `size` int(11) DEFAULT NULL,
				  `amount` int(11) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `item` (`item`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop manufacturers table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_manufacturers` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,";

		foreach($list as $language)
			$sql .= "`name_{$language}` VARCHAR(255) NOT NULL DEFAULT '',";

		$sql .= " `web_site` varchar(255) NOT NULL,
				  `logo` int(11) NOT NULL,
				  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

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
					'shop_warehouse',
					'shop_stock',
					'shop_related_items',
					'shop_manufacturers',
				);
		
		$db->drop_tables($tables);
	}

	/**
	 * Method used by payment providers to register with main module.
	 *
	 * @param string $name
	 * @param object $module
	 */
	public function registerPaymentMethod($name, &$module) {
		if (!array_key_exists($name, $this->payment_methods))
			$this->payment_methods[$name] = $module; else
			throw new Exception("Payment method '{$name}' is already registered with the system.");
	}

	/**
 	 * Method used by delivery providers to register with main module.
	 *
	 * @param string $name
	 * @param object $module
	 */
	public function registerDeliveryMethod($name, &$module) {
		if (!array_key_exists($name, $this->delivery_methods))
			$this->delivery_methods[$name] = $module; else
			throw new Exception("Delivery method '{$name}' is already registered with the system.");
	}

	/**
	 * Include buyer information and checkout form scripts.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function includeScripts($tag_params, $children) {
		if (class_exists('head_tag')) {
			$head_tag = head_tag::getInstance();
			$head_tag->addTag('script', array('src'=>_BASEURL.'/scripts/dialog.js', 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>_BASEURL.'/scripts/page_control.js', 'type'=>'text/javascript'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/checkout.js'), 'type'=>'text/javascript'));
		}
	}

	/**
	 * Show shop configuration form
	 */
	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
						'form_action'	=> backend_UrlMake($this->name, 'settings_save'),
						'cancel_action'	=> window_Close('shop_settings')
					);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save settings
	 */
	private function saveSettings() {
		// save new settings
		$email_article = fix_id($_REQUEST['email_article']);
		$shop_location = fix_chars($_REQUEST['shop_location']);
		$fixed_country = fix_chars($_REQUEST['fixed_country']);

		$this->saveSetting('email_article', $email_article);
		$this->saveSetting('shop_location', $shop_location);
		$this->saveSetting('fixed_country', $fixed_country);

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_settings_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('shop_settings')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Generate variation Id based on UID and properties.
	 *
	 * @param string $uid
	 * @param array $properties
	 * @return string
	 */
	private function generateVariationId($uid, $properties) {
		$data = $uid;

		ksort($properties);
		foreach($properties as $key => $value)
			$data .= ",{$key}:{$value}";

		$result = md5($data);
		return $result;
	}

	/**
	 * Set shopping cart to contain only one item.
	 *
	 * @param array $params
	 * @param array $children
	 */
	private function setItemAsCart($params, $children) {
		$uid = isset($params['uid']) ? fix_chars($params['uid']) : null;
		$count = isset($params['count']) ? fix_id($params['count']) : 1;

		// make sure we have UID specified
		if (!is_null($uid)) {
			$cart = array();
			$manager = ShopItemManager::getInstance();
			$properties = isset($params['properties']) ? fix_chars($params['properties']) : array();
			$variation_id = $this->generateVariationId($uid, $properties);

			// check if item exists in database to avoid poluting shopping cart
			$item = $manager->getSingleItem(array('id'), array('uid' => $uid));

			if (is_object($item) && $count > 0) {
				$cart[$uid] = array(
							'uid'			=> $uid,
							'quantity'		=> $count,
							'variations'	=> array()
						);
				$cart[$uid]['variations'][$variation_id] = array('count' => $count);
			}

			$_SESSION['shopping_cart'] = $cart;
			$this->event_handler->trigger('shopping-cart-changed');
		}
	}

	/**
	 * Set content of a shopping cart from template
	 *
	 * @param 
	 */
	private function setCartFromTemplate($params, $children) {
		if (count($children) > 0) {
			$cart = array();
			$manager = ShopItemManager::getInstance();

			foreach ($children as $data) {
				$uid = array_key_exists('uid', $data->tagAttrs) ? fix_chars($data->tagAttrs['uid']) : null;
				$amount = array_key_exists('count', $data->tagAttrs) ? fix_id($data->tagAttrs['count']) : 0;
				$properties = isset($data->tagAttrs['properties']) ? fix_chars($data->tagAttrs['properties']) : array();
				$variation_id = $this->generateVariationId($uid, $properties);
				$item = null;

				if (!is_null($uid))
					$item = $manager->getSingleItem(array('id'), array('uid' => $uid));

				// make sure item actually exists in database to avoid poluting
				if (is_object($item) && $amount > 0) {
					$cart[$uid] = array(
								'uid'			=> $uid,
								'quantity'		=> $amount,
								'variations'	=> array()
							);
					$cart[$uid]['variations'][$variation_id] = array('count' => $amount);
				}
			}

			$_SESSION['shopping_cart'] = $cart;
			$this->event_handler->trigger('shopping-cart-changed');
		}
	}

	/**
	 * Set recurring plan to be activated.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	private function setRecurringPlan($tag_params, $children) {
		$recurring_plan = fix_chars($tag_params['text_id']);
		$_SESSION['recurring_plan'] = $recurring_plan;
	}

	/**
	 * Set transaction status.
	 *
	 * @param string $transaction_id
	 * @param string $status
	 * @return boolean
	 */
	public function setTransactionStatus($transaction_id, $status) {
		$result = false;
		$manager = ShopTransactionsManager::getInstance();

		// try to get transaction with specified id
		$transaction = $manager->getSingleItem(array('id'), array('uid' => $transaction_id));

		// set status of transaction
		if (is_object($transaction)) {
			$manager->updateData(array('status' => $status), array('id' => $transaction->id));
			$result = true;
		}

		return $result;
	}

	/**
	 * Set token from payment method for specified transaction.
	 *
	 * @param string $transaction_id
	 * @param string $token
	 */
	public function setTransactionToken($transaction_id, $token) {
		$result = false;
		$manager = ShopTransactionsManager::getInstance();

		// try to get transaction with specified id
		$transaction = $manager->getSingleItem(array('id'), array('uid' => $transaction_id));

		// set token for transaction
		if (is_object($transaction)) {
			$manager->updateData(array('token' => $token), array('id' => $transaction->id));
			$result = true;
		}

		return $result;
	}

	/**
	 * Pre-configure search parameters
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	private function configureSearch($tag_params, $children) {
		$this->search_params = $tag_params;
	}

	/**
	 * Show checkout form
	 */
	public function showCheckout() {
		if (count($this->payment_methods) == 0)
			return;

		$template = new TemplateHandler('checkout.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array();

		// register tag handler
		$template->registerTagHandler('_checkout_form', $this, 'tag_CheckoutForm');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show message for completed checkout and empty shopping cart
	 */
	private function showCheckoutCompleted() {
		$template = new TemplateHandler('checkout_completed.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('_completed_message', $this, 'tag_CompletedMessage');

		$params = array();

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
		$template->registerTagHandler('_canceled_message', $this, 'tag_CanceledMessage');

		$params = array();

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
			/* $buyer_info = $method->get_buyer_info(); */
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

				$transactions_manager->insertData($data);
				$transaction_id = $transactions_manager->getInsertedID();

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
		print json_encode($this->getDefaultCurrency());
	}

	/**
	 * Return user information if email and password are correct.
	 */
	private function json_GetAccountInfo() {
		$email = isset($_REQUEST['email']) ? fix_chars($_REQUEST['email']) : null;
		$password = isset($_REQUEST['password']) ? fix_chars($_REQUEST['password']) : null;
		$valid_user = false;

		// get managers
		$retry_manager = LoginRetryManager::getInstance();
		$buyer_manager = ShopBuyersManager::getInstance();
		$delivery_address_manager = ShopDeliveryAddressManager::getInstance();
		$transaction_manager = ShopTransactionsManager::getInstance();

		if ($retry_manager->getRetryCount() > 3) {
			header('HTTP/1.1 401 '.$this->getLanguageConstant('message_error_exceeded_attempts'));
			return;
		}

		// check user credentials
		$buyer = $buyer_manager->getSingleItem(
									$buyer_manager->getFieldNames(),
									array(
										'email'		=> $email,
										'password'	=> hash_hmac(
															'sha256', 
															$password,
															shop::BUYER_SECRET
														),
										'guest'		=> 0,
										// 'validated'	=> 1
									)
								);

		if (is_object($buyer)) {
			$result = array(
					'information'			=> array(),
					'delivery_addresses'	=> array(),
					'last_payment_method'	=> '',
					'last_delivery_method'	=> ''
				);

			// populate user information
			$result['information'] = array(
									'first_name'	=> $buyer->first_name,
									'last_name'		=> $buyer->last_name,
									'email'			=> $buyer->email,
									'uid'			=> $buyer->uid
								);

			// populate delivery addresses
			$address_list = $delivery_address_manager->getItems(
									$delivery_address_manager->getFieldNames(),
									array('buyer' => $buyer->id)
								);

			if (count($address_list) > 0)
				foreach ($address_list as $address) {
					$result['delivery_addresses'][] = array(
									'id'		=> $address->id,
									'name'		=> $address->name,
									'street'	=> $address->street,
									'street2'	=> $address->street2,
									'phone'		=> $address->phone,
									'city'		=> $address->city,
									'zip'		=> $address->zip,
									'state'		=> $address->state,
									'country'	=> $address->country
								);
				}

			// get last used payment and delivery method
			$transaction = $transaction_manager->getSingleItem(
									$transaction_manager->getFieldNames(),
									array('buyer' => $buyer->id),
									array('timestamp'), false
								);

			if (is_object($transaction)) {
				$result['last_payment_method'] = $transaction->payment_method;
				$result['last_delivery_method'] = $transaction->delivery_method;
			}
			$retry_manager->clearAddress();

			print json_encode($result);

		} else {
			// user didn't supply the right username/password
			header('HTTP/1.1 401 '.$this->getLanguageConstant('message_error_invalid_credentials'));

			// record bad attempt
			$retry_manager->increaseCount();
		}
	}

	/**
	 * Check if account with specified email exists in database already.
	 */
	private function json_GetAccountExists() {
		$email = isset($_REQUEST['email']) ? fix_chars($_REQUEST['email']) : null;
		$manager = ShopBuyersManager::getInstance();
		$result = array(
				'account_exists'	=> false,
				'message'			=> ''
			);

		if (!is_null($email)) {
			$account = $manager->getSingleItem(array('id'), array('email' => $email));
			$result['account_exists'] = is_object($account);
			$result['message'] = $this->getLanguageConstant('message_error_account_exists');
		}

		print json_encode($result);
	}

	/**
	 * Show shopping card in form of JSON object
	 */
	private function json_ShowCart() {
		$manager = ShopItemManager::getInstance();
		$values_manager = ShopItemSizeValuesManager::getInstance();
		$gallery = class_exists('gallery') ? gallery::getInstance() : null;
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();

		$result = array();

		// get shopping cart from session
		$result['cart'] = array();
		$result['size_values'] = array();
		$result['count'] = count($result['cart']);
		$result['currency'] = $this->getDefaultCurrency();

		if (isset($_SESSION['transaction'])) {
			$result['shipping'] = $_SESSION['transaction']['shipping'];
			$result['handling'] = $_SESSION['transaction']['handling'];

		} else {
			$result['shipping'] = 0;
			$result['handling'] = 0;
		}

		// colect ids from session
		$ids = array_keys($cart);

		// get items from database and prepare result
		$items = $manager->getItems($manager->getFieldNames(), array('uid' => $ids));
		$values = $values_manager->getItems($values_manager->getFieldNames(), array());

		if (count($items) > 0) 
			foreach ($items as $item) {
				// get item image url
				$thumbnail_url = !is_null($gallery) ? $gallery->getGroupThumbnailURL($item->gallery) : '';

				$uid = $item->uid;

				if (array_key_exists($uid, $cart) && count($cart[$uid]['variations']) > 0)
					foreach ($cart[$uid]['variations'] as $variation_id => $properties) {
						$new_properties = $properties;
						unset($new_properties['count']);

						$result['cart'][] = array(
									'name'			=> $item->name,
									'weight'		=> $item->weight,
									'price'			=> $item->price,
									'tax'			=> $item->tax,
									'image'			=> $thumbnail_url,
									'uid'			=> $item->uid,
									'variation_id'	=> $variation_id,
									'count'			=> $properties['count'],
									'properties'	=> $new_properties
								);
					}
			}
		
		if (count($values) > 0) 
			foreach ($values as $value) {
				$result['size_values'][$value->id] = array(
											'definition'	=> $value->definition,
											'value'			=> $value->value
										);
			}
			
		print json_encode($result);
	}

	/**
	 * Clear shopping cart and return result in form of JSON object
	 */
	private function json_ClearCart() {
		$_SESSION['shopping_cart'] = array();
		$this->event_handler->trigger('shopping-cart-changed');

		print json_encode(true);
	}

	/**
	 * Add item to shopping cart using JSON request
	 */
	private function json_AddItemToCart() {
		$uid = fix_chars($_REQUEST['uid']);
		$properties = isset($_REQUEST['properties']) ? fix_chars($_REQUEST['properties']) : array();
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$variation_id = $this->generateVariationId($uid, $properties);

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
							'uid'			=> $uid,
							'quantity'		=> 1,
							'variations'	=> array()
						);
			}

			if (!array_key_exists($variation_id, $cart[$uid]['variations'])) {
				$cart[$uid]['variations'][$variation_id] = $properties;
				$cart[$uid]['variations'][$variation_id]['count'] = 0;
			}

			// increase count in case it already exists
			$cart[$uid]['variations'][$variation_id]['count'] += 1;

			// get item image url
			$thumbnail_url = null;
			if (class_exists('gallery')) {
				$gallery = gallery::getInstance();
				$thumbnail_url = $gallery->getGroupThumbnailURL($item->gallery); 
			}

			// prepare result
			$result = array(
					'name'			=> $item->name,
					'weight'		=> $item->weight,
					'price'			=> $item->price,
					'tax'			=> $item->tax,
					'image'			=> $thumbnail_url,
					'count'			=> $cart[$uid]['variations'][$variation_id]['count'],
					'variation_id'	=> $variation_id
				);

			// update shopping cart
			$_SESSION['shopping_cart'] = $cart;
		}

		$this->event_handler->trigger('shopping-cart-changed');
		print json_encode($result);
	}

	/**
	 * Remove item from shopping cart using JSON request
	 */
	private function json_RemoveItemFromCart() {
		$uid = fix_chars($_REQUEST['uid']);
		$variation_id = fix_chars($_REQUEST['variation_id']);
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$result = false;

		if (array_key_exists($uid, $cart) && array_key_exists($variation_id, $cart[$uid]['variations'])) {
			$count = $cart[$uid]['variations'][$variation_id]['count'];
			unset($cart[$uid]['variations'][$variation_id]);

			$cart[$uid]['quantity'] -= $count;

			if (count($cart[$uid]['variations']) == 0)
				unset($cart[$uid]);

			$_SESSION['shopping_cart'] = $cart;
			$result = true;
		}

		$this->event_handler->trigger('shopping-cart-changed');
		print json_encode($result);
	}

	private function json_ChangeItemQuantity() {
		$uid = fix_chars($_REQUEST['uid']);
		$variation_id = fix_chars($_REQUEST['variation_id']);
		$count = fix_id($_REQUEST['count']);
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$result = false;

		if (array_key_exists($uid, $cart) && array_key_exists($variation_id, $cart[$uid]['variations'])) {
			$old_count = $cart[$uid]['variations'][$variation_id]['count'];
			$cart[$uid]['variations'][$variation_id]['count'] = $count;

			$cart[$uid]['quantity'] += -$old_count + $count;

			$_SESSION['shopping_cart'] = $cart;
			$result = true;
		}

		$this->event_handler->trigger('shopping-cart-changed');
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

		// print data
		print json_encode($result);
	}

	/**
	 * Get shopping cart summary and update delivery method if needed
	 */
	private function json_GetShoppingCartSummary() {
		$result = array();
		$uid = $_SESSION['transaction']['uid'];
		$update_delivery_method = isset($_REQUEST['delivery_method']);

		// get managers
		$transactions_manager = ShopTransactionsManager::getInstance();
		$address_manager = ShopDeliveryAddressManager::getInstance();

		// update session delivery method
		if ($update_delivery_method) {
			$method = fix_chars($_REQUEST['delivery_method']);

			if (array_key_exists($method, $this->delivery_methods))
				$_SESSION['delivery_method'] = $method;
		}

		// get recipient's address
		$recipient = array();
		$transaction = $transactions_manager->getSingleItem(array('address'), array('uid' => $uid));

		if (is_object($transaction)) {
			$address = $address_manager->getSingleItem(
							$address_manager->getFieldNames(),
							array('id' => $transaction->address)
						);

			if (is_object($address))
				$recipient = array(
							'street'	=> array($address->street, $address->street2),
							'city'		=> $address->city,
							'zip_code'	=> $address->zip,
							'state'		=> $address->state,
							'country'	=> $address->country
						);
		}

		$result = $this->getCartSummary($recipient, $uid);
		unset($result['items_for_checkout']);

		// add currency to result
		$result['currency'] = $this->getDefaultCurrency();

		// add language constants
		$result['label_no_estimate'] = $this->getLanguageConstant('label_no_estimate');
		$result['label_estimated_time'] = $this->getLanguageConstant('label_estimated_time');

		// if delivery method was changed update transaction details in database
		if ($update_delivery_method) {
			$manager = ShopTransactionsManager::getInstance();

			$data = array(
					'handling'			=> $result['handling'],
					'shipping'			=> $result['shipping'],
					'delivery_method'	=> $result['delivery_method'],
					'total'				=> $result['total'],
				);

			$manager->updateData($data, array('uid' => $uid));
		}	

		print json_encode($result);
	}

	/**
	 * Save transaction remark before submitting form
	 */
	private function json_SaveRemark() {
		$result = false;

		if (isset($_SESSION['transaction'])) {
			$manager = ShopTransactionsManager::getInstance();

			// get data
			$uid = $_SESSION['transaction']['uid'];
			$remark = fix_chars($_REQUEST['remark']);

			// store remark
			$manager->updateData(array('remark' => $remark), array('uid' => $uid));
			$result = true;
		}

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
	 * Get shopping cart summary.
	 *
	 * @param string $transaction_id
	 * @param array $recipient
	 * @return array
	 */
	private function getCartSummary($recipient, $transaction_id) {
		$result = array();
		$default_language = MainLanguageHandler::getInstance()->getDefaultLanguage();

		// colect ids from session
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$ids = array_keys($cart);

		if (count($cart) == 0)
			return $result;

		// get managers
		$manager = ShopItemManager::getInstance();

		// get items from database and prepare result
		$items = $manager->getItems($manager->getFieldNames(), array('uid' => $ids));

		// prepare params
		$shipping = 0;
		$handling = 0;
		$total_money = 0;
		$total_weight = 0;
		$delivery_method = null;
		$items_by_uid = array();
		$items_for_checkout = array();
		$delivery_items = array();
		$delivery_prices = array();
		$map_id_to_uid = array();

		// parse items from database
		foreach ($items as $item) {
			$db_item = array(
					'id'		=> $item->id,
					'name'		=> $item->name,
					'price'		=> $item->price,
					'tax'		=> $item->tax,
					'weight'	=> $item->weight
				);
			$items_by_uid[$item->uid] = $db_item;
			$map_id_to_uid[$item->id] = $item->uid;
		}

		// prepare items for checkout
		foreach ($cart as $uid => $item) {
			// include all item variations in preparation
			if (count($item['variations']) > 0)
				foreach($item['variations'] as $variation_id => $data) {
					// add items to checkout list
					$properties = $data;

					foreach ($this->excluded_properties as $key)
						if (isset($properties[$key]))
							unset($properties[$key]);

					$new_item = $items_by_uid[$uid];
					$new_item['count'] = $data['count'];
					$new_item['description'] = implode(', ', array_values($properties));

					// add item to list for delivery estimation
					$delivery_items []= array(
								'properties'	=> array(),
								'package'		=> 1,
								'weight'		=> 0.5,
								'package_type'	=> 0,
								'width'			=> 2,
								'height'		=> 5,
								'length'		=> 15,
								'units'			=> 1,
								'count'			=> $data['count']
							);

					// add item to the list
					$items_for_checkout[] = $new_item;

					// include item data in summary
					$tax = $new_item['tax'];
					$price = $new_item['price'];
					$weight = $new_item['weight'];
					
					$total_money += ($price * (1 + ($tax / 100))) * $data['count']; 
					$total_weight += $weight * $data['count'];
				}
		}


		// only get delivery method prices if request was made by client-side script
		if (_AJAX_REQUEST) {
			// get prefered method
			if (isset($_SESSION['delivery_method']) && array_key_exists($_SESSION['delivery_method'], $this->delivery_methods)) 
				$delivery_method = $this->delivery_methods[$_SESSION['delivery_method']];

			// if there is a delivery method selected, get price estimation for items
			// TODO: Instead of picking up the first warehouse we need to choose proper one based on item property.
			if (!is_null($delivery_method)) {
				$currency_manager = ShopCurrenciesManager::getInstance();
				$warehouse_manager = ShopWarehouseManager::getInstance();
				$warehouse = $warehouse_manager->getSingleItem($warehouse_manager->getFieldNames(), array());

				// get currency associated with transaction
				$currency = $currency_manager->getSingleItem(
													$currency_manager->getFieldNames(),
													array('id' => $currency_id)
												);
				if (is_object($currency))
					$preferred_currency = $currency->currency; else
					$preferred_currency = 'EUR';

				if (is_object($warehouse)) {
					$shipper = array(
							'street'	=> array($warehouse->street, $warehouse->street2),
							'city'		=> $warehouse->city,
							'zip_code'	=> $warehouse->zip,
							'state'		=> $warehouse->state,
							'country'	=> $warehouse->country
						);

					// get types and prices from delivery method provider
					$delivery_prices = $delivery_method->getDeliveryTypes(
							$delivery_items,
							$shipper,
							$recipient,
							$transaction_id,
							$preferred_currency
						);

					// convert prices and format timestamps
					$language_handler = MainLanguageHandler::getInstance();
					$date_format = $language_handler->getText('format_date');

					if (count($delivery_prices) > 0)
						for ($i = 0; $i < count($delivery_prices); $i++) {
							$delivery = $delivery_prices[$i];

							// format starting date
							if (!is_null($delivery[3]))
								$delivery[3] = date($date_format, $delivery[3]);

							// format ending date
							if (!is_null($delivery[4]))
								$delivery[4] = date($date_format, $delivery[4]);

							// store delivery back to the original array
							$delivery_prices[$i] = $delivery;
						}
				}
			}
		}

		$result = array(
				'items_for_checkout'	=> $items_for_checkout,
				'shipping'				=> $shipping,
				'handling'				=> $handling,
				'weight'				=> $total_weight,
				'total'					=> $total_money,
				'delivery_method'		=> is_null($delivery_method) ? '' : $delivery_method->getName(),
				'delivery_prices'		=> $delivery_prices
			);

		return $result;
	}

	/**
	 * Handle drawing checkout form
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CheckoutForm($tag_params, $children) {
		$account_information = array();
		$shipping_information = array();
		$payment_method = null;
		$billing_information = array();
		$existing_user = isset($_POST['existing_user']) ? fix_id($_POST['existing_user']) : null;

		// decide whether to include shipping and account information
		if (isset($tag_params['include_shipping']))
			$include_shipping = fix_id($tag_params['include_shipping']); else
			$include_shipping = true;

		$bad_fields = array();
		$info_available = false;

		// grab user information
		if (isset($_POST['set_info'])) {
			// get payment method
			if (isset($tag_params['payment_method']) && array_key_exists($tag_params['payment_method'], $this->payment_methods)) {
				$method_name = fix_chars($tag_params['payment_method']);
				$payment_method = $this->payment_methods[$method_name];

			} else if (isset($_POST['payment_method']) && array_key_exists($_POST['payment_method'], $this->payment_methods)) {
				$method_name = fix_chars($_POST['payment_method']);
				$payment_method = $this->payment_methods[$method_name];
			}

			// try to get fallback payment method
			if (is_null($payment_method) && count($this->payment_methods) > 0)
				$payment_method = array_shift($this->payment_methods);

			// get delivery information
			if ($include_shipping) {
				$fields = array('name', 'email', 'phone', 'street', 'street2', 'city', 'zip', 'country', 'state');
				$required = array('name', 'email', 'street', 'city', 'zip', 'country');

				foreach($fields as $field)
					if (isset($_POST[$field]) && !empty($_POST[$field]))
						$shipping_information[$field] = fix_chars($_POST[$field]); else
						if (in_array($field, $required))
							$bad_fields[] = $field;
			}

			// get billing information
			if (!is_null($payment_method) && !$payment_method->provides_information()) {
				$fields = array(
					'billing_full_name', 'billing_credit_card', 'billing_expire_month',
					'billing_expire_year', 'billing_cvv' 
				);
				$required = $fields;

				foreach($fields as $field)
					if (isset($_POST[$field]) && !empty($_POST[$field]))
						$billing_information[$field] = fix_chars($_POST[$field]); else
						if (in_array($field, $required))
							$bad_fields[] = $field;
			}

			// set proper account data based on users choice
			switch ($existing_user) {
				case User::EXISTING:
					$manager = ShopBuyersManager::getInstance();
					$retry_manager = LoginRetryManager::getInstance();

					$email = fix_chars($_POST['sign_in_email']);
					$password = hash_hmac(
									'sha256',
									$_POST['sign_in_password'],
									shop::BUYER_SECRET
								);

					$account = $manager->getSingleItem(
											$manager->getFieldNames(),
											array(
												'email'		=> $email,
												'password'	=> $password,
												'guest'		=> 0,
												// 'validated'	=> 1
											));

					if (is_object($account)) {
						$account_information = array(
										'first_name'	=> $account->first_name,
										'last_name'		=> $account->last_name,
										'email'			=> $email
									);
					} else {
						// invalid user name
						$bad_fields[] = 'sign_in_email';
						$bad_fields[] = 'sign_in_password';
					}
					break;

				case User::CREATE:
					$account_information = array(
									'first_name'	=> fix_chars($_POST['first_name']),
									'last_name'		=> fix_chars($_POST['last_name']),
									'email'			=> fix_chars($_POST['new_email']),
									'validated'		=> 0,
									'guest'			=> 0
								);

					if ($_POST['new_password'] != $_POST['new_password_confirm'] || empty($_POST['new_password'])) {
						// password fields missmatch, mark them as bad
						$bad_fields[] = 'new_password';
						$bad_fields[] = 'new_password_confirm';

					} else {
						// password fields match, salt and hash password
						$account_information['password'] = hash_hmac(
																'sha256',
																$_POST['new_password'],
																shop::BUYER_SECRET
															);
					}
					break;
 
				case User::GUEST:
				default:
					$name = explode(' ', fix_chars($_POST['name']), 1);

					$account_information = array(
									'first_name'	=> $name[0],
									'last_name'		=> $name[1],
									'email'			=> fix_chars($_POST['email']),
									'password'		=> '',
									'validated'		=> 0,
									'guest'			=> 1
								);

					break;
			}
		}

		$info_available = count($bad_fields) == 0 && !is_null($payment_method);

		if ($info_available) {
			$buyers_manager = ShopBuyersManager::getInstance();
			$address_manager = ShopDeliveryAddressManager::getInstance();
			$transactions_manager = ShopTransactionsManager::getInstance();
			$transaction_items_manager = ShopTransactionItemsManager::getInstance();
			$currency_manager = ShopCurrenciesManager::getInstance();

			// get fields for payment method
			$return_url = urlencode(url_Make('checkout_completed', 'shop', array('method', $payment_method->get_name())));
			$cancel_url = urlencode(url_Make('checkout_canceled', 'shop', array('method', $payment_method->get_name())));
			$transaction_data = array();

			// get currency info
			$currency = $this->settings['default_currency'];
			$currency_item = $currency_manager->getSingleItem(array('id'), array('currency' => $currency));

			if (is_object($currency_item))
				$transaction_data['currency'] = $currency_item->id;

			if ($existing_user == User::EXISTING) {
				// associate existing buyer with transaction
				$buyer = $buyers_manager->getSingleItem(
									array('id'), 
									array('email' => $account_information['email'])
								);
				$transaction_data['buyer'] = $buyer->id;

			} else {
				// create new buyer and associate with transaction
				$buyers_manager->insertData($account_information);
				$transaction_data['buyer'] = $buyers_manager->getInsertedID();
			}

			// try to associate address with transaction
			$address = $address_manager->getSingleItem(
								array('id'),
								array(
									'buyer'		=> $transaction_data['buyer'],
									'name'		=> $shipping_information['name'],
									'street'	=> $shipping_information['street'],
									'street2'	=> isset($shipping_information['street2']) ? $shipping_information['street2'] : '',
									'city'		=> $shipping_information['city'],
									'zip'		=> $shipping_information['zip'],
									'state'		=> $shipping_information['state'],
									'country'	=> $shipping_information['country'],
								));

			if (is_object($address)) {
				// existing address
				$address_id = $address->id;

			} else {
				// create new address
				$address_manager->insertData(array(
									'buyer'		=> $transaction_data['buyer'],
									'name'		=> $shipping_information['name'],
									'street'	=> $shipping_information['street'],
									'street2'	=> isset($shipping_information['street2']) ? $shipping_information['street2'] : '',
									'phone'		=> $shipping_information['phone'],
									'city'		=> $shipping_information['city'],
									'zip'		=> $shipping_information['zip'],
									'state'		=> $shipping_information['state'],
									'country'	=> $shipping_information['country'],
								));
				$address_id = $address_manager->getInsertedID();
			}

			// generate recipient array for delivery method
			$recipient = array(
	 					'street'	=> array($shipping_information['street'], ),
	 					'city'		=> $shipping_information['city'],
	 					'zip_code'	=> $shipping_information['zip'],
	 					'state'		=> $shipping_information['state'],
	 					'country'	=> $shipping_information['country']
					);

			if (isset($shipping_information['street2']))
				$recipient['street'][] = $shipping_information['street2'];

			// check if we have existing transaction in our database
			if (!isset($_SESSION['transaction'])) {
				// get shopping cart summary
				$uid = uniqid('', true);
				$summary = $this->getCartSummary($recipient, $uid);

				// generate new transaction uid
				$transaction_data['uid'] = $uid;
				$transaction_data['type'] = TransactionType::SHOPPING_CART;
				$transaction_data['status'] = TransactionStatus::PENDING;
				$transaction_data['handling'] = $summary['handling'];
				$transaction_data['shipping'] = $summary['shipping'];
				$transaction_data['payment_method'] = $payment_method->get_name();
				$transaction_data['delivery_method'] = '';
				$transaction_data['remark'] = '';
				$transaction_data['address'] = $address_id;
				$transaction_data['total'] = $summary['total'];

				// create new transaction
				$transactions_manager->insertData($transaction_data);
				$transaction_data['id'] = $transactions_manager->getInsertedID();

				// store transaction data to session
				$_SESSION['transaction'] = $transaction_data;

			} else {
				$uid = $_SESSION['transaction']['uid'];
				$summary = $this->getCartSummary($recipient, $uid);

				// there's already an existing transaction
				$transaction_data = $_SESSION['transaction'];
				$transaction_data['handling'] = $summary['handling'];
				$transaction_data['shipping'] = $summary['shipping'];
				$transaction_data['total'] = $summary['total'];

				// update existing transaction
				$transactions_manager->updateData(
									array(
										'handling'	=> $summary['handling'],
										'shipping'	=> $summary['shipping'],
										'total'		=> $summary['total'],
										'address'	=> $address_id
									),
									array('uid' => $uid)
								);

				// update session storage with newest data
				$_SESSION['transaction'] = $transaction_data;
			}

			// remove items associated with transaction
			$transaction_items_manager->deleteData(array('transaction' => $transaction_data['id']));

			// store items
			if (count($summary['items_for_checkout']) > 0) 
				foreach($summary['items_for_checkout'] as $uid => $item) {
					$transaction_items_manager->insertData(array(
												'transaction'	=> $transaction_data['id'],
												'item'			=> $item['id'],
												'price'			=> $item['price'],
												'tax'			=> $item['tax'],
												'amount'		=> $item['count'],
												'description'	=> $item['description']
											));
				}

			// if affiliate system is active, update referral
			if (isset($_SESSION['referral_id']) && class_exists('affiliates')) {
				$referral_id = $_SESSION['referral_id'];
				$referrals_manager = AffiliateReferralsManager::getInstance();

				$referrals_manager->updateData(
								array('transaction' => $transaction_data['id']),
								array('id' => $referral_id)
							);
			}

			// create new payment
			$checkout_fields = $payment_method->new_payment(
										$transaction_data,
										$billing_information,
										$summary['items_for_checkout'],
										$return_url,
										$cancel_url
									);

			// load template
			$template = $this->loadTemplate($tag_params, 'checkout_form.xml');
			$template->registerTagHandler('cms:checkout_items', $this, 'tag_CheckoutItems');
			$template->registerTagHandler('cms:delivery_methods', $this, 'tag_DeliveryMethodsList');

			// parse template
			$params = array(
						'checkout_url'		=> $payment_method->get_url(),
						'checkout_fields'	=> $checkout_fields,
						'checkout_name'		=> $payment_method->get_title(),
						'sub-total'			=> number_format($summary['total'], 2),
						'shipping'			=> number_format($summary['shipping'], 2),
						'handling'			=> number_format($summary['handling'], 2),
						'total_weight'		=> number_format($summary['weight'], 2),
						'total'				=> number_format($summary['total'] + $summary['shipping'] + $summary['handling'], 2),
						'currency'			=> $this->getDefaultCurrency()
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();

		} else {
			// no information available, show form
			$template = new TemplateHandler('buyer_information.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			// get fixed country if set
			$fixed_country = '';
			if (isset($this->settings['fixed_country']))
				$fixed_country = $this->settings['fixed_country'];

			$params = array(
						'include_shipping'	=> $include_shipping,
						'fixed_country'		=> $fixed_country,
						'bad_fields'		=> $bad_fields
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
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
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$ids = array_keys($cart);

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), array('uid' => $ids));
		$items_by_uid = array();
		$items_for_checkout = array();

		// parse items from database
		foreach ($items as $item) {
			$db_item = array(
					'name'		=> $item->name,
					'price'		=> $item->price,
					'tax'		=> $item->tax,
					'weight'	=> $item->weight
				);
			$items_by_uid[$item->uid] = $db_item;
		}

		// prepare items for checkout
		foreach ($cart as $uid => $item) {
			if (count($item['variations']) > 0)
				foreach($item['variations'] as $variation_id => $data) {
					// add items to checkout list
					$properties = $data;

					foreach ($this->excluded_properties as $key)
						if (isset($properties[$key]))
							unset($properties[$key]);

					$new_item = $items_by_uid[$uid];
					$new_item['count'] = $data['count'];
					$new_item['description'] = implode(', ', array_values($properties));
					$new_item['total'] = number_format(($new_item['price'] * (1 + ($new_item['tax'] / 100))) * $new_item['count'], 2);
					$new_item['tax'] = number_format($new_item['price'], 2);
					$new_item['price'] = number_format($new_item['tax'], 2);
					$new_item['weight'] = number_format($new_item['weight'], 2);

					// add item to the list
					$items_for_checkout[] = $new_item;
				}
		}

		// load template
		$template = $this->loadTemplate($tag_params, 'checkout_form_item.xml');

		// parse template
		if (count($items_for_checkout) > 0)
			foreach ($items_for_checkout as $params) {
				$template->setLocalParams($params);
				$template->restoreXML();
				$template->parse();
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
		
		$params = array(
					'message'		=> $this->getLanguageConstant('message_checkout_canceled'),
					'button_text'	=> $this->getLanguageConstant('button_take_me_back'),
					'button_action'	=> url_Make('', 'home')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show list of payment methods.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_PaymentMethodsList($tag_params, $children) {
		$template = $this->loadTemplate($tag_params, 'payment_method.xml');
		$only_recurring = isset($_SESSION['recurring_plan']) && !empty($_SESSION['recurring_plan']);

		if (count($this->payment_methods) > 0)
			foreach ($this->payment_methods as $name => $module) 
				if (($only_recurring && $module->supports_recurring()) || !$only_recurring) {
					$params = array(
								'name'					=> $name,
								'title'					=> $module->get_title(),
								'icon'					=> $module->get_icon_url(),
								'image'					=> $module->get_image_url(),
								'provides_information'	=> $module->provides_information()
							);

					$template->restoreXML();
					$template->setLocalParams($params);
					$template->parse();
				}
	}

	/**
	 * Show list of delivery methods.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_DeliveryMethodsList($tag_params, $children) {
		$template = $this->loadTemplate($tag_params, 'delivery_method.xml');

		if (count($this->delivery_methods) > 0)
			foreach($this->delivery_methods as $name => $module) {
				$params = array(
							'selected'				=> isset($_SESSION['delivery_method']) && $_SESSION['delivery_method'] == $name,
							'name'					=> $name,
							'title'					=> $module->getTitle(),
							'icon'					=> $module->getIcon(),
							'image'					=> $module->getImage(),
							'small_image'			=> $module->getSmallImage(),
							'is_international'		=> $module->isInternational()
						);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Handle drawing recurring payment cycle units.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CycleUnit($tag_params, $children) {
		$units = array(
			RecurringPayment::DAY 	=> $this->getLanguageConstant('cycle_day'),
			RecurringPayment::WEEK	=> $this->getLanguageConstant('cycle_week'),
			RecurringPayment::MONTH	=> $this->getLanguageConstant('cycle_month'),
			RecurringPayment::YEAR	=> $this->getLanguageConstant('cycle_year')
		);

		$selected = isset($tag_params['selected']) ? fix_id($tag_params['selected']) : null;
		$template = $this->loadTemplate($tag_params, 'cycle_unit_option.xml');

		foreach($units as $id => $text) {
			$params = array(
					'id'		=> $id,
					'text'		=> $text,
					'selected'	=> $id == $selected
				);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Function that returns boolean denoting if shop is in testing phase.
	 *
	 * @return boolean
	 */
	public function isDebug() {
		return true;
	}
}
