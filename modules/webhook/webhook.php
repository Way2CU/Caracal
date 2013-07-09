<?php

/**
 * Webhook Module
 * 
 * This module is used to avoid cross-site scripting limitations of JavaScript.
 * Among others, its purpose is to redirect the data from script to specified URL.
 *
 * Author: Mladen Mijatov
 */

class webhook extends Module {
	private static $_instance;
	private $_invalid_params = array(
						'section', 'action', 'PHPSESSID', '__utmz', '__utma',
						'__utmc', '__utmb', '_'
					);

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;
		
		parent::__construct(__FILE__);

		// load module style and scripts
		if (class_exists('head_tag')) {
			$head_tag = head_tag::getInstance();
			//$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/_blank.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/_blank.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if (class_exists('backend')) {
			$backend = backend::getInstance();
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
				case 'transfer':
					$this->transferAction();
					break;
					
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
	 * Transfer action to specified URL using provided parameters.
	 */
	private function transferAction() {
		$url_base = '';

		// if contact form is available, check for bots
		if (class_exists('contact_form')) {
			$contact_form = contact_form::getInstance();

			if ($contact_form->detectBots()) {
				header('HTTP/1.1 401 '.$this->getLanguageConstant('message_error_bot'));
				return;
			}
		}

		// prepare params
		$params = array();
		foreach ($_REQUEST as $key => $value)
			if (!in_array($key, $this->_invalid_params))
				$params []= $key.'='.urlencode($value);

		// create target URL
		$url = $url_base.(strpos($url_base, '?') ? '&amp;' : '?').implode('&amp;', $params);

		// submit the content
		try {
			file_get_contents($url);
			print json_encode($this->getLanguageConstant('message_success'));

		} catch (Exception $e) {
			header('HTTP/1.1 402 '.$this->getLanguageConstant('message_error_generic'));
		}
	}
}
