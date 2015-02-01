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

		$this->addProperty('fieldset', 'int');
		$this->addProperty('field', 'int');
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
