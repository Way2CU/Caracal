<?php

/**
 * Stripe Payment Implementation
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */

require_once('library/Stripe.php');
require_once('units/stripe_payment_method.php');

class stripe_payment extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;
		
		parent::__construct(__FILE__);

		// load module style and scripts
		if (class_exists('head_tag')) {
			$head_tag = head_tag::getInstance();
			//$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/stripe.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/stripe.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if (class_exists('backend') && class_exists('shop')) {
			$backend = backend::getInstance();
			$method_menu = $backend->getMenu('shop_payment_methods');

			if (!is_null($method_menu)) 
				$method_menu->addChild('', new backend_MenuItem(
									$this->getLanguageConstant('menu_stripe'),
									url_GetFromFilePath($this->path.'images/icon.png'),

									window_Open( // on click open window
												'stripe',
												650,
												$this->getLanguageConstant('title_settings'),
												true, true,
												backend_UrlMake($this->name, 'settings')
											),
									$level=5
								));
		}

		// register payment method
		if (class_exists('shop')) {
			require_once("units/stripe_payment_method.php");
			Stripe_PaymentMethod::getInstance($this); 		
		}
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
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
	}

	/**
	 * Show Stripe settings form
	 */
	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$template->restoreXML();
		$template->parse();
	}
	
	/**
	 * Save settings
	 */
	private function saveSettings() {
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_article_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('articles'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}
}
