<?php

/**
 * PayPal Integration Module
 *
 * @author MeanEYE.rcf
 */

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
				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db_active, $db;

		$sql = "";

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
	 * Handle payment notification from PayPal
	 */
	private function handlePaymentNotification(){
		define('_OMIT_STATS', 1);

		// prepare response data
		$strip = get_magic_quotes_gpc();
		$response = "cmd=_notify-validate";

		foreach ($_POST as $key => $value) {
			if ($strip)	$value = stripslashes($value);
			$value = urlencode($value);

			$response .= "&{$key}={$value}";
		}

		// validate with paypal.com this transaction
		$header .= "POST /cgi-bin/webscr HTTP/1.0\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . strlen($response) . "\r\n\r\n";
		$socket = fsockopen('ssl://www.paypal.com', 443, $error_number, $error_string, 30);

		if ($socket) {
			// send request
			fputs($socket, $header.$response);

			// get response from server
			$result = fgets($socket);

			if (strcmp($result, 'VERIFIED')) {
				// source data verified, now we can process them

			} else if (strcmp($result, 'INVALID')) {
				// data did not came from paypal.com

			}
		} else {

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
		$this->addProperty('custom', 'varchar');
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
