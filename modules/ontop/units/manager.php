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

		$this->add_property('id', 'int');
		$this->add_property('name', 'varchar');
		$this->add_property('uid', 'varchar');
		$this->add_property('key', 'varchar');
		$this->add_property('shop_transaction_complete', 'boolean');
		$this->add_property('contact_form_submit', 'boolean');
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
