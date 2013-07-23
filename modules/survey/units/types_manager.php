<?php

/**
 * Database item manager for survey types.
 *
 * @author: Mladen Mijatov
 */

class SurveyTypesManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('survey_types');

		$this->addProperty('id', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('fields', 'varchar');
		$this->addProperty('unique_address', 'boolean');
		$this->addProperty('send_email', 'boolean');
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
