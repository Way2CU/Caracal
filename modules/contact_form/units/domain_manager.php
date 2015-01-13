<?php


class ContactForm_DomainManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('contact_form_domains');

		$this->addProperty('form', 'int');
		$this->addProperty('domain', 'varchar');
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
