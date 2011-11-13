<?php

/**
 * PayPal Integration Module
 *
 * @author MeanEYE.rcf
 */

require_once('units/proto_buff/pb_message.php');
require_once('units/messages.php');

class paypal extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// load module style and scripts
		if (class_exists('head_tag')) {
			$head_tag = head_tag::getInstance();
			//$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/_blank.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/_blank.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if (class_exists('backend')) {
			$backend = backend::getInstance();
			$method_menu = $backend->getMenu(

			$paypal_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_paypal'),
					url_GetFromFilePath($this->path.'images/icon.png'),
					'javascript:void(0);',
					$level=6
				);

			$paypal_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_subscriptions'),
								url_GetFromFilePath($this->path.'images/subscriptions.png'),

								window_Open( // on click open window
											'paypal_subscriptions',
											650,
											$this->getLanguageConstant('title_subscriptions'),
											true, true,
											backend_UrlMake($this->name, 'subscriptions')
										),
								$level=5
							));
			$paypal_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_transactions'),
								url_GetFromFilePath($this->path.'images/transactions.png'),

								window_Open( // on click open window
											'paypal_transactions',
											550,
											$this->getLanguageConstant('title_transactions'),
											true, true,
											backend_UrlMake($this->name, 'transactions')
										),
								$level=5
							));

			$paypal_menu->addSeparator(6);

			$paypal_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_items'),
								url_GetFromFilePath($this->path.'images/items.png'),

								window_Open( // on click open window
											'paypal_items',
											650,
											$this->getLanguageConstant('title_items'),
											true, true,
											backend_UrlMake($this->name, 'items')
										),
								$level=6
							));
			$paypal_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_bridges'),
								url_GetFromFilePath($this->path.'images/bridges.png'),

								window_Open( // on click open window
											'paypal_bridges',
											650,
											$this->getLanguageConstant('title_bridges'),
											true, true,
											backend_UrlMake($this->name, 'bridges')
										),
								$level=6
							));

			$backend->addMenu($this->name, $paypal_menu);
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
				case 'handle_ipn':
					$this->handlePaymentNotification();
					break;

				case 'check_subscription':
					$this->checkSubscription();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'transactions':
					$this->showTransactions();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db_active, $db;

		$sql = "
			CREATE TABLE `paypal_transactions` (
				`id` INT NOT NULL AUTO_INCREMENT ,
				`transaction_id` VARCHAR( 15 ) NOT NULL ,
				`transaction_type` VARCHAR( 20 ) NOT NULL ,
				`custom` VARCHAR( 200 ) NOT NULL ,
				`payer_first_name` VARCHAR( 50 ) NOT NULL ,
				`payer_last_name` VARCHAR( 50 ) NOT NULL ,
				`payer_email` VARCHAR( 40 ) NOT NULL ,
				`payer_id` VARCHAR( 30 ) NOT NULL ,
				`address_name` VARCHAR( 100 ) NOT NULL ,
				`address_street` VARCHAR( 100 ) NOT NULL ,
				`address_city` VARCHAR( 30 ) NOT NULL ,
				`address_zip` VARCHAR( 15 ) NOT NULL ,
				`address_state` VARCHAR( 30 ) NOT NULL ,
				`address_country` VARCHAR( 50 ) NOT NULL ,
				`currency` VARCHAR( 3 ) NOT NULL ,
				`shipping` FLOAT NOT NULL ,
				`fee` FLOAT NOT NULL ,
				`tax` FLOAT NOT NULL ,
				`gross` FLOAT NOT NULL ,
				`timestamp` TIMESTAMP NOT NULL ,
				PRIMARY KEY (  `id` )
			) ENGINE = MYISAM ;";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db_active, $db;

		$sql = "";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Show PayPal transactions
	 */
	private function showTransactions() {
		$template = new TemplateHandler('transactions.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$template->registerTagHandler('_transaction_list', &$this, 'tag_TransactionList');
		$template->restoreXML();
		$template->parse();
	}

	private function showSubscriptions() {
	}

	/**
	 * List transactions
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_TransactionList($tag_params, $children) {
		$conditions = array();
		$manager = PayPal_TransactionManager::getInstance();

		// create template handler
		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('images_list_item.xml', $this->path.'templates/');
		}

		// map module
		$template->setMappedModule($this->name);

		// get items
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// parse all the items
		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
							''	=> '',
						);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Handle payment notification from PayPal
	 */
	private function handlePaymentNotification(){
		define('_OMIT_STATS', 1);

		trigger_error(print_r($_REQUEST, true));
		// prepare response data
		$strip = get_magic_quotes_gpc();
		$response = "cmd=_notify-validate";

		foreach ($_POST as $key => $value) {
			if ($strip)	$value = stripslashes($value);
			$value = urlencode($value);

			$response .= "&{$key}={$value}";
		}

		// validate with paypal.com this transaction
		$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . strlen($response) . "\r\n\r\n";
		$socket = fsockopen('ssl://www.paypal.com', 443, $error_number, $error_string, 30);

		if ($socket) {
			// send request
			fputs($socket, $header.$response);

			// get response from server
			$result = fgets($socket);

			if (strcmp($result, 'VERIFIED') && isset($_POST['txn_type'])) {
				// record payment
				$this->recordTransaction();

				// source data verified, now we can process them
				switch (strtolower($_POST['txn_type'])) {
					case 'subscr_payment':
						// subscription payment
						$custom = fix_chars($_REQUEST['custom']);
						$item_code = fix_chars($_REQUEST['item_number']);
						$manager = PayPal_SubscriptionManager::getInstance();

						$item = $manager->getSingleItem(
													array('id'),
													array(
														'custom'	=> $custom,
														'item_code'	=> $item_code
													));

						// prepare data for insertion
						$time = new DateTime();
						$time->modify('next month');

						$data = array(
								'custom'		=> $custom,
								'item_code'		=> $item_code,
								'valid_until'	=> $time->format('Y-m-d H:m:s')
							);

						if (is_object($item)) {
							// transaction already exists, we only need to update the time
							$manager->updateData($data, array('id' => $item->id));

						} else {
							// no transaction found, create new
							$manager->insertData($data);
						}
				}

			} else if (strcmp($result, 'INVALID')) {
				// data did not came from paypal.com

			}
		} else {

		}
	}

	/**
	 * Check subscription for specified account
	 */
	private function checkSubscription() {
		define('_OMIT_STATS', 1);

		// parse request object
		$data = file_get_contents('php://input');
		$request = new SubscriptionCheck_Request();
		$request->ParseFromString($data);

		// make response object
		$response = new SubscriptionCheck_Response();

		// check if license existst
		if (class_exists('license')) {
			// license class exists, check it
			$license_manager = license::getInstance();

			if ($license_manager->isLicenseValid($this->name, $request->license())) {
				// license is valid, get subscription data
				$manager = PayPal_SubscriptionManager::getInstance();

				$item = $manager->getSingleItem(
											$manager->getFieldNames(),
											array(
												'custom'	=> $request->custom(),
												'item_code'	=> $request->item()
											)
										);

				if (is_object($item)) {
					$timestamp = strtotime($item->valid_until);
					$response->set_error(false);
					$response->set_valid_until($timestamp);
				}

			} else {
				// license is not valid
				$response->set_valid_until(0);
				$response->set_error(true);
				$response->set_error_message('Invalid license!');
			}

		} else {
			// license module was not found, modify response acordingly
			$response->set_valid_until(0);
			$response->set_error(true);
			$response->set_error_message('License module is not active!');
		}

		// print response
		$data = $response->SerializeToString();
		header('Content-Type: application/binary', true);
		header('Content-Length: ' . strlen($data), true);

		print $data;
	}

	/**
	 * Create a transaction record from POST/GET parameters
	 */
	private function recordTransaction() {
		$data = array(
				'transaction_id'	=> fix_chars($_REQUEST['txn_id']),
				'transaction_type'	=> fix_chars($_REQUEST['txn_type']),
				'custom'			=> fix_chars($_REQUEST['custom']),

				'payer_first_name'	=> fix_chars($_REQUEST['first_name']),
				'payer_last_name'	=> fix_chars($_REQUEST['last_name']),
				'payer_email'		=> fix_chars($_REQUEST['payer_email']),
				'payer_id'			=> fix_chars($_REQUEST['payer_id']),

				'address_name'		=> fix_chars($_REQUEST['address_name']),
				'address_street'	=> fix_chars($_REQUEST['address_street']),
				'address_city'		=> fix_chars($_REQUEST['address_city']),
				'address_zip'		=> fix_chars($_REQUEST['address_zip']),
				'address_state'		=> fix_chars($_REQUEST['address_state']),
				'address_country'	=> fix_chars($_REQUEST['address_country']),

				'currency'			=> fix_chars($_REQUEST['mc_currency']),
				'shipping'			=> fix_chars($_REQUEST['shipping']),
				'fee'				=> fix_chars($_REQUEST['mc_fee']),
				'tax'				=> fix_chars($_REQUEST['tax']),
				'gross'				=> fix_chars($_REQUEST['mc_gross']),
			);
		$manager = PayPal_TransactionManager::getInstance();

		// check if transaction already exists
		$item = $manager->getSingleItem(array('id'), array('transaction_id' => $data['transaction_id']));

		if (is_object($item)) {
			// transaction already exists, update data
			$manager->updateData($data, array('id' => $item->id));

		} else {
			// transaction doesn't exist, insert new data
			$manager->insertData($data);
		}
	}
}


class PayPal_TransactionManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('paypal_transactions');

		$this->addProperty('id', 'int');
		$this->addProperty('transaction_id', 'varchar');
		$this->addProperty('transaction_type', 'varchar');
		$this->addProperty('custom', 'varchar');

		$this->addProperty('payer_first_name', 'varchar');
		$this->addProperty('payer_last_name', 'varchar');
		$this->addProperty('payer_email', 'varchar');
		$this->addProperty('payer_id', 'varchar');

		$this->addProperty('address_name', 'varchar');
		$this->addProperty('address_street', 'varchar');
		$this->addProperty('address_city', 'varchar');
		$this->addProperty('address_zip', 'varchar');
		$this->addProperty('address_state', 'varchar');
		$this->addProperty('address_country', 'varchar');

		$this->addProperty('currency', 'varchar');
		$this->addProperty('shipping', 'varchar');
		$this->addProperty('fee', 'varchar');
		$this->addProperty('tax', 'varchar');
		$this->addProperty('gross', 'varchar');

		$this->addProperty('timestamp', 'timestamp');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}


class PayPal_SubscriptionManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('paypal_subscriptions');

		$this->addProperty('id', 'int');
		$this->addProperty('custom', 'varchar');
		$this->addProperty('item_code', 'varchar');
		$this->addProperty('valid_until', 'timestamp');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}

class PayPal_Items extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('paypal_items');

		$this->addProperty('id', 'int');
		$this->addProperty('code', 'varchar');
		$this->addProperty('name', 'varchar');
		$this->addProperty('is_subscription', 'boolean');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}

class PayPal_Bridges extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('paypal_bridges');

		$this->addProperty('id', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('license', 'varchar');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}

class PayPal_BridgeItems extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('paypal_bridge_items');

		$this->addProperty('id', 'int');
		$this->addProperty('bridge', 'int');
		$this->addProperty('item', 'int');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}
