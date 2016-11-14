<?php

class ContactForm_FormManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('contact_forms');

		$this->add_property('id', 'int');
		$this->add_property('text_id', 'varchar');
		$this->add_property('name', 'ml_varchar');
		$this->add_property('action', 'varchar');
		$this->add_property('template', 'varchar');
		$this->add_property('use_ajax', 'boolean');
		$this->add_property('show_submit', 'boolean');
		$this->add_property('show_reset', 'boolean');
		$this->add_property('show_cancel', 'boolean');
		$this->add_property('include_reply_to', 'boolean');
		$this->add_property('reply_to_field', 'int');
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
