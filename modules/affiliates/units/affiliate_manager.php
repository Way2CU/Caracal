<?php

/**
 * Affiliate Manager
 * Shop Referrals Support
 *
 * Copyright (c) 2012. by Way2CU
 * Author: Mladen Mijatov
 */

class AffiliatesManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('affiliates');

		$this->add_property('id', 'int');
		$this->add_property('uid', 'varchar');
		$this->add_property('name', 'varchar');
		$this->add_property('user', 'int');
		$this->add_property('clicks', 'integer');
		$this->add_property('conversions', 'integer');
		$this->add_property('active', 'boolean');
		$this->add_property('default', 'boolean');
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
