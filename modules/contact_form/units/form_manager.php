<?php

class ContactForm_FormManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('contact_forms');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('name', 'ml_varchar');
		$this->addProperty('action', 'varchar');
		$this->addProperty('template', 'varchar');
		$this->addProperty('use_ajax', 'boolean');
		$this->addProperty('show_submit', 'boolean');
		$this->addProperty('show_reset', 'boolean');
		$this->addProperty('show_cancel', 'boolean');
		$this->addProperty('include_reply_to', 'boolean');
		$this->addProperty('reply_to_field', 'int');
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
