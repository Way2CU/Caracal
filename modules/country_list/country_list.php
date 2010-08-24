<?php

/**
 * Country List Module
 *
 * @author MeanEYE.rcf
 */

class country_list extends Module {
	var $country_list = null;
	var $state_list = null;

	/**
	 * Constructor
	 */
	function __construct() {
		$this->file = __FILE__;
		parent::__construct();
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param string $action
	 * @param integer $level
	 */
	function transferControl($level, $params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show':
					$this->printCountryList($level, $params);
					break;

				case 'show_states':
					$this->printStateList($level, $params);
					break;

				default:
					break;
			}
	}

	/**
	 * Print list of all countries using specified template
	 *
	 * @param integer $level
	 * @param array $params
	 */
	function printCountryList($level, $params) {
		if(is_null($this->country_list))
			$this->_loadCountryList();


	}

	/**
	 * Print a list of United States using specified template
	 *
	 * @param integer $level
	 * @param array $params
	 */
	function printStateList($level, $params) {
		if(is_null($this->state_list))
			$this->_loadStateList();

	}

	/**
	 * Load list of countries from XML
	 */
	function _loadCountryList() {
		$file_name = $this->path."data/country_list.xml";

		$data = @file_get_contents($file_name);
		$this->country_list = new XMLParser($data, $file_name);
		$this->country_list->Parse();
	}

	/**
	 * Load list of states from XML
	 */
	function _loadStateList() {
		$file_name = $this->path."data/state_list.xml";

		$data = @file_get_contents($file_name);
		$this->state_list = new XMLParser($data, $file_name);
		$this->state_list->Parse();
	}
}
