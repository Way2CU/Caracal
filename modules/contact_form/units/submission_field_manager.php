<?php

class ContactForm_SubmissionFieldManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('contact_form_submission_fields');

		$this->addProperty('id', 'int');
		$this->addProperty('submission', 'int');
		$this->addProperty('field', 'int');
		$this->addProperty('value', 'text');
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
