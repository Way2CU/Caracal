<?php

class ContactForm_SubmissionManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('contact_form_submissions');

		$this->add_property('id', 'int');
		$this->add_property('form', 'int');
		$this->add_property('timestamp', 'timestamp');
		$this->add_property('address', 'varchar');
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
