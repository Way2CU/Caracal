<?php

/**
 * Shop Transaction Items Manager
 *
 * @author MeanEYE.rcf
 */

class ShopTransactionItemsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_transaction_items');

		$this->addProperty('id', 'int');
		$this->addProperty('transaction', 'int');
		$this->addProperty('item', 'int');
		$this->addProperty('price', 'float');
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
