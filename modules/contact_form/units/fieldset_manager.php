<?php

/**
 * Manager for form fieldsets.
 */

class ContactForm_FieldsetManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('contact_form_fieldsets');

		$this->addProperty('id', 'int');
		$this->addProperty('form', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('legend', 'ml_varchar');
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
