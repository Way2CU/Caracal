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

		$this->addProperty('id', 'int');
		$this->addProperty('affiliate', 'int');
		$this->addProperty('url', 'varchar');
		$this->addProperty('landing', 'varchar');
		$this->addProperty('transaction', 'int');
		$this->addProperty('conversion', 'boolean');
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
