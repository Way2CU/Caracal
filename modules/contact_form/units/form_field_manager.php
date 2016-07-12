<?php

class ContactForm_FormFieldManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('contact_form_fields');

		$this->add_property('id', 'int');
		$this->add_property('form', 'int');
		$this->add_property('name', 'varchar');
		$this->add_property('type', 'varchar');
		$this->add_property('label', 'ml_varchar');
		$this->add_property('placeholder', 'ml_varchar');
		$this->add_property('min', 'int');
		$this->add_property('max', 'int');
		$this->add_property('maxlength', 'int');
		$this->add_property('value', 'varchar');
		$this->add_property('pattern', 'varchar');
		$this->add_property('disabled', 'boolean');
		$this->add_property('required', 'boolean');
		$this->add_property('checked', 'boolean');
		$this->add_property('autocomplete', 'boolean');
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
