<?php

/**
 * Shop Transactions Manager
 *
 * @author MeanEYE.rcf
 */

class ShopTransactionsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_transactions');

		$this->addProperty('id', 'int');
		$this->addProperty('buyer', 'int');
		$this->addProperty('address', 'int');
		$this->addProperty('uid', 'varchar');
		$this->addProperty('type', 'smallint');
		$this->addProperty('status', 'smallint');
		$this->addProperty('custom', 'varchar');
		$this->addProperty('currency', 'int');
		$this->addProperty('handling', 'decimal');
		$this->addProperty('shipping', 'decimal');
		$this->addProperty('fee', 'decimal');
		$this->addProperty('tax', 'decimal');
		$this->addProperty('gross', 'decimal');
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

?>
