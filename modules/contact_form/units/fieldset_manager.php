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

		$this->add_property('id', 'int');
		$this->add_property('form', 'int');
		$this->add_property('name', 'varchar');
		$this->add_property('legend', 'ml_varchar');
	}

	/**
	* Public function that creates a single instance
	*/
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}

?>
