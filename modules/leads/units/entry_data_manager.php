<?php

/**
 * Database item manager for lead entry data.
 *
 * @author: Mladen Mijatov
 */

class LeadsEntryDataManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('leads_entry_data');

		$this->addProperty('entry', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('value', 'varchar');
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
