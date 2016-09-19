<?php

/**
 * Manager for fieldsets field membership.
 */

class ContactForm_FieldsetFieldsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('contact_form_fieldset_fields');

		$this->add_property('fieldset', 'int');
		$this->add_property('field', 'int');
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
