<?php

class ContactForm_FieldValueManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('contact_form_field_values');

		$this->addProperty('id', 'int');
		$this->addProperty('field', 'int');
		$this->addProperty('name', 'ml_varchar');
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
