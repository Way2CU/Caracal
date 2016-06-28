<?php

/**
 * Google Services Module
 *
 * This module provides support for wide range of Google services and
 * integrations with the rest of Caracal system. Most notably module provides
 * integration with Google's Measurement Protocol for the shop and other modules.
 *
 * Copyright © 2016. Way2CU. All Rights Reserved.
 * Author: Mladen Mijatov
 */

use Core\Events;
use Core\Module;


class google extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// connect events
		Events::connect('shop', 'transaction-completed', 'handle_transaction_completed', $this);
		Events::connect('shop', 'transaction-canceled', 'handle_transaction_canceled', $this);
		Events::connect('shop', 'shopping-cart-changed', 'handle_shopping_cart_changed', $this);
		Events::connect('search', 'get-results', 'handle_search', $this);
		Events::connect('backend', 'user-create', 'handle_user_create', $this);
		Events::connect('contact_form', 'submitted', 'handle_contact_form_submit', $this);
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		$this->saveSetting('proxy_use', 0);
		$this->saveSetting('proxy_host', 'localhost');
		$this->saveSetting('default_currency', 10999);
	}

	private function send_queue() {
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
	}

	/**
	 * Measure successful transaction.
	 */
	public function handle_transaction_completed() {
	}

	/**
	 * Measure canceling of transaction.
	 */
	public function handle_transaction_canceled() {
	}

	/**
	 * Measure changes in shopping cart.
	 */
	public function handle_shopping_cart_changed() {
	}

	/**
	 * Measure user searching for specific thing.
	 */
	public function handle_search() {
	}

	/**
	 * Handle creation of new account on the system.
	 */
	public function handle_user_create() {
	}

	/**
	 * Handle submission of contact form data.
	 */
	public function handle_contact_form_submit() {
	}
}

?>
