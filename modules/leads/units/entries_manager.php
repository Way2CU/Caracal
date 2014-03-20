<?php

/**
 * Database item manager for lead entries.
 *
 * @author: Mladen Mijatov
 */

class LeadsEntriesManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('leads_entries');

		$this->addProperty('id', 'int');
		$this->addProperty('type', 'int');
		$this->addProperty('address', 'varchar');
		$this->addProperty('referral', 'varchar');
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
