<?php

class ContactForm_SubmissionManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('contact_form_submissions');

		$this->addProperty('id', 'int');
		$this->addProperty('form', 'int');
		$this->addProperty('timestamp', 'timestamp');
		$this->addProperty('address', 'varchar');
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
