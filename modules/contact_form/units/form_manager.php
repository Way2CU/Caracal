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
		$this->addProperty('show_submit', 'boolean');
		$this->addProperty('show_reset', 'boolean');
		$this->addProperty('show_cancel', 'boolean');
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
