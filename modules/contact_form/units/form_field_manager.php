<?php

class ContactForm_FormFieldManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('contact_form_fields');

		$this->addProperty('id', 'int');
		$this->addProperty('form', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('type', 'varchar');
		$this->addProperty('label', 'ml_varchar');
		$this->addProperty('placeholder', 'ml_varchar');
		$this->addProperty('min', 'int');
		$this->addProperty('max', 'int');
		$this->addProperty('maxlength', 'int');
		$this->addProperty('value', 'varchar');
		$this->addProperty('pattern', 'varchar');
		$this->addProperty('disabled', 'boolean');
		$this->addProperty('required', 'boolean');
		$this->addProperty('checked', 'boolean');
		$this->addProperty('autocomplete', 'boolean');
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
