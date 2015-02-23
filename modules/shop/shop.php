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

use Core\Events;
use Core\Module;

require_once('units/payment_method.php');
require_once('units/delivery_method.php');
require_once('units/shop_item_handler.php');
require_once('units/shop_category_handler.php');
require_once('units/shop_currencies_handler.php');
require_once('units/shop_item_sizes_handler.php');
require_once('units/shop_item_size_values_manager.php');
require_once('units/shop_transactions_manager.php');
require_once('units/shop_transactions_handler.php');
require_once('units/shop_warehouse_handler.php');
require_once('units/shop_transaction_items_manager.php');
require_once('units/shop_transaction_plans_manager.php');
require_once('units/shop_recurring_payments_manager.php');
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
	// interval units
	const DAY = 0;
	const WEEK = 1;
	const MONTH = 2;
	const YEAR = 3;

	// status
	const PENDING = 0;
	const ACTIVE = 1;
	const SKIPPED = 2;
	const FAILED = 3;
	const SUSPENDED = 4;
	const CANCELED = 5;
	const EXPIRED = 6;

	// status to signal
	public static $signals = array(
		self::PENDING => 'recurring-payment-pending',
		self::ACTIVE => 'recurring-payment',
		self::SKIPPED => 'recurring-payment-skipped',
		self::FAILED => 'recurring-payment-failed',
		self::SUSPENDED => 'recurring-payment-suspended',
		self::CANCELED => 'recurring-payment-canceled',
		self::EXPIRED => 'recurring-payment-expired'
	);
}


class CardType {
	const VISA = 0;
	const MASTERCARD = 1;
	const DISCOVER = 2;
	const AMERICAN_EXPRESS = 3;
	const MAESTRO = 4;

	public static $names = array(
		self::VISA => 'Visa',
		self::MASTERCARD => 'MasterCard',
		self::DISCOVER => 'Discover',
		self::AMERICAN_EXPRESS => 'American Express',
		self::MAESTRO => 'Maestro'
	);
}


class PaymentMethodError extends Exception {};


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
		Events::register('shop', 'shopping-cart-changed');
		Events::register('shop', 'before-checkout');
		Events::register('shop', 'transaction-completed');
		Events::register('shop', 'transaction-canceled');

		// register recurring events
		foreach (RecurringPayment::$signals as $status => $signal_name)
			Events::register('shop', $signal_name);

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
				url_GetFromFilePath($this->path.'images/icon.svg'),
				'javascript:void(0);',
				5  // level
			);

			$shop_menu->addChild(null, new backend_MenuItem(
				$this->getLanguageConstant('menu_items'),
				url_GetFromFilePath($this->path.'images/items.svg'),
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
				url_GetFromFilePath($this->path.'images/recurring_plans.svg'),
				'javascript: void(0);', 5
			);
			$shop_menu->addChild('shop_recurring_plans', $recurring_plans_menu);

			$shop_menu->addSeparator(5);

			$shop_menu->addChild(null, new backend_MenuItem(
				$this->getLanguageConstant('menu_categories'),
				url_GetFromFilePath($this->path.'images/categories.svg'),
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
				url_GetFromFilePath($this->path.'images/item_sizes.svg'),
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
				url_GetFromFilePath($this->path.'images/manufacturers.svg'),
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
				url_GetFromFilePath($this->path.'images/delivery.svg'),
				'javascript: void(0);', 5
			);

			$shop_menu->addChild('shop_delivery_methods', $delivery_menu);

			$shop_menu->addSeparator(5);

			$shop_menu->addChild(null, new backend_MenuItem(
				$this->getLanguageConstant('menu_special_offers'),
				url_GetFromFilePath($this->path.'images/special_offers.svg'),
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
				url_GetFromFilePath($this->path.'images/payment_methods.svg'),
				'javascript: void(0);', 5
			);

			$shop_menu->addChild('shop_payment_methods', $methods_menu);

			$shop_menu->addChild(null, new backend_MenuItem(
				$this->getLanguageConstant('menu_currencies'),
				url_GetFromFilePath($this->path.'images/currencies.svg'),
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
				url_GetFromFilePath($this->path.'images/transactions.svg'),
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
				url_GetFromFilePath($this->path.'images/warehouse.svg'),
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
				url_GetFromFilePath($this->path.'images/stock.svg'),
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
				url_GetFromFilePath($this->path.'images/settings.svg'),

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

			case 'show_recurring_plan':
				$this->tag_RecurringPlan($params, $children);
				break;

			case 'show_transaction_list':
				$handler = ShopTransactionsHandler::getInstance($this);
				$handler->tag_TransactionList($params, $children);
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

			case 'set_item_as_cart':
				$this->setItemAsCart($params, $children);
				break;

			case 'set_cart_from_template':
				$this->setCartFromTemplate($params, $children);
				break;

			case 'set_recurring_plan':
				$this->setRecurringPlan($params, $children);
				break;

			case 'cancel_recurring_plan':
				$this->cancelRecurringPlan($params, $children);
				break;

			case 'include_scripts':
				$this->includeScripts();
				break;

			case 'include_cart_scripts':
				$this->includeCartScripts();
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

			case 'json_set_recurring_plan':
				$this->json_SetRecurringPlan();
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

		// set shop in testing mode by default
		$this->saveSetting('testing_mode', 1);

		// create shop items table
		$sql = "
			CREATE TABLE `shop_items` (
				`id` int NOT NULL AUTO_INCREMENT,
				`uid` VARCHAR(13) NOT NULL,";

		foreach($list as $language)
			$sql .= "`name_{$language}` VARCHAR( 255 ) NOT NULL DEFAULT '',";

		foreach($list as $language)
			$sql .= "`description_{$language}` TEXT NOT NULL ,";

		$sql .= "
				`gallery` INT NOT NULL,
				`manufacturer` INT NOT NULL,
				`size_definition` INT NULL,
				`colors` VARCHAR(255) NOT NULL DEFAULT '',
				`author` INT NOT NULL,
				`views` INT NOT NULL,
				`price` DECIMAL(8,2) NOT NULL,
				`tax` DECIMAL(3,2) NOT NULL,
				`weight` DECIMAL(8,2) NOT NULL,
				`votes_up` INT NOT NULL,
				`votes_down` INT NOT NULL,
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
				`category` INT NOT NULL,
				`item` INT NOT NULL,
				KEY `category` (`category`),
				KEY `item` (`item`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create table for related shop items
		$sql = "
			CREATE TABLE IF NOT EXISTS `shop_related_items` (
				`item` INT NOT NULL,
				`related` INT NOT NULL,
				KEY `item` (`item`,`related`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		$db->query($sql);

		// create shop currencies tableshop_related_items
		$sql = "
			CREATE TABLE `shop_currencies` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`currency` VARCHAR(5) NOT NULL,
				PRIMARY KEY ( `id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop item sizes table
		$sql = "
			CREATE TABLE `shop_item_sizes` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`name` VARCHAR(25) NOT NULL,
				PRIMARY KEY ( `id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop item size values table
		$sql = "
			CREATE TABLE `shop_item_size_values` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`definition` INT NOT NULL,";

		foreach($list as $language)
			$sql .= "`value_{$language}` VARCHAR( 50 ) NOT NULL DEFAULT '',";

		$sql .= "PRIMARY KEY ( `id` ),
			KEY `definition` (`definition`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop categories table
		$sql = "
			CREATE TABLE `shop_categories` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`text_id` VARCHAR(32) NOT NULL,
				`parent` INT NOT NULL DEFAULT '0',
				`image` INT NULL,";

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
			`id` INT NOT NULL AUTO_INCREMENT,
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
			`id` INT NOT NULL AUTO_INCREMENT,
			`buyer` INT NOT NULL,
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
			`id` INT NOT NULL AUTO_INCREMENT,
			`buyer` INT NOT NULL,
			`system_user` int NULL,
			`address` INT NOT NULL,
			`uid` varchar(30) NOT NULL,
			`type` smallint(6) NOT NULL,
			`status` smallint(6) NOT NULL,
			`currency` INT NOT NULL,
			`handling` decimal(8,2) NOT NULL,
			`shipping` decimal(8,2) NOT NULL,
			`weight` decimal(4,2) NOT NULL,
			`payment_method` varchar(255) NOT NULL,
			`delivery_method` varchar(255) NOT NULL,
			`remark` text NOT NULL,
			`token` varchar(255) NOT NULL,
			`total` decimal(8,2) NOT NULL,
			`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
				  KEY `buyer` (`buyer`),
				  KEY `system_user` (`system_user`),
				  KEY `address` (`address`)
			  ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop transaction items table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_transaction_items` (
			`id` INT NOT NULL AUTO_INCREMENT,
			`transaction` INT NOT NULL,
			`item` INT NOT NULL,
			`price` DECIMAL(8,2) NOT NULL,
			`tax` DECIMAL(8,2) NOT NULL,
			`amount` INT NOT NULL,
			`description` varchar(500) NOT NULL,
			PRIMARY KEY (`id`),
				  KEY `transaction` (`transaction`),
				  KEY `item` (`item`)
			  ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop transaction plans table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_transaction_plans` (
			`id` int NOT NULL AUTO_INCREMENT,
			`transaction` int NOT NULL,
			`plan_name` varchar(64) NOT NULL,
			`trial` int NOT NULL,
			`trial_count` int NOT NULL,
			`interval` int NOT NULL,
			`interval_count` int NOT NULL,
			`start_time` timestamp NULL,
			`end_time` timestamp NULL,
			PRIMARY KEY (`id`),
				  KEY `transaction` (`transaction`),
				  KEY `plan_name` (`plan_name`)
			  ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create show recurring payments table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_recurring_payments` (
			`id` INT NOT NULL AUTO_INCREMENT,
			`plan` INT NOT NULL,
			`amount` DECIMAL(8,2) NOT NULL,
			`status` INT NOT NULL,
			`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
				  KEY `index_by_plan` (`plan`)
			  ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop stock table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_warehouse` (
			`id` int NOT NULL AUTO_INCREMENT,
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
			`id` int NOT NULL AUTO_INCREMENT,
			`item` int NOT NULL,
			`size` int DEFAULT NULL,
			`amount` int NOT NULL,
			PRIMARY KEY (`id`),
				  KEY `item` (`item`)
			  ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop manufacturers table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_manufacturers` (
			`id` int NOT NULL AUTO_INCREMENT,";

		foreach($list as $language)
			$sql .= "`name_{$language}` VARCHAR(255) NOT NULL DEFAULT '',";

		$sql .= " `web_site` varchar(255) NOT NULL,
			`logo` int NOT NULL,
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
			'shop_delivery_address',
			'shop_transactions',
			'shop_transaction_items',
			'shop_transaction_plans',
			'shop_recurring_payments',
			'shop_warehouse',
			'shop_stock',
			'shop_related_items',
			'shop_manufacturers'
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
	 */
	public function includeScripts() {
		if (!class_exists('head_tag') || !class_exists('collection'))
			return;

		$head_tag = head_tag::getInstance();
		$collection = collection::getInstance();
		$css_file = _DESKTOP_VERSION ? 'checkout.css' : 'checkout_mobile.css';

		$collection->includeScript(collection::DIALOG);
		$collection->includeScript(collection::PAGE_CONTROL);
		$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/'.$css_file), 'rel'=>'stylesheet', 'type'=>'text/css'));
		$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/checkout.js'), 'type'=>'text/javascript'));
	}

	/**
	 * Include shopping cart scripts.
	 */
	public function includeCartScripts() {
		if (!class_exists('head_tag') || !class_exists('collection'))
			return;

		$head_tag = head_tag::getInstance();
		$collection = collection::getInstance();

		$collection->includeScript(collection::COMMUNICATOR);
		$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/cart.js'), 'type'=>'text/javascript'));
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

		if (class_exists('contact_form')) {
			$contact_form = contact_form::getInstance();
			$template->registerTagHandler('cms:template_list', $contact_form, 'tag_TemplateList');
		}

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save settings
	 */
	private function saveSettings() {
		// save new settings
		$payment_completed = fix_chars($_REQUEST['payment_completed_template']);
		$recurring_started = fix_chars($_REQUEST['recurring_payment_started_template']);
		$recurring_canceled = fix_chars($_REQUEST['recurring_payment_canceled_template']);
		$shop_location = fix_chars($_REQUEST['shop_location']);
		$fixed_country = fix_chars($_REQUEST['fixed_country']);
		$testing_mode = fix_id($_REQUEST['testing_mode']);

		$this->saveSetting('payment_completed_template', $payment_completed);
		$this->saveSetting('recurring_payment_started_template', $recurring_started);
		$this->saveSetting('recurring_payment_canceled_template', $recurring_canceled);
		$this->saveSetting('shop_location', $shop_location);
		$this->saveSetting('fixed_country', $fixed_country);
		$this->saveSetting('testing_mode', $testing_mode);

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
	public function generateVariationId($uid, $properties=array()) {
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
			Events::trigger('shop', 'shopping-cart-changed');
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
			Events::trigger('shop', 'shopping-cart-changed');
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
	 * Cancel recurring payment plan for specified user or transaction.
	 * If not provided system will try to find information for currently
	 * logged user.
	 *
	 * @param array $tag_params
	 * @param array $children
	 * @return boolean
	 */
	private function cancelRecurringPlan($tag_params, $children) {
		$result = false;
		$user_id = null;
		$transaction_id = null;

		$transaction_manager = ShopTransactionsManager::getInstance();

		// try to get user id
		if (isset($tag_params['user']))
			$user_id = fix_id($tag_params['user']);

		if (is_null($user_id) && $_SESSION['logged'])
			$user_id = $_SESSION['uid'];

		// try to get transaction id
		if (isset($tag_params['transaction']))
			$transaction_id = fix_chars($tag_params['transaction']);

		if (is_null($transaction_id) && !is_null($user_id)) {
			$transaction = $transaction_manager->getSingleItem(
				array('id'),
				array('system_user' => $user_id)
			);

			if (is_object($transaction))
				$transaction_id = $transaction->id;
		}

		// cancel recurring plan
		if (!is_null($transaction_id))
			$this->cancelTransaction($transaction_id);

		return $result;
	}

	/**
	 * Get recurring payment plan associated with specified user. If no
	 * user id is specified system will try to find payment plan associated
	 * with currently logged in user.
	 *
	 * @param integer $user_id
	 * @return object
	 */
	public function getRecurringPlan($user_id=null) {
		$result = null;

		// get managers
		$transaction_manager = ShopTransactionsManager::getInstance();
		$plan_manager = ShopTransactionPlansManager::getInstance();
		$recurring_manager = ShopRecurringPaymentsManager::getInstance();

		// try to get currently logged user
		if (is_null($user_id) && $_SESSION['logged'])
			$user_id = $_SESSION['uid'];

		// we need to have a user
		if (is_null($user_id))
			return $result;

		// get all recurring payment transactions for current buyer
		$transaction = $transaction_manager->getSingleItem(
			array('id'),
			array(
				'type'			=> TransactionType::SUBSCRIPTION,
				'status'		=> TransactionStatus::COMPLETED,
				'system_user'	=> $user_id
			),
			array('timestamp'),
			false  // ascending
		);

		// user doesn't have a recurring payment
		if (!is_object($transaction))
			return $result;

		$plan = $plan_manager->getSingleItem(
			$plan_manager->getFieldNames(),
			array('transaction' => $transaction->id)
		);

		// get last payment
		$last_payment = $recurring_manager->getSingleItem(
			$recurring_manager->getFieldNames(),
			array('plan' => $plan->id),
			array('timestamp'),
			false  // ascending
		);

		if (is_object($last_payment) && $last_payment->status <= RecurringPayment::ACTIVE)
			$result = $plan;

		return $result;
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
		$transaction = $manager->getSingleItem(
			$manager->getFieldNames(),
			array('uid' => $transaction_id)
		);

		// set status of transaction
		if (is_object($transaction)) {
			$manager->updateData(
				array('status' => $status),
				array('id' => $transaction->id)
			);
			$result = true;

			// trigger event
			switch ($status) {
				case TransactionStatus::COMPLETED:
					Events::trigger('shop', 'transaction-completed', $transaction);
					unset($_SESSION['transaction']);

					if ($transaction->type == TransactionType::SUBSCRIPTION) {
						if (isset($this->settings['recurring_payment_started_template'])) {
							$template = $this->settings['recurring_payment_started_template'];
							$this->sendTransactionMail($transaction, $template);
						}

					} else if ($transaction->type == TransactionType::SHOPPING_CART) {
						if (isset($this->settings['payment_completed_template'])) {
							$template = $this->settings['payment_completed_template'];
							$this->sendTransactionMail($transaction, $template);
						}
					}

					break;

				case TransactionStatus::CANCELED:
					Events::trigger('shop', 'transaction-canceled', $transaction);

					// send email notification
					if ($transaction->type == TransactionType::SUBSCRIPTION)
						if (isset($this->settings['recurring_payment_canceled_template'])) {
							$template = $this->settings['recurring_payment_canceled_template'];
							$this->sendTransactionMail($transaction, $template);
						}
					break;
			}
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
	 * Cancel specified transaction.
	 *
	 * @param integer $id
	 * @param string $uid
	 * @param string $token
	 * @return boolean
	 */
	public function cancelTransaction($id=null, $uid=null, $token=null) {
		$result = false;
		$conditions = array();

		// we should always have at least one identifying method
		if (is_null($id) && is_null($uid) && is_null($token)) {
			trigger_error('Shop: Unable to cancel transaction, no id provided.', E_USER_WARNING);
			return $result;
		}

		// prepare conditions for manager
		if (!is_null($id))
			$conditions['id'] = $id;

		if (!is_null($uid))
			$conditions['uid'] = $uid;

		if (!is_null($token))
			$conditions['token'] = $token;

		// get transaction
		$manager = ShopTransactionsManager::getInstance();
		$transaction = $manager->getSingleItem($manager->getFieldNames(), $conditions);

		// cancel transaction
		if (is_object($transaction) && array_key_exists($transaction->payment_method, $this->payment_methods)) {
			// get payment method and initate cancelation process
			$payment_method = $this->payment_methods[$transaction->payment_method];

			if ($transaction->type == TransactionType::SUBSCRIPTION)
				$result = $payment_method->cancel_recurring_payment($transaction);

		} else {
			// unknown method or transaction, log error
			trigger_error('Shop: Unknown payment method or transaction. Unable to cancel recurring payment.', E_USER_WARNING);
		}
	}

	/**
	 * Add recurring payment for specified plan.
	 * Returns true if new recurring payment was added for
	 * specified transaction.
	 *
	 * @param integer $plan_id
	 * @param float $amount
	 * @param integer $status
	 * @return boolean
	 */
	public function addRecurringPayment($plan_id, $amount, $status) {
		$result = false;

		// get managers
		$manager = ShopRecurringPaymentsManager::getInstance();
		$plan_manager = ShopTransactionPlansManager::getInstance();
		$buyer_manager = ShopBuyersManager::getInstance();
		$transaction_manager = ShopTransactionsManager::getInstance();

		// get transaction and associated plan
		$plan = $plan_manager->getSingleItem(
			$plan_manager->getFieldNames(),
			array('id' => $plan_id)
		);

		// plan id is not valid
		if (!is_object($plan))
			return $result;

		// insert new data
		$data = array(
			'plan'		=> $plan->id,
			'amount'	=> $amount,
			'status'	=> $status
		);

		$manager->insertData($data);
		$payment_id = $manager->getInsertedID();
		$result = true;

		// get newly inserted data
		$payment = $manager->getSingleItem(
			$manager->getFieldNames(),
			array('id' => $payment_id)
		);

		// get transaction and buyer
		$transaction = $transaction_manager->getSingleItem(
			$transaction_manager->getFieldNames(),
			array('id' => $plan->transaction)
		);

		// trigger event
		Events::trigger('shop', RecurringPayment::$signals[$status], $transaction, $plan, $payment);

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

		$template->restoreXML();
		$template->parse();
	}

	/**
	 * Show message before user gets redirected.
	 */
	private function showCheckoutRedirect() {
		$template = new TemplateHandler('checkout_message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
			'message'		=> $this->getLanguageConstant('message_checkout_redirect'),
			'button_text'	=> $this->getLanguageConstant('button_take_me_back'),
			'button_action'	=> url_Make('', 'home'),
			'redirect'		=> true
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
		$template->registerTagHandler('_canceled_message', $this, 'tag_CanceledMessage');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Return default currency using JSON object
	 */
	private function json_GetCurrency() {
		print json_encode($this->getDefaultCurrency());
	}

	/**
	 * Set recurring plan.
	 */
	public function json_SetRecurringPlan() {
		$recurring_plan = fix_chars($_REQUEST['plan']);
		$_SESSION['recurring_plan'] = $recurring_plan;
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
		Events::trigger('shop', 'shopping-cart-changed');

		print json_encode(true);
	}

	/**
	 * Add item to shopping cart using JSON request
	 */
	private function json_AddItemToCart() {
		$uid = fix_chars($_REQUEST['uid']);
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();

		if (isset($_REQUEST['properties'])) {
			$properties = isset($_REQUEST['properties']) ? fix_chars($_REQUEST['properties']) : array();
			$variation_id = $this->generateVariationId($uid, $properties);

		} else if ($_REQUEST['variation_id']) {
			$variation_id = fix_chars($_REQUEST['variation_id']);
		}

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
				'uid'			=> $item->uid,
				'variation_id'	=> $variation_id
			);

			// update shopping cart
			$_SESSION['shopping_cart'] = $cart;
		}

		Events::trigger('shop', 'shopping-cart-changed');
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

		Events::trigger('shop', 'shopping-cart-changed');
		print json_encode($result);
	}

	/**
	 * Change the amount of items in shopping cart for specified UID and variation id.
	 */
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

		Events::trigger('shop', 'shopping-cart-changed');
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
		$payment_method = $this->getPaymentMethod(null);
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

		$result = $this->getCartSummary(TransactionType::SHOPPING_CART, $recipient, $uid, $payment_method);
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
	 * @param integer $type
	 * @param array $recipient
	 * @param string $transaction_id
	 * @param object $payment_method
	 * @return array
	 */
	private function getCartSummary($type, $recipient, $transaction_id, $payment_method=null) {
		$result = array();
		$default_language = MainLanguageHandler::getInstance()->getDefaultLanguage();

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

		if (isset($_SESSION['recurring_plan'])) {
			$plan_name = $_SESSION['recurring_plan'];

			// get selected recurring plan
			$plans = array();
			if (!is_null($payment_method))
				$plans = $payment_method->get_recurring_plans();

			// get recurring plan price
			if (isset($plans[$plan_name])) {
				$plan = $plans[$plan_name];

				$handling = $plan['setup_price'];
				$total_money = $plan['price'];
			}

		} else {
			// colect ids from session
			$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
			$ids = array_keys($cart);

			if (count($cart) == 0)
				return $result;

			// get managers
			$manager = ShopItemManager::getInstance();

			// get items from database and prepare result
			$items = $manager->getItems($manager->getFieldNames(), array('uid' => $ids));

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
	 * Get payment method for checkout form.
	 *
	 * @param array $tag_params
	 * @return object
	 */
	private function getPaymentMethod($tag_params) {
		$result = null;
		$method_name = null;

		// require at least one payment method
		if (count($this->payment_methods) == 0)
			throw new PaymentMethodError('No payment methods found!');

		// get method name from various sources
		if (!is_null($tag_params) && isset($tag_params['payment_method']))
			$method_name = fix_chars($tag_params['payment_method']);

		if (isset($_REQUEST['payment_method']) && is_null($method_name))
			$method_name = fix_chars($_REQUEST['payment_method']);

		// get method based on its name
		if (isset($this->payment_methods[$method_name]))
			$result = $this->payment_methods[$method_name];

		return $result;
	}

	/**
	 * Get billing information if needed.
	 */
	private function getBillingInformation($payment_method) {
		$result = array();

		// get billing information
		if (!$payment_method->provides_information()) {
			$fields = array(
				'billing_full_name', 'billing_card_type', 'billing_credit_card', 'billing_expire_month',
				'billing_expire_year', 'billing_cvv'
			);

			foreach($fields as $field)
				if (isset($_REQUEST[$field]))
					$result[$field] = fix_chars($_REQUEST[$field]);

			// remove dashes and empty spaces
			$result['billing_credit_card'] = str_replace(
				array(' ', '-', '_'), '',
				$result['billing_credit_card']
			);
		}

		return $result;
	}

	/**
	 * Get shipping information.
	 *
	 * @return array
	 */
	private function getShippingInformation() {
		$result = array();
		$fields = array('name', 'email', 'phone', 'street', 'street2', 'city', 'zip', 'country', 'state');

		// get delivery information
		foreach($fields as $field)
			if (isset($_REQUEST[$field]))
				$result[$field] = fix_chars($_REQUEST[$field]);

		return $result;
	}

	/**
	 * Get existing or create a new user account.
	 *
	 * @return object
	 */
	private function getUserAccount() {
		$result = null;
		$manager = ShopBuyersManager::getInstance();
		$existing_user = isset($_POST['existing_user']) ? fix_id($_POST['existing_user']) : null;

		// set proper account data based on users choice
		if (!is_null($existing_user))
			switch ($existing_user) {
			case User::EXISTING:
				$retry_manager = LoginRetryManager::getInstance();

				$email = fix_chars($_REQUEST['sign_in_email']);
				$password = hash_hmac(
					'sha256',
					$_REQUEST['sign_in_password'],
					shop::BUYER_SECRET
				);

				// get account from database
				$account = $manager->getSingleItem(
					$manager->getFieldNames(),
					array(
						'email'		=> $email,
						'password'	=> $password,
						'guest'		=> 0,
						// 'validated'	=> 1
					));

				// if account exists pass it as result
				if (is_object($account))
					$result = $account;

				break;

			case User::CREATE:
				$data = array(
					'first_name'	=> fix_chars($_REQUEST['first_name']),
					'last_name'		=> fix_chars($_REQUEST['last_name']),
					'email'			=> fix_chars($_REQUEST['new_email']),
					'uid'			=> isset($_REQUEST['uid']) ? fix_chars($_REQUEST['uid']) : '',
					'validated'		=> 0,
					'guest'			=> 0
				);

				if ($_REQUEST['new_password'] == $_REQUEST['new_password_confirm'] || empty($_REQUEST['new_password'])) {
					// password fields match, salt and hash password
					$data['password'] = hash_hmac(
						'sha256',
						$_REQUEST['new_password'],
						shop::BUYER_SECRET
					);

					// create new account
					$manager->insertData($data);

					// get account object
					$id = $manager->getInsertedID();
					$result = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));
				}

				break;

			case User::GUEST:
				// collect data
				if (isset($_REQUEST['name'])) {
					$name = explode(' ', fix_chars($_REQUEST['name']), 1);
					$first_name = $name[0];
					$last_name = count($name) > 1 ? $name[1] : '';

				} else {
					$first_name = fix_chars($_REQUEST['first_name']);
					$last_name = fix_chars($_REQUEST['last_name']);
				}

				$uid = isset($_REQUEST['uid']) ? fix_chars($_REQUEST['uid']) : null;
				$email = isset($_REQUEST['email']) ? fix_chars($_REQUEST['email']) : null;

				$conditions = array();
				$data = array(
					'first_name'	=> $first_name,
					'last_name'		=> $last_name,
					'password'		=> '',
					'validated'		=> 0,
					'guest'			=> 1
				);

				// include uid if specified
				if (!is_null($uid)) {
					$conditions['uid'] = $uid;
					$data['uid'] = $uid;
				}

				// include email if specified
				if (!is_null($email)) {
					$conditions['email'] = $email;
					$data['email'] = $email;
				}

				// try finding existing account
				if (count($conditions) > 0) {
					$account = $manager->getSingleItem($manager->getFieldNames(), $conditions);

					if (is_object($account))
						$result = $account;
				}

				// create new account
				if (is_null($result)) {
					// create new account
					$manager->insertData($data);

					// get account object
					$id = $manager->getInsertedID();
					$result = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));
				}

				break;
			}

		return $result;
	}

	/**
	 * Get user's address.
	 */
	private function getAddress($buyer, $shipping_information) {
		$address_manager = ShopDeliveryAddressManager::getInstance();

		// try to associate address with transaction
		$address = $address_manager->getSingleItem(
			$address_manager->getFieldNames(),
			array(
				'buyer'		=> $buyer->id,
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
			$result = $address;

		} else {
			// create new address
			$address_manager->insertData(array(
				'buyer'		=> $buyer->id,
				'name'		=> $shipping_information['name'],
				'street'	=> $shipping_information['street'],
				'street2'	=> isset($shipping_information['street2']) ? $shipping_information['street2'] : '',
				'phone'		=> $shipping_information['phone'],
				'city'		=> $shipping_information['city'],
				'zip'		=> $shipping_information['zip'],
				'state'		=> $shipping_information['state'],
				'country'	=> $shipping_information['country'],
			));

			$id = $address_manager->getInsertedID();
			$result = $address_manager->getSingleItem($address_manager->getFieldNames(), array('id' => $id));
		}

		return $result;
	}

	/**
	 * Update transaction data.
	 *
	 * @param integer $type
	 * @param object $payment_method
	 * @param string $delivery_method
	 * @param object $buyer
	 * @param object $address
	 * @return array
	 */
	private function updateTransaction($type, $payment_method, $delivery_method, $buyer, $address) {
		global $db;

		$result = array();
		$transactions_manager = ShopTransactionsManager::getInstance();
		$transaction_items_manager = ShopTransactionItemsManager::getInstance();
		$transaction_plans_manager = ShopTransactionPlansManager::getInstance();

		// generate recipient array for delivery method
		if (!is_null($address)) {
			$recipient = array(
				'street'	=> array($address->street),
				'city'		=> $address->city,
				'zip_code'	=> $address->zip,
				'state'		=> $address->state,
				'country'	=> $address->country
			);

			if (isset($address->street2))
				$recipient['street'][] = $address->street2;

		} else {
			$recipient = null;
		}

		// update buyer
		if (!is_null($buyer))
			$result['buyer'] = $buyer->id;

		// determine if we need a new session
		$new_transaction = true;

		if (isset($_SESSION['transaction']) && isset($_SESSION['transaction']['uid'])) {
			$uid = $_SESSION['transaction']['uid'];
			$transaction = $transactions_manager->getSingleItem(array('status'), array('uid' => $uid));
			$new_transaction = !(is_object($transaction) && $transaction->status == TransactionStatus::PENDING);
		}

		// check if we have existing transaction in our database
		if ($new_transaction) {
			// get shopping cart summary
			$uid = uniqid('', true);
			$summary = $this->getCartSummary($type, $recipient, $uid, $payment_method);

			$result['uid'] = $uid;
			$result['type'] = $type;
			$result['status'] = TransactionStatus::PENDING;
			$result['handling'] = $summary['handling'];
			$result['shipping'] = $summary['shipping'];
			$result['weight'] = $summary['weight'];
			$result['payment_method'] = $payment_method->get_name();
			$result['delivery_method'] = $delivery_method;
			$result['remark'] = '';
			$result['total'] = $summary['total'];

			// add address if needed
			if (!is_null($address))
				$result['address'] = $address->id;

			// assign system user
			if ($_SESSION['logged'])
				$result['system_user'] = $_SESSION['uid'];

			// create new transaction
			$transactions_manager->insertData($result);
			$result['id'] = $transactions_manager->getInsertedID();

			// store transaction data to session
			$_SESSION['transaction'] = $result;

		} else {
			$uid = $_SESSION['transaction']['uid'];
			$summary = $this->getCartSummary($type, $recipient, $uid, $payment_method);

			// there's already an existing transaction
			$result = $_SESSION['transaction'];
			$result['handling'] = $summary['handling'];
			$result['shipping'] = $summary['shipping'];
			$result['total'] = $summary['total'];

			$data = array(
				'handling'	=> $summary['handling'],
				'shipping'	=> $summary['shipping'],
				'total'		=> $summary['total']
			);

			if (!is_null($address))
				$data['address'] = $address->id;

			// update existing transaction
			$transactions_manager->updateData($data, array('uid' => $uid));

			// update session storage with newest data
			$_SESSION['transaction'] = $result;
		}

		// remove items associated with transaction
		$transaction_items_manager->deleteData(array('transaction' => $result['id']));

		// remove plans associated with transaction
		$transaction_plans_manager->deleteData(array('transaction' => $result['id']));

		// store items
		if (count($summary['items_for_checkout']) > 0)
			foreach($summary['items_for_checkout'] as $uid => $item) {
				$transaction_items_manager->insertData(array(
					'transaction'	=> $result['id'],
					'item'			=> $item['id'],
					'price'			=> $item['price'],
					'tax'			=> $item['tax'],
					'amount'		=> $item['count'],
					'description'	=> $item['description']
				));
			}

		// create plan entry
		if (isset($_SESSION['recurring_plan'])) {
			$plan_name = $_SESSION['recurring_plan'];
			$plan_list = $payment_method->get_recurring_plans();
			$plan = isset($plan_list[$plan_name]) ? $plan_list[$plan_name] : null;

			if (!is_null($plan))
				$transaction_plans_manager->insertData(array(
					'transaction'		=> $result['id'],
					'plan_name'			=> $plan_name,
					'trial'				=> $plan['trial'],
					'trial_count'		=> $plan['trial_count'],
					'interval'			=> $plan['interval'],
					'interval_count'	=> $plan['interval_count'],
					'start_time'		=> $db->format_timestamp($plan['start_time']),
					'end_time'			=> $db->format_timestamp($plan['end_time'])
				));
		}

		// if affiliate system is active, update referral
		if (isset($_SESSION['referral_id']) && class_exists('affiliates')) {
			$referral_id = $_SESSION['referral_id'];
			$referrals_manager = AffiliateReferralsManager::getInstance();

			$referrals_manager->updateData(
				array('transaction' => $result['id']),
				array('id' => $referral_id)
			);
		}

		return $result;
	}

	/**
	 * Update buyer information for specified transaction. This function is
	 * called by the payment methods that provide buyer information. Return
	 * value denotes whether information update is successful and if method
	 * should complete the billing process.
	 *
	 * @param string $transaction_uid
	 * @param array $buyer_data
	 * @return boolean
	 */
	public function updateBuyerInformation($transaction_uid, $buyer_data) {
		$result = false;
		$transaction_manager = ShopTransactionsManager::getInstance();
		$buyer_manager = ShopBuyersManager::getInstance();

		// make sure buyer is marked as guest if password is not specified
		if (!isset($buyer_data['password']))
			$buyer_data['guest'] = 1;

		// get transaction from database
		$transaction = $transaction_manager->getSingleItem(
			array('id', 'buyer'),
			array('uid' => $transaction_uid)
		);

		// try to get buyer from the system based on uid
		if (isset($buyer_data['uid']))
			$buyer = $buyer_manager->getSingleItem(
				$buyer_manager->getFieldNames(),
				array('uid' => $buyer_data['uid'])
			);

		// update buyer information
		if (is_object($transaction)) {
			// get buyer id
			if (is_object($buyer)) {
				$buyer_id = $buyer->id;

				// update buyer information
				$buyer_manager->updateData($buyer_data, array('id' => $buyer->id));

			} else {
				// create new buyer
				$buyer_manager->insertData($buyer_data);
				$buyer_id = $buyer_manager->getInsertedID();
			}

			// update transaction buyer
			$transaction_manager->updateData(
				array('buyer'	=> $buyer_id),
				array('id'		=> $transaction->id)
			);

			$result = true;

		} else {
			trigger_error("No transaction with specified id: {$transaction_uid}");
		}

		return $result;
	}

	/**
	 * Check if data doesn't contain required fields.
	 *
	 * @param array $data
	 * @param array $required
	 * @param array $start
	 * @return array
	 */
	private function checkFields($data, $required, $start=array()) {
		$result = $start;
		$keys = array_keys($data);

		foreach($required as $field)
			if (!in_array($field, $keys))
				$return[] = $field;

		return $result;
	}

	/**
	 * Send email for transaction using specified template.
	 *
	 * @param object $transaction
	 * @param string $template
	 * @return boolean
	 */
	private function sendTransactionMail($transaction, $template) {
		global $language;

		$result = false;

		// require contact form
		if (!class_exists('contact_form'))
			return $result;

		$email_address = null;
		$contact_form = contact_form::getInstance();

		// template replacement data
		$fields = array(
			'transaction_id'				=> $transaction->id,
			'transaction_uid'				=> $transaction->uid,
			'status'						=> $transaction->status,
			'handling'						=> $transaction->handling,
			'shipping'						=> $transaction->shipping,
			'total'							=> $transaction->total,
			'weight'						=> $transaction->weight,
			'payment_method'				=> $transaction->payment_method,
			'delivery_method'				=> $transaction->delivery_method,
			'remark'						=> $transaction->remark,
			'token'							=> $transaction->token,
			'timestamp'						=> $transaction->timestamp
		);

		$timestamp = strtotime($transaction->timestamp);
		$fields['date'] = date($this->getLanguageConstant('format_date_short'), $timestamp);
		$fields['time'] = date($this->getLanguageConstant('format_time_short'), $timestamp);

		// get currency
		$currency_manager = ShopCurrenciesManager::getInstance();
		$currency = $currency_manager->getSingleItem(
				$currency_manager->getFieldNames(),
				array('id' => $transaction->currency)
			);

		if (is_object($currency))
			$fields['currency'] = $currency->currency;

		// add buyer information
		$buyer_manager = ShopBuyersManager::getInstance();
		$buyer = $buyer_manager->getSingleItem(
				$buyer_manager->getFieldNames(),
				array('id' => $transaction->buyer)
			);

		if (is_object($buyer)) {
			$fields['buyer_first_name'] = $buyer->first_name;
			$fields['buyer_last_name'] = $buyer->last_name;
			$fields['buyer_email'] = $buyer->email;
			$fields['buyer_uid'] = $buyer->uid;

			$email_address = $buyer->email;
		}

		// add system user information
		$user_manager = UserManager::getInstance();
		$user = $user_manager->getSingleItem(
			$user_manager->getFieldNames(),
			array('id' => $transaction->system_user)
		);

		if (is_object($user)) {
			$fields['user_name'] = $user->username;
			$fields['user_fullname'] = $user->fullname;
			$fields['user_email'] = $user->email;

			if (is_null($email_address) || empty($email_address)) {
				$email_address = $user->email;

			} else if ($email_address != $user->email) {
				$email_address = $email_address.','.$user->email;
			}
		}

		// add buyer address
		$address_manager = ShopDeliveryAddressManager::getInstance();
		$address = $address_manager->getSingleItem(
			$address_manager->getFieldNames(),
			array('id' => $transaction->address)
		);

		if (is_object($address)) {
			$fields['address_name'] = $address->name;
			$fields['address_street'] = $address->street;
			$fields['address_street2'] = $address->street2;
			$fields['address_phone'] = $address->phone;
			$fields['address_city'] = $address->city;
			$fields['address_zip'] = $address->zip;
			$fields['address_state'] = $address->state;
			$fields['address_country'] = $address->country;
		}

		// create item table
		switch ($transaction->type) {
			case TransactionType::SHOPPING_CART:
				$item_manager = ShopTransactionItemsManager::getInstance();
				$items = $item_manager->getItems(
					$item_manager->getFieldNames(),
					array('transaction' => $transaction->id)
				);

				if (count($items) > 0) {
					// create items table
					$text_table = str_pad($this->getLanguageConstant('column_name'), 40);
					$text_table .= str_pad($this->getLanguageConstant('column_price'), 8);
					$text_table .= str_pad($this->getLanguageConstant('column_amount'), 6);
					$text_table .= str_pad($this->getLanguageConstant('column_item_total'), 8);
					$text_table .= "\n" . str_repeat('-', 40 + 8 + 6 + 8) . "\n";

					$html_table = '<table border="0" cellspacing="5" cellpadding="0">';
					$html_table .= '<thead><tr>';
					$html_table .= '<td>'.$this->getLanguageConstant('column_name').'</td>';
					$html_table .= '<td>'.$this->getLanguageConstant('column_price').'</td>';
					$html_table .= '<td>'.$this->getLanguageConstant('column_amount').'</td>';
					$html_table .= '<td>'.$this->getLanguageConstant('column_item_total').'</td>';
					$html_table .= '</td></thead><tbody>';

					foreach ($items as $item) {
						// append item name with description
						if (empty($data['description']))
							$line = $item->name[$language] . ' (' . $item->description . ')'; else
							$line = $item->name[$language];

						$line = utf8_wordwrap($line, 40, "\n", true);
						$line = mb_split("\n", $line);

						// append other columns
						$line[0] = $line[0] . str_pad($item->price, 8, ' ', STR_PAD_LEFT);
						$line[0] = $line[0] . str_pad($item->amount, 6, ' ', STR_PAD_LEFT);
						$line[0] = $line[0] . str_pad($item->total, 8, ' ', STR_PAD_LEFT);

						// add this item to text table
						$text_table .= implode("\n", $line) . "\n\n";

						// form html row
						$row = '<tr><td>' . $item->name[$language];

						if (!empty($item->description))
							$row .= ' <small>' . $item->description . '</small>';

						$row .= '</td><td>' . $item->price . '</td>';
						$row .= '<td>' . $item->amount . '</td>';
						$row .= '<td>' . $item->total . '</td></tr>';

						// update subtotal
						$subtotal += $item->total;
					}

					// close text table
					$text_table .= str_repeat('-', 40 + 8 + 6 + 8) . "\n";
					$html_table .= '</tbody>';

					// create totals
					$text_table .= str_pad($this->getLanguageConstant('column_subtotal'), 15);
					$text_table .= str_pad($subtotal, 10, ' ', STR_PAD_LEFT) . "\n";

					$text_table .= str_pad($this->getLanguageConstant('column_shipping'), 15);
					$text_table .= str_pad($transaction->shipping, 10, ' ', STR_PAD_LEFT) . "\n";

					$text_table .= str_pad($this->getLanguageConstant('column_handling'), 15);
					$text_table .= str_pad($transaction->handling, 10, ' ', STR_PAD_LEFT) . "\n";

					$text_table .= str_repeat('-', 25);
					$text_table .= str_pad($this->getLanguageConstant('column_total'), 15);
					$text_table .= str_pad($transaction->total, 10, ' ', STR_PAD_LEFT) . "\n";

					$html_table .= '<tfoot>';
					$html_table .= '<tr><td colspan="2"></td><td>' . $this->getLanguageConstant('column_subtotal') . '</td>';
					$html_table .= '<td>' . $subtotal . '</td></tr>';

					$html_table .= '<tr><td colspan="2"></td><td>' . $this->getLanguageConstant('column_shipping') . '</td>';
					$html_table .= '<td>' . $transaction->shipping . '</td></tr>';

					$html_table .= '<tr><td colspan="2"></td><td>' . $this->getLanguageConstant('column_handling') . '</td>';
					$html_table .= '<td>' . $transaction->handling . '</td></tr>';

					$html_table .= '<tr><td colspan="2"></td><td><b>' . $this->getLanguageConstant('column_total') . '</b></td>';
					$html_table .= '<td><b>' . $transaction->total . '</b></td></tr>';

					$html_table .= '</tfoot>';

					// close table
					$html_table .= '</table>';

					// add field
					$fields['item_table'] = $text_table;
				}
				break;

			case TransactionType::SUBSCRIPTION:
				$plan_manager = ShopTransactionPlansManager::getInstance();
				$plan = $plan_manager->getSingleItem(
					$plan_manager->getFieldNames(),
					array('transaction' => $transaction->id)
				);

				// get payment method
				$plan_data = null;
				if (isset($this->payment_methods[$transaction->payment_method])) {
					$payment_method = $this->payment_methods[$transaction->payment_method];
					$plans = $payment_method->get_recurring_plans();

					if (isset($plans[$plan->plan_name]))
						$plan_data = $plans[$plan->plan_name];
				}

				// populate fields with plan params
				if (is_object($plan) && !is_null($plan_data)) {
					$fields['plan_text_id'] = $plan->plan_name;
					$fields['plan_name'] = $plan_data['name'][$language];
				}
				break;
		}

		// we require email address for sending
		if (is_null($email_address) || empty($email_address))
			return $result;

		// get mailer
		$mailer = $contact_form->getMailer();
		$sender = $contact_form->getSender();
		$template = $contact_form->getTemplate($template);

		// start creating message
		$mailer->start_message();
		$mailer->set_subject($template['subject']);
		$mailer->set_sender($sender['address'], $sender['name']);
		$mailer->add_recipient($email_address);

		$mailer->set_body($template['plain_body'], $template['html_body']);
		$mailer->set_variables($fields);

		// send email
		$mailer->send();

		return $result;
	}

	/**
	 * Show recurring plan from specified payment method.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_RecurringPlan($tag_params, $children) {
		$plan_name = null;
		$payment_method = $this->getPaymentMethod($tag_params);

		// we ned payment mothod to proceed
		if (!is_object($payment_method))
			return;

		// get plan name from the parameters
		if (isset($tag_params['plan']))
			$plan_name = fix_chars($tag_params['plan']);

		// get all the plans from payment method
		$plans = $payment_method->get_recurring_plans();

		// show plan
		if (count($plans) > 0 && !is_null($plan_name) && isset($plans[$plan_name])) {
			$template = $this->loadTemplate($tag_params, 'plan.xml');
			$current_plan = $this->getRecurringPlan();

			$params = $plans[$plan_name];
			$params['selected'] = is_object($current_plan) && $current_plan->plan_name == $plan_name;
			$params['text_id'] = $plan_name;

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
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
		$billing_information = array();
		$payment_method = null;
		$stage = isset($_REQUEST['stage']) ? fix_chars($_REQUEST['stage']) : null;
		$recurring = isset($_SESSION['recurring_plan']) && !empty($_SESSION['recurring_plan']);

		// decide whether to include shipping and account information
		if (isset($tag_params['include_shipping']))
			$include_shipping = fix_id($tag_params['include_shipping']) == 1; else
				$include_shipping = true;

		$bad_fields = array();
		$info_available = false;

		// grab user information
		if (!is_null($stage)) {
			// get payment method
			$payment_method = $this->getPaymentMethod($tag_params);

			if (is_null($payment_method))
				throw new PaymentMethodError('No payment method selected!');

			// get billing information
			$billing_information = $this->getBillingInformation($payment_method);
			$billing_required = array(
				'billing_full_name', 'billing_card_type', 'billing_credit_card', 'billing_expire_month',
				'billing_expire_year', 'billing_cvv'
			);
			$bad_fields = $this->checkFields($billing_information, $billing_required, $bad_fields);

			// get shipping information
			if ($include_shipping && $stage == 'set_info') {
				$shipping_information = $this->getShippingInformation();
				$shipping_required = array('name', 'email', 'street', 'city', 'zip', 'country');
				$bad_fields = $this->checkFields($shipping_information, $shipping_required, $bad_fields);
			}
		}

		$info_available = count($bad_fields) == 0 && !is_null($payment_method);

		// log bad fields if debugging is enabled
		if (count($bad_fields) > 0 && defined('DEBUG'))
			trigger_error('Checkout bad fields: '.implode(', ', $bad_fields), E_USER_NOTICE);

		if ($info_available) {
			$address_manager = ShopDeliveryAddressManager::getInstance();
			$currency_manager = ShopCurrenciesManager::getInstance();

			// get fields for payment method
			$return_url = url_Make('checkout_completed', 'shop', array('payment_method', $payment_method->get_name()));
			$cancel_url = url_Make('checkout_canceled', 'shop', array('payment_method', $payment_method->get_name()));

			// get currency info
			$currency = $this->settings['default_currency'];
			$currency_item = $currency_manager->getSingleItem(array('id'), array('currency' => $currency));

			if (is_object($currency_item))
				$transaction_data['currency'] = $currency_item->id;

			// get buyer
			$buyer = $this->getUserAccount();

			if ($include_shipping)
				$address = $this->getAddress($buyer, $shipping_information); else
				$address = null;

			// update transaction
			$transaction_type = $recurring ? TransactionType::SUBSCRIPTION : TransactionType::SHOPPING_CART;
			$summary = $this->updateTransaction($transaction_type, $payment_method, '', $buyer, $address);

			// emit signal and return if handled
			if ($stage == 'set_info') {
				$result_list = Events::trigger(
					'shop',
					'before-checkout',
					$payment_method->get_name(),
					$return_url,
					$cancel_url
				);

				foreach ($result_list as $result)
					if ($result) {
						$this->showCheckoutRedirect();
						return;
					}
			}

			// create new payment
			if ($recurring) {
				// recurring payment
				$checkout_fields = $payment_method->new_recurring_payment(
					$summary,
					$billing_information,
					$_SESSION['recurring_plan'],
					$return_url,
					$cancel_url
				);

			} else {
				// regular payment
				$checkout_fields = $payment_method->new_payment(
					$summary,
					$billing_information,
					$summary['items_for_checkout'],
					$return_url,
					$cancel_url
				);
			}

			// load template
			$template = $this->loadTemplate($tag_params, 'checkout_form.xml', 'checkout_template');
			$template->registerTagHandler('cms:checkout_items', $this, 'tag_CheckoutItems');
			$template->registerTagHandler('cms:delivery_methods', $this, 'tag_DeliveryMethodsList');

			// parse template
			$params = array(
				'checkout_url'		=> $payment_method->get_url(),
				'checkout_fields'	=> $checkout_fields,
				'checkout_name'		=> $payment_method->get_title(),
				'currency'			=> $this->getDefaultCurrency(),
				'recurring'			=> $recurring,
				'include_shipping'	=> $include_shipping,
			);

			// for recurring plans add additional params
			if ($recurring) {
				$plans = $payment_method->get_recurring_plans();
				$plan_name = $_SESSION['recurring_plan'];

				$plan = $plans[$plan_name];

				$params['plan_name'] = $plan['name'];
				$params['plan_description'] = $this->formatRecurring(array(
					'price'			=> $plan['price'],
					'period'		=> $plan['interval_count'],
					'period'		=> $plan['interval_count'],
					'unit'			=> $plan['interval'],
					'setup'			=> $plan['setup_price'],
					'trial_period'	=> $plan['trial_count'],
					'trial_unit'	=> $plan['trial']
				));

			} else {
				$params['sub-total'] = number_format($summary['total'], 2);
				$params['shipping'] = number_format($summary['shipping'], 2);
				$params['handling'] = number_format($summary['handling'], 2);
				$params['total_weight'] = number_format($summary['weight'], 2);
				$params['total'] = number_format($summary['total'] + $summary['shipping'] + $summary['handling'], 2);
			}

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();

		} else {
			// no information available, show form
			$template = $this->loadTemplate($tag_params, 'buyer_information.xml');
			$template->registerTagHandler('cms:card_type', $this, 'tag_CardType');

			// get fixed country if set
			$fixed_country = '';
			if (isset($this->settings['fixed_country']))
				$fixed_country = $this->settings['fixed_country'];

			$params = array(
				'include_shipping'	=> $include_shipping,
				'fixed_country'		=> $fixed_country,
				'bad_fields'		=> $bad_fields,
				'recurring'			=> $recurring
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
		// show message
		$template = $this->loadTemplate($tag_params, 'checkout_message.xml');

		$params = array(
			'message'		=> $this->getLanguageConstant('message_checkout_completed'),
			'button_text'	=> $this->getLanguageConstant('button_take_me_back'),
			'button_action'	=> url_Make('', 'home'),
			'redirect'		=> false
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
			'button_action'	=> url_Make('', 'home'),
			'redirect'		=> false
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
	 * Handle drawing of supported credit cards.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CardType($tag_params, $children) {
		$template = $this->loadTemplate($tag_params, 'card_type.xml');

		foreach (CardType::$names as $id => $name) {
			$params = array(
				'id'	=> $id,
				'name'	=> $name
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
		$result = true;

		if (isset($this->settings['testing_mode']))
			$result = $this->settings['testing_mode'] == 1;

		return $result;
	}

	/**
	 * Format recurring plan description string.
	 *
	 * $params = array(
	 *			'price'			=> 2.99,
	 *			'period'		=> 1,
	 *			'unit'			=> RecurringPayment::DAY,
	 *			'setup'			=> 0.99,
	 *			'trial_period'	=> 0,
	 *			'trial_unit'	=> RecurringPayment::WEEK
	 *		);
	 *
	 * @param array $params
	 * @return string
	 */
	public function formatRecurring($params) {
		$units = array(
			RecurringPayment::DAY 	=> mb_strtolower($this->getLanguageConstant('cycle_day')),
			RecurringPayment::WEEK	=> mb_strtolower($this->getLanguageConstant('cycle_week')),
			RecurringPayment::MONTH	=> mb_strtolower($this->getLanguageConstant('cycle_month')),
			RecurringPayment::YEAR	=> mb_strtolower($this->getLanguageConstant('cycle_year'))
		);

		$template = $this->getLanguageConstant('recurring_description');
		$zero_word = $this->getLanguageConstant('recurring_period_zero');
		$currency = $this->getDefaultCurrency();

		$price = $params['price'].' '.$currency;
		$period = $params['period'].' '.$units[$params['unit']];
		$setup = $params['setup'] == 0 ? $zero_word : $params['setup'].' '.$currency;
		$trial_period = $params['trial_period'] == 0 ? $zero_word : $params['trial_period'].' '.$units[$params['trial_unit']];

		$result = str_replace(
			array('{price}', '{period}', '{setup}', '{trial_period}'),
			array($price, $period, $setup, $trial_period),
			$template
		);

		return $result;
	}
}
