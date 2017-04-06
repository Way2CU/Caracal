<?php


class ContactForm_DomainManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('contact_form_domains');

		$this->add_property('form', 'int');
		$this->add_property('domain', 'varchar');
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
