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

// base classes
require_once('units/payment_method.php');
require_once('units/delivery_method.php');
require_once('units/promotion.php');
require_once('units/discount.php');

// data managers and handlers
require_once('units/item_handler.php');
require_once('units/category_handler.php');
require_once('units/currencies_handler.php');
require_once('units/item_sizes_handler.php');
require_once('units/item_size_values_manager.php');
require_once('units/transactions_manager.php');
require_once('units/transactions_handler.php');
require_once('units/warehouse_handler.php');
require_once('units/transaction_items_manager.php');
require_once('units/transaction_plans_manager.php');
require_once('units/transaction_promotions_manager.php');
require_once('units/recurring_payments_manager.php');
require_once('units/buyers_manager.php');
require_once('units/delivery_address_manager.php');
require_once('units/delivery_address_handler.php');
require_once('units/related_items_manager.php');
require_once('units/manufacturer_handler.php');
require_once('units/delivery_methods_handler.php');
require_once('units/coupons_handler.php');

// helper classes
require_once('units/token_manager.php');
require_once('units/delivery.php');
require_once('units/transaction.php');
require_once('units/token.php');

use Modules\Shop\Delivery as Delivery;
use Modules\Shop\Transaction as Transaction;
use Modules\Shop\Token as Token;

use Modules\Shop\TokenManager as TokenManager;
use Modules\Shop\Item\Handler as ShopItemHandler;


final class TransactionType {
	const SUBSCRIPTION = 0;
	const REGULAR = 1;
	const DONATION = 2;
	const DELAYED = 3;

	// language constant mapping
	public static $reverse = array(
		self::SUBSCRIPTION => 'type_subscription',
		self::REGULAR      => 'type_regular',
		self::DONATION     => 'type_donation',
		self::DELAYED      => 'type_delayed'
	);
}


final class TransactionStatus {
	const UNKNOWN = -1;
	const PENDING = 0;
	const DENIED = 1;
	const COMPLETED = 2;
	const CANCELED = 3;
	const SHIPPING = 4;
	const SHIPPED = 5;
	const LOST = 6;
	const DELIVERED = 7;
	const PROCESSED = 8;

	// language constant mapping
	public static $reverse = array(
		self::UNKNOWN   => 'status_unknown',
		self::PENDING   => 'status_pending',
		self::DENIED    => 'status_denied',
		self::COMPLETED => 'status_completed',
		self::CANCELED  => 'status_canceled',
		self::SHIPPING  => 'status_shipping',
		self::SHIPPED   => 'status_shipped',
		self::LOST      => 'status_lost',
		self::DELIVERED => 'status_delivered',
		self::PROCESSED => 'status_processed'
	);

	// list of statuses available for manual setting based on current transaction status
	public static $flow = array(
		TransactionType::REGULAR => array(
			self::PENDING	=> array(self::PENDING),
			self::CANCELED  => array(self::CANCELED),
			self::COMPLETED	=> array(self::COMPLETED, self::SHIPPING),
			self::SHIPPING	=> array(self::SHIPPING, self::SHIPPED),
			self::SHIPPED	=> array(self::LOST, self::DELIVERED)
		),
		TransactionType::DELAYED => array(
			self::PENDING	=> array(self::PENDING, self::PROCESSED, self::CANCELED),
			self::COMPLETED	=> array(self::COMPLETED, self::SHIPPING),
			self::SHIPPED	=> array(self::LOST, self::DELIVERED),
			self::CANCELED  => array(self::CANCELED),
			self::DENIED	=> array(self::DENIED, self::PROCESSED)
		)
	);
}


final class PackageType {
	const BOX_10 = 0;
	const BOX_20 = 1;
	const BOX = 2;
	const ENVELOPE = 3;
	const PAK = 4;
	const TUBE = 5;
	const USER_PACKAGING = 6;
}


final class UnitType {
	const METRIC = 0;
	const IMPERIAL = 1;
}


final class User {
	const EXISTING = 'log_in';
	const CREATE = 'sign_up';
	const GUEST = 'guest';
}


final class Stage {
	const INPUT = 'input';
	const SET_INFO = 'set-info';  // used to collect information, not handled
	const RESUME = 'resume';
	const CHECKOUT = 'checkout';
}


final class RecurringPayment {
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
	private $promotions;
	private $discounts;
	private $checkout_scripts = array();
	private $checkout_styles = array();
	private $search_params = array();

	private $excluded_properties = array(
		'size_value', 'color_value', 'count', 'price'
	);

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// create extension storage
		$this->payment_methods = array();
		$this->promotions = array();
		$this->discounts = array();

		// create events
		Events::register('shop', 'shopping-cart-changed');
		Events::register('shop', 'before-checkout');
		Events::register('shop', 'transaction-completed');
		Events::register('shop', 'transaction-canceled');

		// register recurring events
		foreach (RecurringPayment::$signals as $status => $signal_name)
			Events::register('shop', $signal_name);

		// connect to search module
		Events::connect('search', 'get-results', 'getSearchResults', $this);
		Events::connect('backend', 'user-create', 'handleUserCreate', $this);

		// register backend
		if (ModuleHandler::is_loaded('backend') && $section == 'backend') {
			$head_tag = head_tag::getInstance();
			$backend = backend::getInstance();

			// include collection scripts
			if (ModuleHandler::is_loaded('collection')) {
				$collection = collection::getInstance();
				$collection->includeScript(collection::PROPERTY_EDITOR);
			}

			// include local scripts
			if (ModuleHandler::is_loaded('head_tag')) {
				$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/multiple_images.js'), 'type'=>'text/javascript'));
				$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/backend.js'), 'type'=>'text/javascript'));
				$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/backend.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			}

			$shop_menu = new backend_MenuItem(
				$this->get_language_constant('menu_shop'),
				url_GetFromFilePath($this->path.'images/icon.svg'),
				'javascript:void(0);',
				5  // level
			);

			$shop_menu->addChild(null, new backend_MenuItem(
				$this->get_language_constant('menu_items'),
				url_GetFromFilePath($this->path.'images/items.svg'),
				window_Open( // on click open window
					'shop_items',
					650,
					$this->get_language_constant('title_manage_items'),
					true, true,
					backend_UrlMake($this->name, 'items')
				),
				5  // level
			));

			$recurring_plans_menu = new backend_MenuItem(
				$this->get_language_constant('menu_recurring_plans'),
				url_GetFromFilePath($this->path.'images/recurring_plans.svg'),
				'javascript: void(0);', 5
			);
			$shop_menu->addChild('shop_recurring_plans', $recurring_plans_menu);

			$import_menu = new backend_MenuItem(
				$this->get_language_constant('menu_import'),
				url_GetFromFilePath($this->path.'images/import.svg'),
				'javascript: void(0);', 5
			);
			$shop_menu->addChild('shop_import', $import_menu);

			$shop_menu->addSeparator(5);

			$shop_menu->addChild(null, new backend_MenuItem(
				$this->get_language_constant('menu_categories'),
				url_GetFromFilePath($this->path.'images/categories.svg'),
				window_Open( // on click open window
					'shop_categories',
					550,
					$this->get_language_constant('title_manage_categories'),
					true, true,
					backend_UrlMake($this->name, 'categories')
				),
				5  // level
			));

			$shop_menu->addChild(null, new backend_MenuItem(
				$this->get_language_constant('menu_item_sizes'),
				url_GetFromFilePath($this->path.'images/item_sizes.svg'),
				window_Open( // on click open window
					'shop_item_sizes',
					400,
					$this->get_language_constant('title_manage_item_sizes'),
					true, true,
					backend_UrlMake($this->name, 'sizes')
				),
				5  // level
			));

			$shop_menu->addChild(null, new backend_MenuItem(
				$this->get_language_constant('menu_manufacturers'),
				url_GetFromFilePath($this->path.'images/manufacturers.svg'),
				window_Open( // on click open window
					'shop_manufacturers',
					400,
					$this->get_language_constant('title_manufacturers'),
					true, true,
					backend_UrlMake($this->name, 'manufacturers')
				),
				5  // level
			));

			// delivery methods menu
			$delivery_menu = new backend_MenuItem(
				$this->get_language_constant('menu_delivery_methods'),
				url_GetFromFilePath($this->path.'images/delivery.svg'),
				'javascript: void(0);', 5
			);

			$shop_menu->addChild('shop_delivery_methods', $delivery_menu);

			$shop_menu->addSeparator(5);

			// special offers menu
			$special_offers = new backend_MenuItem(
				$this->get_language_constant('menu_special_offers'),
				url_GetFromFilePath($this->path.'images/special_offers.svg'),
				'javascript: void(0);', 5
			);

			$shop_menu->addChild('shop_special_offers', $special_offers);

			$shop_menu->addSeparator(5);

			// payment methods menu
			$methods_menu = new backend_MenuItem(
				$this->get_language_constant('menu_payment_methods'),
				url_GetFromFilePath($this->path.'images/payment_methods.svg'),
				'javascript: void(0);', 5
			);

			$shop_menu->addChild('shop_payment_methods', $methods_menu);

			$shop_menu->addChild(null, new backend_MenuItem(
				$this->get_language_constant('menu_currencies'),
				url_GetFromFilePath($this->path.'images/currencies.svg'),
				window_Open( // on click open window
					'shop_currencies',
					350,
					$this->get_language_constant('title_currencies'),
					true, true,
					backend_UrlMake($this->name, 'currencies')
				),
				5  // level
			));

			$shop_menu->addSeparator(5);

			$shop_menu->addChild(null, new backend_MenuItem(
				$this->get_language_constant('menu_transactions'),
				url_GetFromFilePath($this->path.'images/transactions.svg'),
				window_Open( // on click open window
					'shop_transactions',
					800,
					$this->get_language_constant('title_transactions'),
					true, true,
					backend_UrlMake($this->name, 'transactions')
				),
				5  // level
			));
			$shop_menu->addChild(null, new backend_MenuItem(
				$this->get_language_constant('menu_warehouses'),
				url_GetFromFilePath($this->path.'images/warehouse.svg'),
				window_Open( // on click open window
					'shop_warehouses',
					490,
					$this->get_language_constant('title_warehouses'),
					true, true,
					backend_UrlMake($this->name, 'warehouses')
				),
				5  // level
			));
			$shop_menu->addChild(null, new backend_MenuItem(
				$this->get_language_constant('menu_stocks'),
				url_GetFromFilePath($this->path.'images/stock.svg'),
				window_Open( // on click open window
					'shop_stocks',
					490,
					$this->get_language_constant('title_stocks'),
					true, true,
					backend_UrlMake($this->name, 'stocks')
				),
				5  // level
			));

			$shop_menu->addSeparator(5);
			$shop_menu->addChild('', new backend_MenuItem(
				$this->get_language_constant('menu_settings'),
				url_GetFromFilePath($this->path.'images/settings.svg'),

				window_Open( // on click open window
					'shop_settings',
					400,
					$this->get_language_constant('title_settings'),
					true, true,
					backend_UrlMake($this->name, 'settings')
				),
				$level=5
			));

			$backend->addMenu($this->name, $shop_menu);

			// create custom handlers
			$coupons_handler = \Modules\Shop\Promotion\CouponHandler::getInstance($this);
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
	 * @param array $module_list
	 * @param string $query
	 * @param integer $threshold
	 * @return array
	 */
	public function getSearchResults($module_list, $query, $threshold) {
		global $language;

		// make sure shop is in list of modules requested
		if (!in_array($this->name, $module_list))
			return array();

		// don't bother searching for empty query string
		if (empty($query))
			return array();

		// initialize managers and data
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
			$category = fix_chars($this->search_params['category']);
			$item_ids = array();

			if (!is_numeric($category)) {
				$category_manager = ShopCategoryManager::getInstance();
				$raw_category = $category_manager->get_single_item(
					array('id'),
					array('text_id' => $category)
				);

				if (is_object($raw_category))
					$category = $raw_category->id; else
						$category = -1;

			} else {
				$category = fix_id($category);
			}

			// get list of item ids
			$membership_list = $membership_manager->get_items(
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
		$items = $manager->get_items(
			array(
				'id',
				'name',
				'description'
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
	 * Handle creating system user.
	 *
	 * @param object $user
	 */
	public function handleUserCreate($user) {
		$manager = ShopBuyersManager::getInstance();

		// get user data
		$data = array(
			'first_name'	=> $user->first_name,
			'last_name'		=> $user->last_name,
			'email'			=> $user->email,
			'guest'			=> 0,
			'system_user'	=> $user->id,
			'agreed'		=> $user->agreed
		);

		// create new buyer
		$manager->insert_item($data);
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transfer_control($params, $children) {
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

			case 'show_property':
				$handler = \Modules\Shop\Property\Handler::getInstance($this);
				$handler->tag_Property($params, $children);
				break;

			case 'show_property_list':
				$handler = \Modules\Shop\Property\Handler::getInstance($this);
				$handler->tag_PropertyList($params, $children);
				break;

			case 'show_manufacturer':
				$handler = ShopManufacturerHandler::getInstance($this);
				$handler->tag_Manufacturer($params, $children);
				break;

			case 'show_manufacturer_list':
				$handler = ShopManufacturerHandler::getInstance($this);
				$handler->tag_ManufacturerList($params, $children);
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

			case 'checkout-completed':
				$this->showCheckoutCompleted();
				break;

			case 'checkout-canceled':
				$this->showCheckoutCanceled();
				break;

			case 'show_checkout_items':
				$this->tag_CheckoutItems($params, $children);
				break;

			case 'set_item_as_cart':
				$this->setItemAsCartFromParams($params, $children);
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

			case 'set_transaction_type':
				$this->setTransactionType($params, $children);
				break;

			case 'set_terms':
				$this->setTermsLink($params, $children);
				break;

			case 'include_scripts':
				$this->includeScripts();
				break;

			case 'include_cart_scripts':
				$this->includeCartScripts();
				break;

			case 'include_redirect_script':
				$this->includeRedirectScript();
				break;

			case 'json_get_item':
				$handler = ShopItemHandler::getInstance($this);
				$handler->json_GetItem();
				break;

			case 'json_get_currency':
				$this->json_GetCurrency();
				break;

			case 'json_get_conversion_rate':
				$this->json_GetConversionRate();
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

			case 'json_set_item_as_cart':
				$this->json_SetItemAsCart();
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

			case 'json_get_delivery_estimate':
				$this->json_GetDeliveryEstimate();
				break;

			case 'json_set_cart_from_transaction':
				$this->json_SetCartFromTransaction();
				break;

			case 'json_get_delivery_method_interface':
				$this->json_GetDeliveryMethodInterface();
				break;

			case 'json_get_property':
				$handler = \Modules\Shop\Property\Handler::getInstance($this);
				$handler->json_GetProperty();
				break;

			case 'json_get_property_list':
				$handler = \Modules\Shop\Property\Handler::getInstance($this);
				$handler->json_GetPropertyList();
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
				$handler->transfer_control($params, $children);
				break;

			case 'currencies':
				$handler = ShopCurrenciesHandler::getInstance($this);
				$handler->transfer_control($params, $children);
				break;

			case 'categories':
				$handler = ShopCategoryHandler::getInstance($this);
				$handler->transfer_control($params, $children);
				break;

			case 'coupons':
				$handler = \Modules\Shop\Promotion\CouponHandler::getInstance($this);
				$handler->transfer_control($params, $children);
				break;

			case 'sizes':
				$handler = ShopItemSizesHandler::getInstance($this);
				$handler->transfer_control($params, $children);
				break;

			case 'transactions':
				$handler = ShopTransactionsHandler::getInstance($this);
				$handler->transfer_control($params, $children);
				break;

			case 'manufacturers':
				$handler = ShopManufacturerHandler::getInstance($this);
				$handler->transfer_control($params, $children);
				break;

			case 'special_offers':
				break;

			case 'warehouses':
				$handler = ShopWarehouseHandler::getInstance($this);
				$handler->transfer_control($params, $children);
				break;

			case 'stocks':
				break;

			case 'settings':
				$this->showSettings();
				break;

			case 'settings_save':
				$this->save_settings();
				break;

			default:
				break;
			}
		}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function on_init() {
		global $db;

		$list = Language::getLanguages(false);

		// set shop in testing mode by default
		$this->save_setting('testing_mode', 1);
		$this->save_setting('send_copy', 0);
		$this->save_setting('default_account_option', User::GUEST);

		// create shop items table
		$sql = "
			CREATE TABLE `shop_items` (
			`id` int NOT NULL AUTO_INCREMENT,
			`uid` VARCHAR(64) NOT NULL,";

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
			`price` DECIMAL(10,2) NOT NULL,
			`discount` DECIMAL(5,2) NOT NULL,
			`tax` DECIMAL(5,2) NOT NULL,
			`weight` DECIMAL(10,4) NOT NULL,
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

		// create shop item membership table
		$sql = "
			CREATE TABLE `shop_item_membership` (
			`category` INT NOT NULL,
			`item` INT NOT NULL,
			KEY `category` (`category`),
			KEY `item` (`item`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop item properties table
		$sql = "
			CREATE TABLE `shop_item_properties` (
			`id` INT NOT NULL AUTO_INCREMENT,
			`item` INT NOT NULL,
			`text_id` VARCHAR(32) NOT NULL,
			`type` VARCHAR(32) NOT NULL,";

		foreach($list as $language)
			$sql .= "`name_{$language}` VARCHAR(255) NOT NULL DEFAULT '',";

		$sql .= "
			`value` TEXT NOT NULL,
			PRIMARY KEY ( `id` ),
			KEY `item` (`item`),
			KEY `text_id` (`text_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create table for related shop items
		$sql = "
			CREATE TABLE `shop_related_items` (
			`item` INT NOT NULL,
			`related` INT NOT NULL,
			KEY `item` (`item`,`related`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;";
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
		$sql = "CREATE TABLE `shop_buyers` (
			`id` INT NOT NULL AUTO_INCREMENT,
			`first_name` varchar(64) NOT NULL,
			`last_name` varchar(64) NOT NULL,
			`email` varchar(127) NOT NULL,
			`phone` varchar(200) NOT NULL,
			`guest` boolean NOT NULL DEFAULT '0',
			`system_user` int NULL,
			`agreed` boolean NOT NULL DEFAULT '0',
			`promotions` boolean NOT NULL DEFAULT '0',
			`uid` varchar(50) NOT NULL,
			PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop buyer addresses table
		$sql = "CREATE TABLE `shop_delivery_address` (
			`id` INT NOT NULL AUTO_INCREMENT,
			`buyer` INT NOT NULL,
			`name` varchar(128) NOT NULL,
			`street` varchar(200) NOT NULL,
			`street2` varchar(200) NOT NULL,
			`email` varchar(127) NOT NULL,
			`phone` varchar(200) NOT NULL,
			`city` varchar(40) NOT NULL,
			`zip` varchar(20) NOT NULL,
			`state` varchar(40) NOT NULL,
			`country` varchar(64) NOT NULL,
			`access_code` varchar(100) NOT NULL,
			PRIMARY KEY (`id`),
			KEY `buyer` (`buyer`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop transactions table
		$sql = "CREATE TABLE `shop_transactions` (
			`id` INT NOT NULL AUTO_INCREMENT,
			`buyer` INT NOT NULL,
			`address` INT NOT NULL,
			`uid` varchar(30) NOT NULL,
			`type` smallint(6) NOT NULL,
			`status` smallint(6) NOT NULL,
			`currency` INT NOT NULL,
			`handling` decimal(8,2) NOT NULL,
			`shipping` decimal(8,2) NOT NULL,
			`weight` decimal(4,2) NOT NULL,
			`payment_method` varchar(255) NOT NULL,
			`payment_token` int NOT NULL DEFAULT '0',
			`delivery_method` varchar(255) NOT NULL,
			`delivery_type` varchar(255) NOT NULL,
			`remark` text NOT NULL,
			`remote_id` varchar(255) NOT NULL,
			`total` decimal(8,2) NOT NULL,
			`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			KEY `buyer` (`buyer`),
			KEY `address` (`address`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop transaction items table
		$sql = "CREATE TABLE `shop_transaction_items` (
			`id` int NOT NULL AUTO_INCREMENT,
			`transaction` int NOT NULL,
			`item` int NOT NULL,
			`price` decimal(8,2) NOT NULL,
			`tax` decimal(8,2) NOT NULL,
			`amount` int NOT NULL,
			`description` varchar(500) NOT NULL,
			PRIMARY KEY (`id`),
			KEY `transaction` (`transaction`),
			KEY `item` (`item`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop payment tokens table
		$sql = "CREATE TABLE `shop_payment_tokens` (
			`id` int NOT NULL AUTO_INCREMENT,
			`payment_method` varchar(64) NOT NULL,
			`buyer` int NOT NULL,
			`name` varchar(50) NOT NULL,
			`token` varchar(200) NOT NULL,
			`expires` boolean NOT NULL DEFAULT '0',
			`expiration_month` int NOT NULL,
			`expiration_year` int NOT NULL,
			PRIMARY KEY (`id`),
			KEY `index_by_name` (`payment_method`, `buyer`, `name`),
			KEY `index_by_buyer` (`payment_method`, `buyer`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop transaction plans table
		$sql = "CREATE TABLE `shop_transaction_plans` (
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

		// create shop transaction promotions table
		$sql = "CREATE TABLE `shop_transaction_promotions` (
			`id` int NOT NULL AUTO_INCREMENT,
			`transaction` int NOT NULL,
			`promotion` varchar(64) NOT NULL,
			`discount` varchar(64) NOT NULL,
			PRIMARY KEY (`id`),
			KEY `transaction` (`transaction`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create show recurring payments table
		$sql = "CREATE TABLE `shop_recurring_payments` (
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
		$sql = "CREATE TABLE `shop_warehouse` (
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
		$sql = "CREATE TABLE `shop_stock` (
			`id` int NOT NULL AUTO_INCREMENT,
			`item` int NOT NULL,
			`size` int DEFAULT NULL,
			`amount` int NOT NULL,
			PRIMARY KEY (`id`),
			KEY `item` (`item`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create shop manufacturers table
		$sql = "CREATE TABLE `shop_manufacturers` (
			`id` int NOT NULL AUTO_INCREMENT,";

		foreach($list as $language)
			$sql .= "`name_{$language}` VARCHAR(255) NOT NULL DEFAULT '',";

		$sql .= " `web_site` varchar(255) NOT NULL,
			`logo` int NOT NULL,
			PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		// create coupons storage
		$sql = "CREATE TABLE `shop_coupons` (
			`id` int NOT NULL AUTO_INCREMENT,
			`text_id` varchar(64) NOT NULL,";

		foreach($list as $language)
			$sql .= "`name_{$language}` VARCHAR(255) NOT NULL DEFAULT '',";

		$sql .= "`has_limit` boolean NOT NULL DEFAULT '0',
			`has_timeout` boolean NOT NULL DEFAULT '0',
			`limit` int NOT NULL DEFAULT '0',
			`timeout` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			KEY `index_by_text_id` (`text_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		$sql = "CREATE TABLE `shop_coupon_codes` (
			`id` int NOT NULL AUTO_INCREMENT,
			`coupon` int NOT NULL,
			`code` varchar(64) NOT NULL,
			`times_used` int NOT NULL DEFAULT '0',
			`timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`discount` varchar(64) NOT NULL,
			PRIMARY KEY (`id`),
			KEY `index_by_timestamp` (`timestamp`),
			KEY `index_by_code` (`code`),
			KEY `index_by_coupon` (`coupon`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function on_disable() {
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
			'shop_transaction_promotions',
			'shop_recurring_payments',
			'shop_warehouse',
			'shop_stock',
			'shop_related_items',
			'shop_manufacturers',
			'shop_payment_tokens',
			'shop_item_properties',
			'shop_coupons'
		);

		$db->drop_tables($tables);
	}

	/**
	 * Method used by payment providers to register with main module.
	 *
	 * @param string $name
	 * @param object $method
	 */
	public function registerPaymentMethod($name, &$method) {
		if (!array_key_exists($name, $this->payment_methods))
			$this->payment_methods[$name] = $method; else
			throw new Exception("Payment method '{$name}' is already registered with the system.");
	}

	/**
	 * Method used with promotions to register with main module.
	 *
	 * @param string $name
	 * @param object $promotion
	 */
	public function registerPromotion($name, &$promotion) {
		if (!array_key_exists($name, $this->promotions))
			$this->promotions[$name] = $promotion; else
			throw new Exception("Promotion '{$name}' is already registered with the system.");
	}

	/**
	 * Register discount to be used with promotions.
	 *
	 * @param string $name
	 * @param object $discount
	 */
	public function registerDiscount($name, &$discount) {
		if (!array_key_exists($name, $this->discounts))
			$this->discounts[$name] = $discount; else
			throw new Exception("Discount '{$name}' is already registered with the system.");
	}

	/**
	 * Add script to be included with other checkout scripts.
	 *
	 * @param string $url
	 */
	public function addCheckoutScript($url) {
		if (!in_array($url, $this->checkout_scripts))
			$this->checkout_scripts[] = $url;
	}

	/**
	 * Add checkout style to be included with other checkout styles.
	 *
	 * @param string $url
	 */
	public function addCheckoutStyle($url) {
		if (!in_array($url, $this->checkout_styles))
			$this->checkout_styles[] = $url;
	}

	/**
	 * Include buyer information and checkout form scripts.
	 */
	public function includeScripts() {
		if (!ModuleHandler::is_loaded('head_tag') || !ModuleHandler::is_loaded('collection'))
			return;

		$head_tag = head_tag::getInstance();
		$collection = collection::getInstance();
		$css_file = _DESKTOP_VERSION ? 'checkout.css' : 'checkout_mobile.css';

		$collection->includeScript(collection::DIALOG);
		$collection->includeScript(collection::PAGE_CONTROL);
		$collection->includeScript(collection::COMMUNICATOR);
		$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/'.$css_file), 'rel'=>'stylesheet', 'type'=>'text/css'));
		$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/checkout.js'), 'type'=>'text/javascript'));

		// add custom scripts
		if (count($this->checkout_scripts) > 0)
			foreach ($this->checkout_scripts as $script_url)
				$head_tag->addTag('script', array( 'src' => $script_url, 'type' => 'text/javascript'));

		// add custom styles
		if (count($this->checkout_styles) > 0)
			foreach ($this->checkout_styles as $style_url)
				$head_tag->addTag('link', array('href' => $style_url, 'rel' => 'stylesheet', 'type' => 'text/css'));
	}

	/**
	 * Include shopping cart scripts.
	 */
	public function includeCartScripts() {
		if (!ModuleHandler::is_loaded('head_tag') || !ModuleHandler::is_loaded('collection'))
			return;

		$head_tag = head_tag::getInstance();
		$collection = collection::getInstance();

		$collection->includeScript(collection::COMMUNICATOR);
		$head_tag->addTag('script', array('src' => url_GetFromFilePath($this->path.'include/cart.js'), 'type'=>'text/javascript'));
	}

	/**
 	 * Include script that makes sure page is not running in iframe.
	 */
	public function includeRedirectScript() {
		if (!ModuleHandler::is_loaded('head_tag'))
			return;

		$head_tag = head_tag::getInstance();
		$head_tag->addTag('script', array('src' => url_GetFromFilePath($this->path.'include/redirect.js'), 'type'=>'text/javascript'));
	}

	/**
	 * Show shop configuration form
	 */
	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('cms:account_options', $this, 'tag_AccountOptions');

		$params = array(
			'form_action'	=> backend_UrlMake($this->name, 'settings_save'),
			'cancel_action'	=> window_Close('shop_settings')
		);

		if (ModuleHandler::is_loaded('contact_form')) {
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
	private function save_settings() {
		// save new settings
		$regular_template = fix_chars($_REQUEST['regular_template']);
		$recurring_template = fix_chars($_REQUEST['recurring_template']);
		$delayed_template = fix_chars($_REQUEST['delayed_template']);
		$shop_location = fix_chars($_REQUEST['shop_location']);
		$fixed_country = fix_chars($_REQUEST['fixed_country']);
		$testing_mode = fix_id($_REQUEST['testing_mode']);
		$send_copy = fix_id($_REQUEST['send_copy']);
		$default_account_option = escape_chars($_REQUEST['default_account_option']);

		$this->save_setting('regular_template', $regular_template);
		$this->save_setting('recurring_template', $recurring_template);
		$this->save_setting('delayed_template', $delayed_template);
		$this->save_setting('shop_location', $shop_location);
		$this->save_setting('fixed_country', $fixed_country);
		$this->save_setting('testing_mode', $testing_mode);
		$this->save_setting('send_copy', $send_copy);
		$this->save_setting('default_account_option', $default_account_option);

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
			'message'	=> $this->get_language_constant('message_settings_saved'),
			'button'	=> $this->get_language_constant('close'),
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
	 * Set item as cart content from provided template params.
	 *
	 * @param array $params
	 * @param array $children
	 */
	private function setItemAsCartFromParams($params, $children) {
		$uid = isset($params['uid']) ? escape_chars($params['uid']) : null;
		$count = isset($params['count']) ? escape_chars($params['count']) : 1;
		$variation_id = isset($params['variation_id']) ? escape_chars($params['variation_id']) : null;

		// set cart content
		$this->setItemAsCart($uid, $count, $variation_id);
	}

	/**
	 * Set shopping cart to contain only one item.
	 *
	 * @param string $uid
	 * @param integer $count
	 * @param string $variation_id
	 * @return boolean
	 */
	private function setItemAsCart($uid, $count, $variation_id=null) {
		$cart = array();
		$result = false;
		$manager = ShopItemManager::getInstance();

		// make sure we have variation id
		if (is_null($variation_id))
			$variation_id = $this->generateVariationId($uid, array());

		// check if item exists in database to avoid poluting shopping cart
		$item = $manager->get_single_item(array('id', 'price'), array('uid' => $uid));

		// make new content of shopping cart
		if (is_object($item) && $count > 0) {
			$cart[$uid] = array(
				'uid'			=> $uid,
				'quantity'		=> $count,
				'variations'	=> array()
			);
			$cart[$uid]['variations'][$variation_id] = array(
					'count' => $count,
					'price' => $item->price
				);
			$result = true;
		}

		// assign new cart
		$_SESSION['shopping_cart'] = $cart;

		// notify all the listeners about change
		Events::trigger('shop', 'shopping-cart-changed');

		return $result;
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
					$item = $manager->get_single_item(array('id'), array('uid' => $uid));

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
			$transaction = $transaction_manager->get_single_item(
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
	 * Set current transaction type.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	private function setTransactionType($tag_params, $children) {
		$type = TransactionType::REGULAR;
		if (isset($tag_params['type']) && array_key_exists($tag_params['type'], TransactionType::$reverse))
			$type = fix_id($tag_params['type']);

		$_SESSION['transaction_type'] = $type;
	}

	/**
	 * Set terms of use link to be displayed in the shop checkout
	 * page. If link is not specified, no checkbox will appear on checkout.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	private function setTermsLink($tag_params, $children) {
		if (isset($tag_params['link']))
			$_SESSION['buyer_terms_link'] = fix_chars($tag_params['link']);
	}

	/**
	 * Get transaction type.
	 *
	 * @return integer
	 */
	public function getTransactionType() {
		return isset($_SESSION['transaction_type']) ? $_SESSION['transaction_type'] : TransactionType::REGULAR;
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
		$transaction = $transaction_manager->get_single_item(
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

		$plan = $plan_manager->get_single_item(
			$plan_manager->get_field_names(),
			array('transaction' => $transaction->id)
		);

		// get last payment
		$last_payment = $recurring_manager->get_single_item(
			$recurring_manager->get_field_names(),
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
		$transaction = $manager->get_single_item(
			$manager->get_field_names(),
			array('uid' => $transaction_id)
		);

		// set status of transaction
		if (is_object($transaction)) {
			$manager->update_items(
				array('status' => $status),
				array('id' => $transaction->id)
			);
			$transaction->status = $status;
			$result = true;

			// get template based on transaction type
			switch ($transaction->type) {
				case TransactionType::SUBSCRIPTION:
					$template_name = $this->settings['recurring_template'];
					break;

				case TransactionType::DELAYED:
					$template_name = $this->settings['delayed_template'];
					break;

				case TransactionType::REGULAR:
				default:
					$template_name = $this->settings['regular_template'];
					break;
			}

			// whether we should send email notification
			$send_email = true;

			// trigger event
			switch ($status) {
				case TransactionStatus::COMPLETED:
					Events::trigger('shop', 'transaction-completed', $transaction);
					unset($_SESSION['transaction']);
					break;

				case TransactionStatus::PROCESSED:
					// get payment method
					if (!array_key_exists($transaction->payment_method, $this->payment_methods)) {
						trigger_error('Unable to update transaction status. Missing payment method!', E_USER_NOTICE);
						break;
					}

					// charge transaction
					$payment_method = $this->payment_methods[$transaction->payment_method];
					$payment_method->charge_transaction($transaction);

					// we don't send emails for delayed transactions
					$send_email = $transaction->type != TransactionType::DELAYED;
					break;

				case TransactionStatus::CANCELED:
					Events::trigger('shop', 'transaction-canceled', $transaction);
					break;

				case TransactionStatus::UNKNOWN:
				case TransactionStatus::PENDING:
					// we don't send emails for delayed transactions
					$send_email = $transaction->type != TransactionType::DELAYED;
					break;
			}

			// send notification email
			if ($send_email)
				$this->sendTransactionMail($transaction, $template_name);
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
		$transaction = $manager->get_single_item($manager->get_field_names(), $conditions);

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
		$plan = $plan_manager->get_single_item(
			$plan_manager->get_field_names(),
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

		$manager->insert_item($data);
		$payment_id = $manager->get_inserted_id();
		$result = true;

		// get newly inserted data
		$payment = $manager->get_single_item(
			$manager->get_field_names(),
			array('id' => $payment_id)
		);

		// get transaction and buyer
		$transaction = $transaction_manager->get_single_item(
			$transaction_manager->get_field_names(),
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
		$template->registerTagHandler('cms:checkout_form', $this, 'tag_CheckoutForm');

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
		$template->registerTagHandler('cms:completed_message', $this, 'tag_CompletedMessage');

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
			'message'		=> $this->get_language_constant('message_checkout_redirect'),
			'button_text'	=> $this->get_language_constant('button_take_me_back'),
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
		$template->registerTagHandler('cms:canceled_message', $this, 'tag_CanceledMessage');

		$template->restoreXML();
		$template->parse();
	}

	/**
	 * Return default currency using JSON object
	 */
	private function json_GetCurrency() {
		print json_encode(self::getDefaultCurrency());
	}

	/**
	 * Return conversion rate from two currencies.
	 */
	private function json_GetConversionRate() {
		$from = fix_chars($_REQUEST['from']);
		$to = fix_chars($_REQUEST['to']);
		$rate = 0;

		$url = "http://rate-exchange.appspot.com/currency?from=$from&to=$to";
		$data = json_decode(file_get_contents($url));
		$rate = $data->rate;

		print json_encode($rate);
	}

	/**
	 * Set recurring plan.
	 */
	public function json_SetRecurringPlan() {
		$recurring_plan = fix_chars($_REQUEST['plan']);
		$_SESSION['recurring_plan'] = $recurring_plan;
	}

	/**
	 * Returns custom delivery method interface.
	 */
	private function json_GetDeliveryMethodInterface() {
		$method = isset($_REQUEST['method']) ? escape_chars($_REQUEST['method']) : null;

		// make sure method is specified
		if (is_null($method))
			return;

		$delivery_method = Delivery::get_method($method);
		if ($delivery_method->hasCustomInterface())
			print $delivery_method->getInterface();
	}

	/**
	 * Get estimated price of delivery and delivery types if method provides
	 * them for specified parameters. Scripts calling this method need to provide
	 * the following fields:
	 *
	 *	 street, street2, city, zip_code, state, country
	 *
	 * System will try to select the closest warehouse and give estimates
	 * based on that address.
	 */
	private function json_GetDeliveryEstimate() {
		$result = array(
				'error'           => false,
				'delivery_prices' => null,
				'shipping'        => 0,
				'handling'        => 0
			);

		// get delivery method
		$method_name = isset($_REQUEST['method']) ? escape_chars($_REQUEST['method']) : null;
		$type = isset($_REQUEST['type']) ? escape_chars($_REQUEST['type']) : null;

		// get recipient from user specified information
		$recipient = array(
			'street'   => array(
					isset($_REQUEST['street']) ? escape_chars($_REQUEST['street']) : '',
					isset($_REQUEST['street2']) ? escape_chars($_REQUEST['street2']) : '',
				),
			'city'     => isset($_REQUEST['city']) ? escape_chars($_REQUEST['city']) : '',
			'zip_code' => isset($_REQUEST['zip']) ? escape_chars($_REQUEST['zip']) : '',
			'state'    => isset($_REQUEST['state']) ? escape_chars($_REQUEST['state']) : '',
			'country'  => isset($_REQUEST['country']) ? escape_chars($_REQUEST['country']) : ''
		);

		$shipping = $this->getDeliveryEstimate($recipient, $method_name, $type);

		if (!is_null($shipping['price']))
			$result['shipping'] = $shipping['price'];

		if (!is_null($shipping['list']))
			$result['delivery_prices'] = $shipping['list'];

		$result['error'] = is_null($shipping['list']) && is_null($shipping['price']);

		print json_encode($result);
	}

	/**
	 * Return user information if email and password are correct.
	 */
	private function json_GetAccountInfo() {
		// get managers
		$buyer_manager = ShopBuyersManager::getInstance();
		$delivery_address_manager = ShopDeliveryAddressManager::getInstance();
		$transaction_manager = ShopTransactionsManager::getInstance();

		// get buyer from specified email
		if ($_SESSION['logged'])
			$buyer = $buyer_manager->get_single_item(
				$buyer_manager->get_field_names(),
				array(
					'guest'			=> 0,
					'system_user'	=> $_SESSION['uid']
				)
			);

		if (is_object($buyer)) {
			$result = array(
				'information'          => array(),
				'delivery_addresses'   => array(),
				'last_payment_method'  => '',
				'last_delivery_method' => ''
			);

			// populate user information
			$result['information'] = array(
				'first_name' => $buyer->first_name,
				'last_name'  => $buyer->last_name,
				'email'      => $buyer->email,
				'phone'      => $buyer->phone,
				'uid'        => $buyer->uid
			);

			// populate delivery addresses
			$address_list = $delivery_address_manager->get_items(
				$delivery_address_manager->get_field_names(),
				array('buyer' => $buyer->id)
			);

			if (count($address_list) > 0)
				foreach ($address_list as $address) {
					$result['delivery_addresses'][] = array(
						'id'          => $address->id,
						'name'        => $address->name,
						'street'      => $address->street,
						'street2'     => $address->street2,
						'email'       => $address->email,
						'phone'       => $address->phone,
						'city'        => $address->city,
						'zip'         => $address->zip,
						'state'       => $address->state,
						'country'     => $address->country,
						'access_code' => $address->access_code
					);
				}

			// get last used payment and delivery method
			$transaction = $transaction_manager->get_single_item(
				$transaction_manager->get_field_names(),
				array('buyer' => $buyer->id),
				array('timestamp'), false
			);

			if (is_object($transaction)) {
				$result['last_payment_method'] = $transaction->payment_method;
				$result['last_delivery_method'] = $transaction->delivery_method;
			}

			print json_encode($result);
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
			$account = $manager->get_single_item(array('id'), array('email' => $email));
			$result['account_exists'] = is_object($account);
			$result['message'] = $this->get_language_constant('message_error_account_exists');
		}

		print json_encode($result);
	}

	/**
	 * Show shopping card in form of JSON object
	 */
	private function json_ShowCart() {
		$manager = ShopItemManager::getInstance();
		$values_manager = ShopItemSizeValuesManager::getInstance();
		$gallery = ModuleHandler::is_loaded('gallery') ? gallery::getInstance() : null;
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();

		$result = array();

		// get shopping cart from session
		$result['cart'] = array();
		$result['size_values'] = array();
		$result['count'] = count($result['cart']);
		$result['currency'] = self::getDefaultCurrency();

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
		$items = $manager->get_items($manager->get_field_names(), array('uid' => $ids));
		$values = $values_manager->get_items($values_manager->get_field_names(), array());

		if (count($items) > 0)
			foreach ($items as $item) {
				// get item image url
				$thumbnail_url = ModuleHandler::is_loaded('gallery') ? gallery::getGroupThumbnailById($item->gallery) : '';

				$uid = $item->uid;

				if (array_key_exists($uid, $cart) && count($cart[$uid]['variations']) > 0)
					foreach ($cart[$uid]['variations'] as $variation_id => $properties) {
						$new_properties = $properties;
						unset($new_properties['count']);

						$result['cart'][] = array(
							'name'            => $item->name,
							'weight'          => $item->weight,
							'price'           => $properties['price'],
							'discount'        => $item->discount,
							'discount_price'  => $item->discount ? $properties['price'] * ((100 - $item->discount) / 100) : $properties['price'],
							'tax'             => $item->tax,
							'image'           => $thumbnail_url,
							'uid'             => $item->uid,
							'variation_id'    => $variation_id,
							'count'           => $properties['count'],
							'properties'      => unfix_chars($new_properties),
							'size_definition' => $item->size_definition
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
	 * Set single item as shopping cart content.
	 */
	private function json_SetItemAsCart() {
		$uid = fix_chars($_REQUEST['uid']);
		$count = isset($_REQUEST['count']) ? fix_id($_REQUEST['count']) : 1;
		$variation_id = isset($_REQUEST['variation_id']) ? fix_chars($_REQUEST['variation_id']) : null;

		// set cart content
		$result = $this->setItemAsCart($uid, $count, $variation_id);

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
	 * Set shopping cart from previous transaction.
	 */
	private function json_SetCartFromTransaction() {
		$uid = fix_chars($_REQUEST['uid']);
		$item_manager = ShopItemManager::getInstance();
		$transaction_manager = ShopTransactionsManager::getInstance();
		$transaction_item_manager = ShopTransactionItemsManager::getInstance();

		// find specified transaction
		$transaction = $transaction_manager->get_single_item(
				array('id'),
				array(
					'uid'	=> $uid,
					'type'	=> array(
						TransactionType::REGULAR,
						TransactionType::DELAYED
					)
				)
			);

		// no transaction was found, show current cart and return
		if (!is_object($transaction)) {
			$this->json_ShowCart();
			return;
		}

		// get transaction items
		$items = $transaction_item_manager->get_items(
			$transaction_item_manager->get_field_names(),
			array('transaction' => $transaction->id)
		);

		// no items in this transaction, show current cart and return
		if (count($items) == 0) {
			$this->json_ShowCart();
			return;
		}

		// parse transaction item list
		$id_list = array();
		$amount_list = array();
		$description_list = array();
		foreach ($items as $item) {
			$id_list[] = $item->item;
			$amount_list[$item->item] = $item->amount;
			$description_list[$item->item] = $item->description;
		}

		// get active shop items
		$items = $item_manager->get_items(
			$item_manager->get_field_names(),
			array(
				'deleted'	=> 0,
				'visible'	=> 1,
				'id'		=> $id_list
			)
		);

		// no visible and active items, show current cart and return
		if (count($items) == 0) {
			$this->json_ShowCart();
			return;
		}

		// prepare new items
		$cart = array();
		foreach ($items as $item) {
			$properties = unserialize($description_list[$item->id]);
			$variation_id = $this->generateVariationId($item->uid, $properties);

			if (array_key_exists($item->uid, $cart)) {
				$cart[$item->uid]['quantity'] += $amount_list[$item->id];

			} else {
				$cart[$item->uid] = array(
						'uid'			=> $item->uid,
						'quantity'		=> $amount_list[$item->id],
						'variations'	=> array()
					);
			}

			$cart[$item->uid]['variations'][$variation_id] = $properties;
			$cart[$item->uid]['variations'][$variation_id]['count'] = $amount_list[$item->id];
		}

		// assign new cart to session
		$_SESSION['shopping_cart'] = $cart;

		// trigger an event
		Events::trigger('shop', 'shopping-cart-changed');

		// return response
		$this->json_ShowCart();
	}

	/**
	 * Add item to shopping cart using JSON request
	 */
	private function json_AddItemToCart() {
		$uid = fix_chars($_REQUEST['uid']);
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$price_property = isset($_REQUEST['price_property']) ? fix_chars($_REQUEST['price_property']) : null;
		$properties = isset($_REQUEST['properties']) ? fix_chars($_REQUEST['properties']) : array();

		// get variation id
		if (isset($_REQUEST['variation_id']))
			$variation_id = fix_chars($_REQUEST['variation_id']); else
			$variation_id = $this->generateVariationId($uid, $properties);

		// get thumbnail options
		$thumbnail_size = isset($_REQUEST['thumbnail_size']) ? fix_id($_REQUEST['thumbnail_size']) : 100;
		$thumbnail_constraint = isset($_REQUEST['thumbnail_constraint']) ? fix_id($_REQUEST['thumbnail_constraint']) : Thumbnail::CONSTRAIN_BOTH;

		// try to get item from database
		$manager = ShopItemManager::getInstance();
		$item = $manager->get_single_item($manager->get_field_names(), array('uid' => $uid));

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

			// get item image url
			$thumbnail_url = null;
			if (ModuleHandler::is_loaded('gallery'))
				$thumbnail_url = gallery::getGroupThumbnailById(
										$item->gallery,
										null,
										$thumbnail_size,
										$thumbnail_constraint
									);

			// get item price
			if (!is_null($price_property)) {
				$properties_manager = \Modules\Shop\Property\Manager::getInstance();
				$property = $properties_manager->get_single_item(
						array('value'),
						array(
							'item'    => $item->id,
							'text_id' => $price_property
						));

				if (is_object($property))
					$item_price = floatval(unserialize($property->value)); else
					$item_price = $item->price;  // fallback, better charge regular than nothing

			} else {
				$item_price = $item->price;
			}

			// create variation and configure its values
			foreach ($this->excluded_properties as $key)
				if (isset($properties[$key]))
					unset($properties[$key]);

			if (!array_key_exists($variation_id, $cart[$uid]['variations'])) {
				$cart[$uid]['variations'][$variation_id] = $properties;
				$cart[$uid]['variations'][$variation_id]['count'] = 0;
			}

			$cart[$uid]['variations'][$variation_id]['count'] += 1;
			$cart[$uid]['variations'][$variation_id]['price'] = $item_price;

			// prepare result
			$result = array(
				'name'            => $item->name,
				'weight'          => $item->weight,
				'price'           => $item_price,
				'discount'        => $item->discount,
				'discount_price'  => $item->discount ? $item_price * ((100 - $item->discount) / 100) : $item_price,
				'tax'             => $item->tax,
				'size_definition' => $item->size_definition,
				'image'           => $thumbnail_url,
				'count'           => $cart[$uid]['variations'][$variation_id]['count'],
				'uid'             => $item->uid,
				'variation_id'    => $variation_id,
				'properties'      => unfix_chars($properties)
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
		$transaction_manager = ShopTransactionsManager::getInstance();
		$payment_method = $this->getPaymentMethod(null);

		// get specified transaction
		$transaction = $transaction_manager->get_single_item(
			$transaction_manager->get_field_names(),
			array('uid' => $uid)
		);

		$type = $this->getTransactionType();
		if (is_object($transaction))
			$type = $transaction->type;

		$result = $this->getCartSummary($uid, $type, $payment_method);
		unset($result['items_for_checkout']);

		print json_encode($result);
	}

	/**
	 * Save transaction remark before submitting form
	 */
	private function json_SaveRemark() {
		$result = false;
		$transaction = Transaction::get_current();
		$manager = ShopTransactionsManager::getInstance();
		$append = isset($_REQUEST['append']) && $_REQUEST['append'] == 1 ? true : false;

		if (!is_null($transaction)) {
			$remark = $append ? $transaction->remark."\n" : '';
			$remark .= escape_chars($_REQUEST['remark']);

			$manager->update_items(
					array('remark' => $remark),
					array('id' => $transaction->id)
				);
			$result = true;

		} else {
			trigger_error('Shop: Trying to set transaction remark before transaction is created!', E_USER_NOTICE);
		}

		print json_encode($result);
	}

	/**
	 * Save default currency to module settings
	 * @param string $currency
	 */
	public function saveDefaultCurrency($currency) {
		$this->save_setting('default_currency', $currency);
	}

	/**
	 * Return default currency
	 * @return string
	 */
	public static function getDefaultCurrency() {
		$shop = self::getInstance();
		return $shop->settings['default_currency'];
	}

	/**
	 * Get shopping cart summary.
	 *
	 * @param string $transaction_id
	 * @param integer $type
	 * @param object $payment_method
	 * @return array
	 */
	public function getCartSummary($transaction_id, $type, $payment_method=null) {
		global $default_language;

		// prepare params
		$result = array();
		$shipping = 0;
		$handling = 0;
		$total_money = 0;
		$total_discount = 0;
		$total_weight = 0;
		$items_by_uid = array();
		$items_for_checkout = array();
		$delivery_items = array();
		$map_id_to_uid = array();
		$currency = null;

		// get currency associated with transaction
		$transaction_manager = ShopTransactionsManager::getInstance();
		$currency_manager = ShopCurrenciesManager::getInstance();

		$transaction = $transaction_manager->get_single_item(
							array('currency', 'shipping', 'handling'),
							array('uid' => $transaction_id)
						);

		if (is_object($transaction)) {
			$currency = $currency_manager->get_single_item(
				$currency_manager->get_field_names(),
				array('id' => $transaction->currency)
			);

			// get shipping and handling from database
			$shipping = $transaction->shipping;
			$handling = $transaction->handling;
		}

		if (is_object($currency))
			$preferred_currency = $currency->currency; else
			$preferred_currency = 'EUR';

		// get cart summary
		switch ($type) {
			case TransactionType::SUBSCRIPTION:
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
				break;

			case TransactionType::DELAYED:
			case TransactionType::REGULAR:
			default:
				// colect ids from session
				$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
				$ids = array_keys($cart);

				if (count($cart) == 0)
					break;

				// get managers
				$manager = ShopItemManager::getInstance();

				// get items from database and prepare result
				$items = $manager->get_items($manager->get_field_names(), array('uid' => $ids));

				// parse items from database
				foreach ($items as $item) {
					$db_item = array(
						'id'		=> $item->id,
						'name'		=> $item->name,
						'price'		=> $item->price,
						'discount'	=> $item->discount,
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
							$new_item = $items_by_uid[$uid];
							$new_item['count'] = $data['count'];
							$new_item['price'] = $data['price'];
							$new_item['description'] = serialize($data);

							// add item to list for delivery estimation
							$delivery_items []= array(
								'properties'   => array(),
								'package'      => 1,
								'weight'       => 0.5,
								'package_type' => 0,
								'width'        => 2,
								'height'       => 5,
								'length'       => 15,
								'units'        => 1,
								'count'        => $data['count']
							);

							// include item data in summary
							$tax = $new_item['tax'];
							$weight = $new_item['weight'];

							if ($new_item['discount']) {
								// calculate discounted prices
								$price = $data['price'] * ((100 - $new_item['discount']) / 100);
								$total_discount += $data['price'] - $price;

							} else {
								$price = $data['price'];
							}

							$total_money += ($price * (1 + ($tax / 100))) * $data['count'];
							$total_weight += $weight * $data['count'];

							// add item to the list
							$items_for_checkout[] = $new_item;
						}
				}

				break;
		}

		$result = array(
			'items_for_checkout' => $items_for_checkout,
			'shipping'           => $shipping,
			'handling'           => $handling,
			'weight'             => $total_weight,
			'total'              => $total_money,
			'discounts'          => $total_discount,
			'currency'           => $preferred_currency
		);

		return $result;
	}

	/**
	 * Get discount for specified name.
	 *
	 * @param string $name
	 * @return object
	 */
	private function getDiscount($name) {
		$result = null;

		if (array_key_exists($name, $this->discounts))
			$result = $this->discounts[$name];

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
		if ($payment_method->needs_credit_card_information()) {
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
		$fields = array(
				'name', 'email', 'phone', 'street', 'street2',
				'city', 'zip', 'country', 'state', 'access_code'
			);

		// get delivery information
		foreach($fields as $field)
			if (isset($_REQUEST[$field]))
				$result[$field] = fix_chars($_REQUEST[$field]);

		return $result;
	}

	/**
	 * Get delivery cost for specified information.
	 *
	 * @param array $recipient
	 * @param string $method_name
	 * @param string $type
	 * @return array
	 */
	public function getDeliveryEstimate($recipient, $method_name, $type) {
		$result = array(
				'list'  => null,
				'price' => null
			);

		// get delivery method
		$method = Delivery::get_method($method_name);

		if (is_null($method)) {
			trigger_error('Shop: No delivery method specified!', E_USER_NOTICE);
			return $result;
		}

		// get warehouse address
		// TODO: Instead of picking up the first warehouse we need to
		// choose proper one based on location of items
		$warehouse_manager = ShopWarehouseManager::getInstance();
		$warehouse = $warehouse_manager->get_single_item($warehouse_manager->get_field_names(), array());

		if (!is_object($warehouse)) {
			trigger_error('Shop: No warehouse defined!', E_USER_NOTICE);
			return $result;
		}

		$shipper = array(
			'street'	=> array($warehouse->street, $warehouse->street2),
			'city'		=> $warehouse->city,
			'zip_code'	=> $warehouse->zip,
			'state'		=> $warehouse->state,
			'country'	=> $warehouse->country
		);

		// get estimate
		if ($method->hasCustomInterface()) {
			// get custom estimate from the delivery method
			$result['price'] = $method->getCustomEstimate(
					Delivery::get_items_for_estimate(),
					$shipper,
					$recipient,
					$type
				);

		} else {
			// get estimate from the list of delivery types
			$delivery_prices = $method->getDeliveryTypes(
					Delivery::get_items_for_estimate(),
					$shipper,
					$recipient
				);

			// find matching type from the list of provided types
			foreach ($delivery_prices as $data)
				if ($data[0] == $type) {
					$result['price'] = $data[1];
					break;
				}

			$result['list'] = $delivery_prices;
		}

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
		$existing_user = isset($_POST['existing_user']) ? escape_chars($_POST['existing_user']) : null;

		// set proper account data based on users choice
		if (!is_null($existing_user)) {
			switch ($existing_user) {
				case User::EXISTING:
					// get managers
					$user_manager = UserManager::getInstance();
					$retry_manager = LoginRetryManager::getInstance();

					// get user data
					$email = escape_chars($_REQUEST['sign_in_email']);
					$password = $_REQUEST['sign_in_password'];

					// check credentials
					$retry_count = $retry_manager->getRetryCount();
					$credentials_ok = $user_manager->check_credentials($email, $password);

					// get user account if sign in is valid
					if ($credentials_ok && $retry_count <= 3)
						$result = $manager->get_single_item(
								$manager->get_field_names(),
								array('email' => $email)
							);

					break;

				case User::CREATE:
					// get manager
					$user_manager = UserManager::getInstance();
					$retry_manager = LoginRetryManager::getInstance();

					// check if user agrees
					$agree_to_terms = $this->get_boolean_field('agree_to_terms');
					$want_promotions = $this->get_boolean_field('want_promotions');

					// get user data
					$data = array(
						'first_name' => escape_chars($_REQUEST['first_name']),
						'last_name'  => escape_chars($_REQUEST['last_name']),
						'email'      => escape_chars($_REQUEST['new_email']),
						'phone'      => escape_chars($_REQUEST['new_phone']),
						'uid'        => isset($_REQUEST['uid']) ? escape_chars($_REQUEST['uid']) : '',
						'guest'      => 0,
						'agreed'     => $agree_to_terms ? 1 : 0,
						'promotions' => $want_promotions ? 1 : 0
					);

					$password = $_REQUEST['new_password'];
					$password_confirm = $_REQUEST['new_password_confirm'];

					// check if system user already exists
					$user = $user_manager->get_single_item(array('id'), array('email' => $data['email']));

					if (is_object($user)) {
						// check if buyer exists
						$buyer = $manager->get_single_item(
									$manager->get_field_names(),
									array('system_user' => $user->id)
								);

						if (is_object($buyer)) {
							// buyer already exists, no need to create new
							$result = $buyer;

						} else {
							// assign system user to buyer
							$data['system_user'] = $user->id;

							// create new account
							$manager->insert_item($data);

							// get account object
							$id = $manager->get_inserted_id();
							$result = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

							// send notification email
							if (class_exists('Backend_UserManager')) {
								$backed_user_manager = Backend_UserManager::getInstance();
								$backed_user_manager->sendNotificationEmail($user->id);
							}
						}

					} else if ($password == $password_confirm) {
						$user_data = array(
								'username'   => $data['email'],
								'email'      => $data['email'],
								'phone'      => $data['phone'],
								'fullname'   => $data['first_name'].' '.$data['last_name'],
								'first_name' => $data['first_name'],
								'last_name'  => $data['last_name'],
								'level'      => 0,
								'verified'   => 0,
								'agreed'     => 0
							);
						$user_manager->insert_item($user_data);
						$data['system_user'] = $user_manager->get_inserted_id();
						$user_manager->change_password($user_data['username'], $password);

						// create new account
						$manager->insert_item($data);

						// get account object
						$id = $manager->get_inserted_id();
						$result = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

						// send notification email
						if (ModuleHandler::is_loaded('backend')) {
							$backed_user_manager = Backend_UserManager::getInstance();
							$backed_user_manager->sendNotificationEmail($result->system_user);
						}
					}
					break;

				case User::GUEST:
				default:
					// check if user agrees
					$agree_to_terms = $this->get_boolean_field('agree_to_terms');

					// check if user wants to receive promotional emails
					$want_promotions = $this->get_boolean_field('want_promotions');

					// collect data
					$conditions = array();
					$data = array(
						'first_name'  => escape_chars($_REQUEST['guest_first_name']),
						'last_name'   => escape_chars($_REQUEST['guest_last_name']),
						'phone'       => escape_chars($_REQUEST['guest_phone']),
						'guest'       => 1,
						'system_user' => 0,
						'agreed'      => $agree_to_terms ? 1 : 0,
						'promotions'  => $want_promotions ? 1 : 0
					);

					// include uid if specified
					if (isset($_REQUEST['uid'])) {
						$conditions['uid'] = escape_chars($_REQUEST['uid']);
						$data['uid'] = $conditions['uid'];
					}

					// include email if specified
					if (isset($_REQUEST['guest_email'])) {
						$conditions['email'] = escape_chars($_REQUEST['guest_email']);
						$data['email'] = $conditions['email'];
					}

					// try finding existing account
					if (count($conditions) > 0) {
						$account = $manager->get_single_item($manager->get_field_names(), $conditions);

						if (is_object($account))
							$result = $account;
					}

					// create new account
					if (!is_object($result)) {
						$manager->insert_item($data);

						// get account object
						$id = $manager->get_inserted_id();
						$result = $manager->get_single_item($manager->get_field_names(), array('id' => $id));
					}

					break;
			}

		} else if ($_SESSION['logged']) {
			// user is already logged in, get associated buyer
			$buyer = $manager->get_single_item(
				$manager->get_field_names(),
				array('system_user' => $_SESSION['uid'])
			);

			if (is_object($buyer))
				$result = $buyer;
		}

		return $result;
	}

	/**
	 * Get user's address.
	 */
	private function getAddress($buyer, $shipping_information) {
		$address_manager = ShopDeliveryAddressManager::getInstance();

		// try to associate address with transaction
		$address = $address_manager->get_single_item(
			$address_manager->get_field_names(),
			array(
				'buyer'   => $buyer->id,
				'name'    => $shipping_information['name'],
				'street'  => $shipping_information['street'],
				'street2' => isset($shipping_information['street2']) ? $shipping_information['street2'] : '',
				'city'    => $shipping_information['city'],
				'zip'     => $shipping_information['zip'],
				'state'   => $shipping_information['state'],
				'country' => $shipping_information['country'],
			));

		if (is_object($address)) {
			// existing address
			$result = $address;

		} else {
			// create new address
			$address_manager->insert_item(array(
				'buyer'       => $buyer->id,
				'name'        => $shipping_information['name'],
				'street'      => $shipping_information['street'],
				'street2'     => isset($shipping_information['street2']) ? $shipping_information['street2'] : '',
				'email'       => $shipping_information['email'],
				'phone'       => $shipping_information['phone'],
				'city'        => $shipping_information['city'],
				'zip'         => $shipping_information['zip'],
				'state'       => $shipping_information['state'],
				'country'     => $shipping_information['country'],
				'access_code' => $shipping_information['access_code']
			));

			$id = $address_manager->get_inserted_id();
			$result = $address_manager->get_single_item($address_manager->get_field_names(), array('id' => $id));
		}

		return $result;
	}

	/**
	 * Update transaction data.
	 *
	 * @param integer $type
	 * @param object $payment_method
	 * @param string $delivery_method
	 * @param string $delivery_type
	 * @param object $buyer
	 * @param object $address
	 * @return array
	 */
	private function updateTransaction($type, $payment_method, $delivery_method, $delivery_type, $buyer, $address) {
		global $db;

		$result = array();
		$transactions_manager = ShopTransactionsManager::getInstance();
		$transaction_items_manager = ShopTransactionItemsManager::getInstance();
		$transaction_plans_manager = ShopTransactionPlansManager::getInstance();
		$promotion_manager = \Modules\Shop\Transaction\PromotionManager::getInstance();

		// update buyer
		if (!is_null($buyer))
			$result['buyer'] = $buyer->id;

		// determine if we need a new session
		$new_transaction = true;

		if (isset($_SESSION['transaction']) && isset($_SESSION['transaction']['uid'])) {
			$uid = $_SESSION['transaction']['uid'];
			$transaction = $transactions_manager->get_single_item(array('status'), array('uid' => $uid));
			$new_transaction = !(is_object($transaction) && $transaction->status == TransactionStatus::PENDING);
		}

		// get delivery estimate
		if ($delivery_method) {
			$recipient = array(
					'street'   => array($address->street, $address->street2),
					'city'     => $address->city,
					'zip_code' => $address->zip,
					'state'    => $address->state,
					'country'  => $address->country,
				);
			$delivery_estimate = null;
			$delivery_data = $this->getDeliveryEstimate($recipient, $delivery_method, $delivery_type);

			if (!is_null($delivery_data['price'])) {
				// use original estimate
				$delivery_estimate = $delivery_data['price'];

			} else if (count($delivery_data['list']) > 0) {
				// no estimate available, check list
				foreach ($delivery_data['list'] as $estimate_delivery_type => $data)
					if ($estimate_delivery_type == $delivery_type) {
						$delivery_estimate = $data[1];
						break;
					}

			} else {
				// we didn't have any estimate report error
				trigger_error('No valid delivery estimate was found.', E_USER_WARNING);
			}

		} else {
			// this transaction doesn't require delivery estimate
			$delivery_estimate = 0;
		}

		// check if we have existing transaction in our database
		if ($new_transaction) {
			// get shopping cart summary
			$uid = uniqid('', true);
			$summary = $this->getCartSummary($uid, $type, $payment_method);
			$summary['shipping'] = $delivery_estimate;

			// decide on new transaction status
			$new_status = TransactionStatus::PENDING;
			if ($type == TransactionType::DELAYED)
				$new_status = TransactionStatus::UNKNOWN;

			// prepare data
			$result['uid'] = $uid;
			$result['type'] = $type;
			$result['status'] = $new_status;
			$result['handling'] = $summary['handling'];
			$result['shipping'] = $summary['shipping'];
			$result['weight'] = $summary['weight'];
			$result['payment_method'] = $payment_method->get_name();
			$result['delivery_method'] = $delivery_method;
			$result['delivery_type'] = $delivery_type;
			$result['remark'] = '';
			$result['total'] = $summary['total'];

			// get default currency
			$currency_manager = ShopCurrenciesManager::getInstance();
			$default_currency = $this->settings['default_currency'];
			$currency = $currency_manager->get_single_item(array('id'), array('currency' => $default_currency));

			if (is_object($currency))
				$result['currency'] = $currency->id;

			// add address if needed
			if (!is_null($address))
				$result['address'] = $address->id;

			// create new transaction
			$transactions_manager->insert_item($result);
			$result['id'] = $transactions_manager->get_inserted_id();

			// add discounts to result
			$result['discounts'] = $summary['discounts'];

			// store transaction data to session
			$_SESSION['transaction'] = $result;

		} else {
			$uid = $_SESSION['transaction']['uid'];
			$summary = $this->getCartSummary($uid, $type, $payment_method);
			$summary['shipping'] = $delivery_estimate;

			// there's already an existing transaction
			$result = $_SESSION['transaction'];
			$result['handling'] = $summary['handling'];
			$result['shipping'] = $summary['shipping'];
			$result['total'] = $summary['total'];

			$data = array(
				'handling'        => $summary['handling'],
				'shipping'        => $summary['shipping'],
				'total'           => $summary['total'],
				'delivery_method' => $delivery_method,
				'delivery_type'   => $delivery_type
			);

			if (!is_null($address))
				$data['address'] = $address->id;

			// update existing transaction
			$transactions_manager->update_items($data, array('uid' => $uid));

			// add discounts to result
			$result['discounts'] = $summary['discounts'];

			// update session storage with newest data
			$_SESSION['transaction'] = $result;
		}

		// remove items associated with transaction
		$transaction_items_manager->delete_items(array('transaction' => $result['id']));

		// remove plans associated with transaction
		$transaction_plans_manager->delete_items(array('transaction' => $result['id']));

		// store items
		if (count($summary['items_for_checkout']) > 0)
			foreach($summary['items_for_checkout'] as $uid => $item) {
				$transaction_items_manager->insert_item(array(
					'transaction'	=> $result['id'],
					'item'			=> $item['id'],
					'price'			=> $item['price'],
					'tax'			=> $item['tax'],
					'amount'		=> $item['count'],
					'description'	=> $item['description']
				));
			}

		$result['items_for_checkout'] = $summary['items_for_checkout'];

		// create plan entry
		if (isset($_SESSION['recurring_plan'])) {
			$plan_name = $_SESSION['recurring_plan'];
			$plan_list = $payment_method->get_recurring_plans();
			$plan = isset($plan_list[$plan_name]) ? $plan_list[$plan_name] : null;

			if (!is_null($plan))
				$transaction_plans_manager->insert_item(array(
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

		// remove associated promotions from the table
		$discount_items = array();
		$promotion_manager->delete_items(array('transaction' => $result['id']));

		foreach ($this->promotions as $promotion)
			if ($promotion->qualifies($transaction)) {
				// store discount for application later
				$discount = $promotion->get_discount();

				// insert data to database
				$data = array(
						'transaction' => $result['id'],
						'promotion'   => $promotion->get_name(),
						'discount'    => $discount->get_name()
					);

				$promotion_manager->insert_item($data);

				// apply discount
				$discount_items = array_merge($discount_items, $discount->apply($transaction));
			}

		// store discounts to transaction
		$_SESSION['transaction']['discounts'] = $discount_items;

		// deduce discounts from total amount
		$discount_total = 0;
		foreach ($discounted_items as $discount)
			$discount_total += $discount[2];

		$_SESSION['transaction']['total'] -= $discount_total;

		// if affiliate system is active, update referral
		if (isset($_SESSION['referral_id']) && ModuleHandler::is_loaded('affiliates')) {
			$referral_id = $_SESSION['referral_id'];
			$referrals_manager = AffiliateReferralsManager::getInstance();

			$referrals_manager->update_items(
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
		$transaction = $transaction_manager->get_single_item(
			array('id', 'buyer'),
			array('uid' => $transaction_uid)
		);

		// try to get buyer from the system based on uid
		if (isset($buyer_data['uid']))
			$buyer = $buyer_manager->get_single_item(
				$buyer_manager->get_field_names(),
				array('uid' => $buyer_data['uid'])
			);

		// update buyer information
		if (is_object($transaction)) {
			// get buyer id
			if (is_object($buyer)) {
				$buyer_id = $buyer->id;

				// update buyer information
				$buyer_manager->update_items($buyer_data, array('id' => $buyer->id));

			} else {
				// create new buyer
				$buyer_manager->insert_item($buyer_data);
				$buyer_id = $buyer_manager->get_inserted_id();
			}

			// update transaction buyer
			$transaction_manager->update_items(
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
		if (!ModuleHandler::is_loaded('contact_form'))
			return $result;

		$email_address = null;
		$contact_form = contact_form::getInstance();

		// template replacement data
		$status_text = $this->get_language_constant(TransactionStatus::$reverse[$transaction->status]);
		$fields = array(
			'transaction_id'				=> $transaction->id,
			'transaction_uid'				=> $transaction->uid,
			'status'						=> $transaction->status,
			'status_text'					=> $status_text,
			'handling'						=> $transaction->handling,
			'shipping'						=> $transaction->shipping,
			'total'							=> $transaction->total,
			'weight'						=> $transaction->weight,
			'payment_method'				=> $transaction->payment_method,
			'delivery_method'				=> $transaction->delivery_method,
			'delivery_type'					=> $transaction->delivery_type,
			'remark'						=> $transaction->remark,
			'remote_id'						=> $transaction->remote_id,
			'timestamp'						=> $transaction->timestamp
		);

		$timestamp = strtotime($transaction->timestamp);
		$fields['date'] = date($this->get_language_constant('format_date_short'), $timestamp);
		$fields['time'] = date($this->get_language_constant('format_time_short'), $timestamp);

		// get currency
		$currency_manager = ShopCurrenciesManager::getInstance();
		$currency = $currency_manager->get_single_item(
				$currency_manager->get_field_names(),
				array('id' => $transaction->currency)
			);

		if (is_object($currency))
			$fields['currency'] = $currency->currency;

		// add buyer information
		$buyer_manager = ShopBuyersManager::getInstance();
		$buyer = $buyer_manager->get_single_item(
				$buyer_manager->get_field_names(),
				array('id' => $transaction->buyer)
			);

		if (is_object($buyer)) {
			$fields['buyer_first_name'] = $buyer->first_name;
			$fields['buyer_last_name'] = $buyer->last_name;
			$fields['buyer_email'] = $buyer->email;
			$fields['buyer_phone'] = $buyer->phone;
			$fields['buyer_uid'] = $buyer->uid;

			$email_address = $buyer->email;
		}

		// add buyer address
		$address_manager = ShopDeliveryAddressManager::getInstance();
		$address = $address_manager->get_single_item(
			$address_manager->get_field_names(),
			array('id' => $transaction->address)
		);

		if (is_object($address)) {
			$fields['address_name'] = $address->name;
			$fields['address_street'] = $address->street;
			$fields['address_street2'] = $address->street2;
			$fields['address_email'] = $address->email;
			$fields['address_phone'] = $address->phone;
			$fields['address_city'] = $address->city;
			$fields['address_zip'] = $address->zip;
			$fields['address_state'] = $address->state;
			$fields['address_country'] = $address->country;
		}

		// create item table
		switch ($transaction->type) {
			case TransactionType::REGULAR:
				$subtotal = 0;
				$item_manager = ShopItemManager::getInstance();
				$transaction_item_manager = ShopTransactionItemsManager::getInstance();
				$items = $transaction_item_manager->get_items(
					$transaction_item_manager->get_field_names(),
					array('transaction' => $transaction->id)
				);

				if (count($items) > 0) {
					// prepare item names
					$id_list = array();
					foreach ($items as $item)
						$id_list[] = $item->item;

					$item_names = array();
					$item_list = $item_manager->get_items(array('id', 'name'), array('id' => $id_list));
					foreach ($item_list as $item)
						$item_names[$item->id] = $item->name[$language];

					// create items table
					$text_table = str_pad($this->get_language_constant('column_name'), 60);
					$text_table .= str_pad($this->get_language_constant('column_price'), 8);
					$text_table .= str_pad($this->get_language_constant('column_amount'), 6);
					$text_table .= str_pad($this->get_language_constant('column_item_total'), 8);
					$text_table .= "\n" . str_repeat('-', 60 + 8 + 6 + 8) . "\n";

					$html_table = '<table border="0" cellspacing="5" cellpadding="0">';
					$html_table .= '<thead><tr>';
					$html_table .= '<td>'.$this->get_language_constant('column_name').'</td>';
					$html_table .= '<td>'.$this->get_language_constant('column_price').'</td>';
					$html_table .= '<td>'.$this->get_language_constant('column_amount').'</td>';
					$html_table .= '<td>'.$this->get_language_constant('column_item_total').'</td>';
					$html_table .= '</td></thead><tbody>';

					foreach ($items as $item) {
						// append item name with description
						$description = unserialize($item->description);

						if (!empty($description)) {
							$description_text = implode(', ', array_values($description));
							$line = $item_names[$item->item]. ' (' . $description_text . ')';
						} else {
							$line = $item_names[$item->item];
						}

						$line = utf8_wordwrap($line, 60, "\n", true);
						$line = mb_split("\n", $line);

						// append other columns
						$line[0] = str_pad($line[0], 60, ' ', STR_PAD_RIGHT);
						$line[0] .= str_pad($item->price, 8, ' ', STR_PAD_LEFT);
						$line[0] .= str_pad($item->amount, 6, ' ', STR_PAD_LEFT);
						$line[0] .= str_pad($item->price * $item->amount, 8, ' ', STR_PAD_LEFT);

						// add this item to text table
						foreach ($line as $row)
							$text_table .= $row;
						$text_table .= "\n\n";

						// form html row
						$row = '<tr><td>' . $item_names[$item->item];

						if (!empty($description))
							$row .= ' <small>' . $description_text . '</small>';

						$row .= '</td><td>' . $item->price . '</td>';
						$row .= '<td>' . $item->amount . '</td>';
						$row .= '<td>' . ($item->price * $item->amount) . '</td></tr>';
						$html_table .= $row;

						// update subtotal
						$subtotal += $item->price * $item->amount;
					}

					// close text table
					$text_table .= str_repeat('-', 60 + 8 + 6 + 8) . "\n";
					$html_table .= '</tbody>';

					// create totals
					$text_table .= str_pad($this->get_language_constant('column_subtotal'), 15);
					$text_table .= str_pad($subtotal, 10, ' ', STR_PAD_LEFT) . "\n";

					$text_table .= str_pad($this->get_language_constant('column_shipping'), 15);
					$text_table .= str_pad($transaction->shipping, 10, ' ', STR_PAD_LEFT) . "\n";

					$text_table .= str_pad($this->get_language_constant('column_handling'), 15);
					$text_table .= str_pad($transaction->handling, 10, ' ', STR_PAD_LEFT) . "\n";

					$text_table .= str_repeat('-', 25);
					$text_table .= str_pad($this->get_language_constant('column_total'), 15);
					$text_table .= str_pad($transaction->total, 10, ' ', STR_PAD_LEFT) . "\n";

					$html_table .= '<tfoot>';
					$html_table .= '<tr><td colspan="2"></td><td>' . $this->get_language_constant('column_subtotal') . '</td>';
					$html_table .= '<td>' . $subtotal . '</td></tr>';

					$html_table .= '<tr><td colspan="2"></td><td>' . $this->get_language_constant('column_shipping') . '</td>';
					$html_table .= '<td>' . $transaction->shipping . '</td></tr>';

					$html_table .= '<tr><td colspan="2"></td><td>' . $this->get_language_constant('column_handling') . '</td>';
					$html_table .= '<td>' . $transaction->handling . '</td></tr>';

					$html_table .= '<tr><td colspan="2"></td><td><b>' . $this->get_language_constant('column_total') . '</b></td>';
					$html_table .= '<td><b>' . $transaction->total . '</b></td></tr>';

					$html_table .= '</tfoot>';

					// close table
					$html_table .= '</table>';

					// add field
					$fields['html_item_table'] = $html_table;
					$fields['text_item_table'] = $text_table;
				}
				break;

			case TransactionType::DELAYED:
				$subtotal = 0;
				$item_manager = ShopItemManager::getInstance();
				$transaction_item_manager = ShopTransactionItemsManager::getInstance();
				$items = $transaction_item_manager->get_items(
					$transaction_item_manager->get_field_names(),
					array('transaction' => $transaction->id)
				);

				if (count($items) > 0) {
					// prepare item names
					$id_list = array();
					foreach ($items as $item)
						$id_list[] = $item->item;

					$item_names = array();
					$item_list = $item_manager->get_items(array('id', 'name'), array('id' => $id_list));
					foreach ($item_list as $item)
						$item_names[$item->id] = $item->name[$language];

					// create items table
					$text_table = str_pad($this->get_language_constant('column_name'), 60);
					$text_table .= str_pad($this->get_language_constant('column_amount'), 6);
					$text_table .= "\n" . str_repeat('-', 60 + 6) . "\n";

					$html_table = '<table border="0" cellspacing="5" cellpadding="0">';
					$html_table .= '<thead><tr>';
					$html_table .= '<td>'.$this->get_language_constant('column_name').'</td>';
					$html_table .= '<td>'.$this->get_language_constant('column_amount').'</td>';
					$html_table .= '</td></thead><tbody>';

					foreach ($items as $item) {
						// append item name with description
						$description = unserialize($item->description);

						if (!empty($description)) {
							$description_text = implode(', ', array_values($description));
							$line = $item_names[$item->item]. ' (' . $description_text . ')';
						} else {
							$line = $item_names[$item->item];
						}

						$line = utf8_wordwrap($line, 60, "\n", true);
						$line = mb_split("\n", $line);

						// correct columns
						$line[0] = str_pad($line[0], 60, ' ', STR_PAD_RIGHT);
						$line[0] .= str_pad($item->amount, 6, ' ', STR_PAD_LEFT);

						// add this item to text table
						foreach ($line as $row)
							$text_table .= $row;
						$text_table .= "\n\n";

						// form html row
						$row = '<tr><td>' . $item_names[$item->item];

						if (!empty($description))
							$row .= ' <small>' . $description_text . '</small>';

						$row .= '</td><td>' . $item->amount . '</td></tr>';
						$html_table .= $row;
					}

					// close text table
					$text_table .= str_repeat('-', 60 + 6) . "\n";
					$html_table .= '</tbody>';

					// create totals
					$text_table .= str_pad($this->get_language_constant('column_shipping'), 15);
					$text_table .= str_pad($transaction->shipping, 10, ' ', STR_PAD_LEFT) . "\n";

					$text_table .= str_pad($this->get_language_constant('column_handling'), 15);
					$text_table .= str_pad($transaction->handling, 10, ' ', STR_PAD_LEFT) . "\n";

					$text_table .= str_repeat('-', 25);
					$text_table .= str_pad($this->get_language_constant('column_total'), 15);
					$text_table .= str_pad($transaction->total, 10, ' ', STR_PAD_LEFT) . "\n";

					$html_table .= '<tfoot>';
					$html_table .= '<tr><td></td><td>' . $this->get_language_constant('column_shipping') . '</td>';
					$html_table .= '<td>' . $transaction->shipping . '</td></tr>';

					$html_table .= '<tr><td></td><td>' . $this->get_language_constant('column_handling') . '</td>';
					$html_table .= '<td>' . $transaction->handling . '</td></tr>';

					$html_table .= '<tr><td></td><td><b>' . $this->get_language_constant('column_total') . '</b></td>';
					$html_table .= '<td><b>' . $transaction->total . '</b></td></tr>';

					$html_table .= '</tfoot>';

					// close table
					$html_table .= '</table>';

					// add field
					$fields['html_item_table'] = $html_table;
					$fields['text_item_table'] = $text_table;
				}
				break;

			case TransactionType::SUBSCRIPTION:
				$plan_manager = ShopTransactionPlansManager::getInstance();
				$plan = $plan_manager->get_single_item(
					$plan_manager->get_field_names(),
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
		$mailers = $contact_form->getMailers();
		$sender = $contact_form->getSender();
		$template = $contact_form->getTemplate($template);

		// get default recipient
		$recipients = array();
		if ($this->settings['send_copy'])
			$recipients = $contact_form->getRecipients();

		// start creating message
		foreach ($mailers as $mailer_name => $mailer) {
			$mailer->start_message();
			$mailer->set_subject($template['subject']);
			$mailer->set_sender($sender['address'], $sender['name']);
			$mailer->add_recipient($email_address);

			if (count($recipients) > 0)
				foreach ($recipients as $recipient)
					$mailer->add_recipient($recipient['address']);

			$mailer->set_body($template['plain_body'], $template['html_body']);
			$mailer->set_variables($fields);

			// send email
			$mailer->send();
		}

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
			$template = $this->load_template($tag_params, 'plan.xml');
			$template->setTemplateParamsFromArray($children);
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
	 * Handle drawing checkout form.
	 *
	 * Checkout form is organized in following stages:
	 * Input -> Set info -> [Resume] -> Checkout
	 *
	 * Set info and Resume stages are not being handled by any template. They are considered
	 * inbetween stages where system prepares all the data required for Checkout stage. Internally
	 * stage will change from any of the two to Checkout.
	 *
	 * Resume stage is optional and is used for payment methods that require redirecting
	 * to external resource before showing checkout page. To achieve this, Shop module emits
	 * `before-checkout` event signal which allows payment method to redirect to desired
	 * resource. That resource then has to redirect back to checkout URL with stage set to `resume`.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CheckoutForm($tag_params, $children) {
		$billing_information = array();
		$buyer = null;
		$address = null;
		$payment_method = null;
		$stage = isset($_REQUEST['stage']) ? fix_chars($_REQUEST['stage']) : null;
		$original_stage = $stage;
		$transaction_type = $this->getTransactionType();
		$bad_fields = array();
		$delivery_method = '';
		$delivery_type = '';

		// decide whether to include shipping and account information
		$include_shipping = true;
		if (isset($tag_params['include_shipping']))
			$include_shipping = fix_id($tag_params['include_shipping']) == 1;

		// handle data preparation of checkout process
		switch ($stage) {
			case Stage::RESUME:
				// collect billing information
				$payment_method = $this->getPaymentMethod($tag_params);
				$billing_information = $this->getBillingInformation($payment_method);

				// get buyer and address associated with transaction
				$buyer_manager = ShopBuyersManager::getInstance();
				$address_manager = ShopDeliveryAddressManager::getInstance();

				// get transaction with specified unique id
				$transaction = Transaction::get_current();

				if (is_object($transaction)) {
					$buyer = $buyer_manager->get_single_item(
							$buyer_manager->get_field_names(),
							array('id' => $transaction->buyer)
						);
					$address = $address_manager->get_single_item(
							$address_manager->get_field_names(),
							array('id' => $transaction->address)
						);
					$stage = Stage::CHECKOUT;
				}
				break;

			case Stage::SET_INFO:
				// get buyer
				$buyer = $this->getUserAccount();

				// collect shipping information
				if ($include_shipping) {
					$shipping_required = array('name', 'email', 'street', 'city', 'zip', 'country');
					$shipping_information = $this->getShippingInformation();
					$address = $this->getAddress($buyer, $shipping_information);

				} else {
					$shipping_required = array();
				}

				// collect billing information
				$payment_method = $this->getPaymentMethod($tag_params);
				$billing_information = $this->getBillingInformation($payment_method);

				$bad_fields = array();
				$bad_fields = $this->checkFields($shipping_information, $shipping_required, $bad_fields);
				$required_count = count($shipping_required);
				$fields_are_invalid = count($bad_fields) > 0 && $required_count > 0;

				// log bad behavior if debugging is enabled
				if ($fields_are_invalid && defined('DEBUG'))
					trigger_error('Checkout bad fields: '.implode(', ', $bad_fields), E_USER_NOTICE);

				if (is_null($buyer) && defined('DEBUG'))
					trigger_error('Unable to get buyer. Check database compatibility!', E_USER_NOTICE);

				// reset stage back to data entry
				if ($fields_are_invalid || is_null($payment_method) || is_null($buyer))
					$stage = Stage::INPUT; else
					$stage = Stage::CHECKOUT;

				// get delivery method values
				if ($include_shipping) {
					$delivery_method = escape_chars($_REQUEST['delivery_method']);
					$delivery_type = escape_chars($_REQUEST['delivery_type']);
				}
				break;
		}

		// handle final stage of checkout process
		switch ($stage) {
			case Stage::CHECKOUT:
				// get fields for payment method
				$return_url = url_Make('checkout-completed', 'shop', array('payment_method', $payment_method->get_name()));
				$cancel_url = url_Make('checkout-canceled', 'shop', array('payment_method', $payment_method->get_name()));

				// update transaction
				$summary = $this->updateTransaction(
						$transaction_type,
						$payment_method,
						$delivery_method,
						$delivery_type,
						$buyer,
						$address
					);

				// emit signal and give payment methods a chance to redirect
				// payment process to external location only on initial redirect
				// to checkout page to prevent dead loop
				if ($original_stage == Stage::SET_INFO) {
					$result_list = Events::trigger(
							'shop', 'before-checkout',
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
				switch ($transaction_type) {
					case TransactionType::SUBSCRIPTION:
						// recurring payment
						$checkout_fields = $payment_method->new_recurring_payment(
							$summary,
							$billing_information,
							$_SESSION['recurring_plan'],
							$return_url,
							$cancel_url
						);
						break;

					case TransactionType::DELAYED:
						// regular payment
						$checkout_fields = $payment_method->new_delayed_payment(
							$summary,
							$billing_information,
							$summary['items_for_checkout'],
							$return_url,
							$cancel_url
						);
						break;

					case TransactionType::REGULAR:
					default:
						// regular payment
						$checkout_fields = $payment_method->new_payment(
							$summary,
							$billing_information,
							$summary['items_for_checkout'],
							$return_url,
							$cancel_url
						);
						break;
				}

				// load template
				$template = $this->load_template($tag_params, 'checkout_form.xml', 'checkout_template');
				$template->setTemplateParamsFromArray($children);
				$template->registerTagHandler('cms:checkout_items', $this, 'tag_CheckoutItems');
				$template->registerTagHandler('cms:discounted_items', $this, 'tag_DiscountedItemList');
				$template->registerTagHandler('cms:discounts', $this, 'tag_DiscountList');

				// parse template
				$params = array(
					'checkout_url'     => $payment_method->get_url(),
					'checkout_fields'  => $checkout_fields,
					'checkout_name'    => $payment_method->get_title(),
					'method'           => $payment_method->get_name(),
					'currency'         => self::getDefaultCurrency(),
					'recurring'        => $transaction_type == TransactionType::SUBSCRIPTION,
					'include_shipping' => $include_shipping,
					'type'             => $transaction_type
				);

				// for recurring plans add additional params
				if ($transaction_type == TransactionType::SUBSCRIPTION) {
					$plans = $payment_method->get_recurring_plans();
					$plan_name = $_SESSION['recurring_plan'];

					$plan = $plans[$plan_name];

					$params['plan_name'] = $plan['name'];
					$params['plan_description'] = $this->formatRecurring(array(
						'price'			=> $plan['price'],
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
					$params['discounts'] = number_format($summary['discounts'], 2);
				}

				// add transaction specific data
				$transaction = Transaction::get_current();
				if (!is_null($transaction)) {
					$params['remarks'] = $transaction->remark;
				}

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
				break;

			// initial stage of checkout process
			case Stage::INPUT:
			default:
				// no information available, show form
				$template = $this->load_template($tag_params, 'buyer_information.xml');
				$template->setTemplateParamsFromArray($children);
				$template->registerTagHandler('cms:card_type', $this, 'tag_CardType');
				$template->registerTagHandler('cms:payment_method', $this, 'tag_PaymentMethod');
				$template->registerTagHandler('cms:payment_method_list', $this, 'tag_PaymentMethodsList');
				$template->registerTagHandler('cms:delivery_methods', $this, 'tag_DeliveryMethodsList');

				// get fixed country if set
				$fixed_country = '';
				if (isset($this->settings['fixed_country']))
					$fixed_country = $this->settings['fixed_country'];

				// get login retry count
				$retry_manager = LoginRetryManager::getInstance();
				$count = $retry_manager->getRetryCount();
				$buyer_terms_link = null;

				if (isset($_SESSION['buyer_terms_link']))
					$buyer_terms_link = $_SESSION['buyer_terms_link'];

				$params = array(
					'include_shipping'	=> $include_shipping,
					'fixed_country'		=> $fixed_country,
					'bad_fields'		=> $bad_fields,
					'recurring'			=> $transaction_type == TransactionType::SUBSCRIPTION,
					'show_captcha'		=> $count > 3,
					'terms_link'		=> $buyer_terms_link,
					'payment_method'	=> isset($tag_params['payment_method']) ? $tag_params['payment_method'] : null
				);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
				break;
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
		$transaction_type = $this->getTransactionType();

		// get items from database
		$items = $manager->get_items($manager->get_field_names(), array('uid' => $ids));
		$items_by_uid = array();
		$items_for_checkout = array();

		// parse items from database
		foreach ($items as $item) {
			$db_item = array(
				'name'		=> $item->name,
				'price'		=> $item->price,
				'discount'	=> $item->discount,
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
					if ($new_item['discount'])
						$price = $data['price'] * ((100 - $new_item['discount']) / 100); else
						$price = $data['price'];

					$new_item['count'] = $data['count'];
					$new_item['description'] = implode(', ', array_values($properties));
					$new_item['total'] = number_format(($price * (1 + ($new_item['tax'] / 100))) * $new_item['count'], 2);
					$new_item['tax'] = number_format($new_item['tax'], 2);
					$new_item['price'] = number_format($price, 2);
					$new_item['weight'] = number_format($new_item['weight'], 2);
					$new_item['transaction_type'] = $transaction_type;

					// add item to the list
					$items_for_checkout[] = $new_item;
				}
		}

		// load template
		$template = $this->load_template($tag_params, 'checkout_form_item.xml');
		$template->setTemplateParamsFromArray($children);

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
		$template = $this->load_template($tag_params, 'checkout_message.xml');
		$template->setTemplateParamsFromArray($children);

		// get message to show
		$message = Language::getText('message_checkout_completed');
		if (empty($message))
			$message = $this->get_language_constant('message_checkout_completed');

		// prepare template parameters
		$params = array(
				'message'		=> $message,
				'button_text'	=> $this->get_language_constant('button_take_me_back'),
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
		$template = $this->load_template($tag_params, 'checkout_message.xml');
		$template->setTemplateParamsFromArray($children);

		// get message to show
		$message = Language::getText('message_checkout_canceled');
		if (empty($message))
			$message = $this->get_language_constant('message_checkout_canceled');

		// prepare template parameters
		$params = array(
				'message'		=> $message,
				'button_text'	=> $this->get_language_constant('button_take_me_back'),
				'button_action'	=> url_Make('', 'home'),
				'redirect'		=> false
			);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show currently selected or specified payment method.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_PaymentMethod($tag_params, $children) {
		$method = null;
		$only_recurring = isset($_SESSION['recurring_plan']) && !empty($_SESSION['recurring_plan']);

		// get predefined method
		$name = null;

		if (isset($tag_params['name']))
			$name = escape_chars($tag_params['name']);

		// make sure method exists
		if (!isset($this->payment_methods[$name]))
			return;

		$method = $this->payment_methods[$name];

		// make sure method fits requirement
		if ($only_recurring && !$method->supports_recurring())
			return;

		// prepare parameters
		$params = array(
			'name'              => $method->get_name(),
			'title'             => $method->get_title(),
			'icon'              => $method->get_icon_url(),
			'image'             => $method->get_image_url(),
			'needs_credit_card' => $method->needs_credit_card_information()
		);

		// load and parse template
		$template = $this->load_template($tag_params, 'payment_method.xml');
		$template->setTemplateParamsFromArray($children);
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
		$template = $this->load_template($tag_params, 'payment_method.xml');
		$template->setTemplateParamsFromArray($children);
		$only_recurring = isset($_SESSION['recurring_plan']) && !empty($_SESSION['recurring_plan']);

		if (count($this->payment_methods) > 0)
			foreach ($this->payment_methods as $name => $module)
				if (($only_recurring && $module->supports_recurring()) || !$only_recurring) {
					$params = array(
						'name'              => $name,
						'title'             => $module->get_title(),
						'icon'              => $module->get_icon_url(),
						'image'             => $module->get_image_url(),
						'needs_credit_card' => $module->needs_credit_card_information()
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
		$template = $this->load_template($tag_params, 'delivery_method.xml');
		$template->setTemplateParamsFromArray($children);
		$selected = Delivery::get_current_name();

		if (Delivery::method_count() > 0)
			foreach(Delivery::get_printable_list() as $name => $data) {
				$params = $data;
				$params['selected'] = ($selected == $name);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Show list of discounts for current transaction.
	 *
	 * @param array $tag_params
	 * @param array children
	 */
	public function tag_DiscountedItemList($tag_params, $children) {
		$manager = ShopItemManager::getInstance();

		// get items which have discounted price
		$item_to_display = array();
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$uid_list = array_keys($cart);
		$items = $manager->get_items($manager->get_field_names(), array('uid' => $uid_list));

		// prepare template
		$template = $this->load_template($tag_params, 'checkout_form_discounted_items.xml');

		if (count($items) > 0)
			foreach ($items as $item) {
				// make sure item has discount
				if (!$item->discount)
					continue;

				$uid = $item->uid;
				if (!array_key_exists($uid, $cart) || count($cart[$uid]['variations']) == 0)
					continue;

				// show items
				foreach ($cart[$uid]['variations'] as $variation_id => $properties) {
					if ($item->discount)
						$price = $properties['price'] * ((100 - $item->discount) / 100); else
						$price = $data['price'];

					$count = $properties['count'];
					$discount_amount = $properties['price'] - $price;

					$params = array(
							'name'            => $item->name,
							'count'           => $count,
							'price'           => $properties['price'],
							'discount'        => $item->discount,
							'discount_amount' => number_format($discount_amount, 2),
							'final_price'     => number_format($price, 2)
						);

					$template->restoreXML();
					$template->setLocalParams($params);
					$template->parse();
				}
			}
	}

	/**
	 * Render tag for list of applied discounts.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_DiscountList($tag_params, $children) {
		$template = $this->load_template($tag_params, 'discount_item.xml');

		foreach ($discount_items as $item) {
			$params = array(
				'text'   => $item[0],
				'count'  => $item[1],
				'amount' => $item[2]
			);

			$template->setLocalParams($params);
			$template->restoreXML();
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
			RecurringPayment::DAY 	=> $this->get_language_constant('cycle_day'),
			RecurringPayment::WEEK	=> $this->get_language_constant('cycle_week'),
			RecurringPayment::MONTH	=> $this->get_language_constant('cycle_month'),
			RecurringPayment::YEAR	=> $this->get_language_constant('cycle_year')
		);

		$selected = isset($tag_params['selected']) ? fix_id($tag_params['selected']) : null;
		$template = $this->load_template($tag_params, 'cycle_unit_option.xml');
		$template->setTemplateParamsFromArray($children);

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
		$template = $this->load_template($tag_params, 'card_type.xml');
		$template->setTemplateParamsFromArray($children);

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
	 * Render account options tag.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_AccountOptions($tag_params, $children) {
		$template = $this->load_template($tag_params, 'account_type_option.xml');
		$template->setTemplateParamsFromArray($children);

		if (isset($tag_params['selected']))
			$selected = escape_chars($tag_params['selected']); else
			$selected = User::GUEST;

		$options = array(
			User::EXISTING => $this->get_language_constant('label_existing_user'),
			User::CREATE => $this->get_language_constant('label_new_user'),
			User::GUEST => $this->get_language_constant('label_guest')
		);

		foreach ($options as $value => $text) {
			$params = array(
				'value'    => $value,
				'name'     => $text,
				'selected' => $selected == $value
			);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
 	 * Render list of discounts.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_DiscountList($tag_params, $children) {
		$template = $this->load_template($tag_params, 'discount_list_item.xml');
		$selected = null;

		// make sure we have registered discounts
		if (count($this->discounts) == 0)
			return;

		// collect extra parameters
		if (isset($tag_params['selected']))
			$selected = fix_chars($tag_params['selected']);

		foreach ($this->discounts as $text_id => $discount) {
			// prepare parameters
			$params = array(
				'text_id'  => $text_id,
				'selected' => $selected == $text_id,
				'title'    => $discount->get_title()
			);

			// parse template
			$template->setLocalParams($params);
			$template->restoreXML();
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
			RecurringPayment::DAY 	=> mb_strtolower($this->get_language_constant('cycle_day')),
			RecurringPayment::WEEK	=> mb_strtolower($this->get_language_constant('cycle_week')),
			RecurringPayment::MONTH	=> mb_strtolower($this->get_language_constant('cycle_month')),
			RecurringPayment::YEAR	=> mb_strtolower($this->get_language_constant('cycle_year'))
		);

		$template = $this->get_language_constant('recurring_description');
		$zero_word = $this->get_language_constant('recurring_period_zero');
		$currency = self::getDefaultCurrency();

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
