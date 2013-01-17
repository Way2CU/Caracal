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

		$this->addProperty('id', 'int');
		$this->addProperty('uid', 'varchar');
		$this->addProperty('name', 'varchar');
		$this->addProperty('user', 'int');
		$this->addProperty('clicks', 'integer');
		$this->addProperty('conversions', 'integer');
		$this->addProperty('active', 'boolean');
		$this->addProperty('default', 'boolean');
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
