<?php

/**
 * Manager for OnTop applications.
 *
 * Author: Mladen Mijatov
 */

namespace Modules\OnTop;

use \ItemManager as ItemManager;


class Manager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('ontop_applications');

		$this->addProperty('id', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('uid', 'varchar');
		$this->addProperty('key', 'varchar');
		$this->addProperty('shop_transaction_complete', 'boolean');
		$this->addProperty('shop_transaction_pending', 'boolean');
		$this->addProperty('contact_form_submit', 'boolean');
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
