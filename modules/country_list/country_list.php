<?php

/**
 * Country List Module
 *
 * @author MeanEYE.rcf
 */

class country_list extends Module {
	private static $_instance;
	public $country_list = null;
	public $state_list = null;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);
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
	public function transferControl($params, $children) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show':
					$this->printCountryList($params);
					break;

				case 'show_states':
					$this->printStateList($params);
					break;

				default:
					break;
			}
	}

	/**
	 * Print list of all countries using specified template
	 *
	 * @param array $params
	 */
	private function printCountryList($params) {
		if(is_null($this->country_list))
			$this->_loadCountryList();
	}

	/**
	 * Print a list of United States using specified template
	 *
	 * @param array $params
	 */
	private function printStateList($params) {
		if(is_null($this->state_list))
			$this->_loadStateList();
	}

	/**
	 * Load list of countries from XML
	 */
	private function _loadCountryList() {
		$file_name = $this->path."data/country_list.xml";

		$data = @file_get_contents($file_name);
		$this->country_list = new XMLParser($data, $file_name);
		$this->country_list->Parse();
	}

	/**
	 * Load list of states from XML
	 */
	private function _loadStateList() {
		$file_name = $this->path."data/state_list.xml";

		$data = @file_get_contents($file_name);
		$this->state_list = new XMLParser($data, $file_name);
		$this->state_list->Parse();
	}
}
