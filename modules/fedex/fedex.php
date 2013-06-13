<?php

/**
 * FedEx Integration
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */


class fedex extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;
		
		parent::__construct(__FILE__);

		// register backend
		if (class_exists('backend') && class_exists('shop')) {
			$backend = backend::getInstance();
			$method_menu = $backend->getMenu('shop_delivery_methods');

			if (!is_null($method_menu)) 
				$method_menu->addChild('', new backend_MenuItem(
									$this->getLanguageConstant('menu_fedex'),
									url_GetFromFilePath($this->path.'images/icon.png'),

									window_Open( // on click open window
												'fedex',
												650,
												$this->getLanguageConstant('title_settings'),
												true, true,
												backend_UrlMake($this->name, 'settings')
											),
									$level=5
								));
		}

		// register delivery method
		if (class_exists('shop')) {
			require_once('units/fedex_delivery_method.php');
			FedEx_DeliveryMethod::getInstance($this);
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
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'settings':
					$this->showSettings();
					break;

				case 'save_settings':
					$this->saveSettings();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db;

		$sql = "";

		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$sql = "";

		$db->query($sql);
	}

	/**
	 * Show settings edit form.
	 */
	private function showSettings() {
	}

	/**
	 * Save settings.
	 */
	private function saveSettings() {
	}
}
