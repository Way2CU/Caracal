<?php

/**
 * Database item manager for survey entry data.
 *
 * @author: Mladen Mijatov
 */

class SurveyEntryDataManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('survey_entry_data');

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
