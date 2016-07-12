<?php

/**
 * Referrals Manager
 * Shop Referrals Support
 *
 * Copyright (c) 2012. by Way2CU
 * Author: Mladen Mijatov
 */

class AffiliateReferralsManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('affiliate_referrals');

		$this->add_property('id', 'int');
		$this->add_property('affiliate', 'int');
		$this->add_property('url', 'varchar');
		$this->add_property('landing', 'varchar');
		$this->add_property('transaction', 'int');
		$this->add_property('conversion', 'boolean');
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
