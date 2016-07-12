<?php

class ContactForm_FieldValueManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('contact_form_field_values');

		$this->add_property('id', 'int');
		$this->add_property('field', 'int');
		$this->add_property('name', 'ml_varchar');
		$this->add_property('value', 'varchar');
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
