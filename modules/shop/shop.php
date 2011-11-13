<?php

/**
 * Shop Module
 *
 * @author MeanEYE.rcf
 */

require_once('units/shop_item_handler.php');
require_once('units/shop_category_handler.php');
require_once('units/shop_currencies_handler.php');
require_once('units/shop_item_sizes_handler.php');


class shop extends Module {
	private static $_instance;
	private $_payment_providers;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// create payment providers container
		$this->_payment_providers = array();


		// load module style and scripts
		if (class_exists('head_tag') && $section != 'backend') {
			$head_tag = head_tag::getInstance();
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

			$methods_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_payment_methods'),
								url_GetFromFilePath($this->path.'images/payment_methods.png'),
								window_Open( // on click open window
											'shop_payment_methods',
											490,
											$this->getLanguageConstant('title_payment_methods'),
											true, true,
											backend_UrlMake($this->name, 'payment_methods')
										),
								5  // level
							));
			$methods_menu->addSeparator(5);

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
					
				case 'show_item_sizes':
					break;
					
				case 'json_get_item':
					$handler = ShopItemHandler::getInstance($this);
					$handler->json_GetItem();
					break;

				case 'json_get_currency':
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
				  `first_name` varchar(30) NOT NULL,
				  `last_name` varchar(30) NOT NULL,
				  `email` varchar(50) NOT NULL,
				  `uid` varchar(50) NOT NULL,
				  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
		// create shop buyer addresses table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_buyer_addresses` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `buyer` int(11) NOT NULL,
				  `name` varchar(60) NOT NULL,
				  `street` varchar(100) NOT NULL,
				  `city` varchar(40) NOT NULL,
				  `zip` varchar(10) NOT NULL,
				  `state` varchar(30) NOT NULL,
				  `country` varchar(40) NOT NULL,
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
				  `type` varchar(20) NOT NULL,
				  `custom` varchar(200) NOT NULL,
				  `currency` int(11) NOT NULL,
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
				  `price` float NOT NULL,
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
	}

	/**
	 * Show checkout confirmation form
	 */
	public function show_checkout() {
		
	}
}
