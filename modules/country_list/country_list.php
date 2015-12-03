<?php

/**
 * Country List Module
 *
 * This module provides list of countries and states for certain countries.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;


class country_list extends Module {
	private static $_instance;

	private static $update_url = 'http://ec2-54-201-183-195.us-west-2.compute.amazonaws.com/cow/cow.csv';
	private static $csv_language = 'en-iso';

	public $country_list = null;
	public $state_list = null;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		if (class_exists('contact_form')) {
			$contact_form = contact_form::getInstance();
			$contact_form->registerField(
				'country_list',
				$this->getLanguageConstant('country_field_name'),
				$this,
				'field_CountryList'
			);
			$contact_form->registerField(
				'state_list',
				$this->getLanguageConstant('state_field_name'),
				$this,
				'field_StateList'
			);
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
	public function transferControl($params, $children) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show':
					$this->tag_CountryList($params, $children);
					break;

				case 'show_states':
					$this->tag_StateList($params, $children);
					break;

				default:
					break;
			}
	}

	/**
	 * Create table and populate with data on module initialization
	 */
	public function onInit() {
		global $db;

		// create tables
		$sql = "CREATE TABLE IF NOT EXISTS `countries` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`name` varchar(35) NOT NULL,
				`short` char(2) NOT NULL,
				PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;";
		$db->query($sql);

		$sql = "CREATE TABLE IF NOT EXISTS `country_states` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`country` char(2) NOT NULL,
				`name` varchar(30) NOT NULL,
				`short` char(5) NOT NULL,
				PRIMARY KEY (`id`),
				KEY `country` (`country`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;";
		$db->query($sql);

		// populate table with data
		if(is_null($this->country_list))
			$this->_loadCountryList();

		if(is_null($this->state_list))
			$this->_loadStateList();

		// get managers
		$country_manager = CountryManager::getInstance();
		$state_manager = CountryStateManager::getInstance();

		$country_list = $this->country_list->document->tagChildren;
		$state_list = $this->state_list->document->tagChildren;

		foreach ($country_list as $country)
			$country_manager->insertData(array(
								'name'	=> escape_chars($country->tagData),
								'short'	=> $country->tagAttrs['short']
							));

		foreach ($state_list as $country) {
			$country_code = $country->tagAttrs['short'];

			foreach ($country->tagChildren as $state)
				$state_manager->insertData(array(
									'country'	=> $country_code,
									'name'		=> $state->tagData,
									'short'		=> $state->tagAttrs['short']
								));
		}
	}

	/**
	 * Clean up database on module disable
	 */
	public function onDisable() {
		global $db;

		$tables = array('countries', 'country_states');
		$db->drop_tables($tables);
	}

	/**
	 * Print list of all countries using specified template
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CountryList($tag_params, $children) {
		$manager = CountryManager::getInstance();
		$conditions = array();

		// filter results if specified
		if (isset($tag_params['filter']))
			$conditions['short'] = explode(',', fix_chars($tag_params['filter']));

		// get tag params
		$selected = isset($tag_params['selected']) ? fix_chars($tag_params['selected']) : null;

		// create template
		$template = $this->loadTemplate($tag_params, 'country_option.xml');
		$template->setTemplateParamsFromArray($children);
		$country_list = $manager->getItems($manager->getFieldNames(), $conditions);

		// parse template
		if (count($country_list) > 0)
			foreach ($country_list as $country) {
				$params = array(
							'selected'	=> $selected,
							'name'		=> $country->name,
							'short'		=> $country->short
						);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse();
			}
	}

	/**
	 * Print a list of United States using specified template
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_StateList($tag_params, $children) {
		$manager = CountryStateManager::getInstance();
		$conditions = array();

		// get tag params
		$selected = isset($tag_params['selected']) ? fix_chars($tag_params['selected']) : null;

		if (isset($tag_params['country'])) {
			// country is defined as a part of XML tag
			$conditions['country'] = fix_chars($tag_params['country']);

		} else if (isset($_REQUEST['country'])) {
			// country is defined in query
			$conditions['country'] = fix_chars($_REQUEST['country']);
		}

		$template = $this->loadTemplate($tag_params, 'state_option.xml');
		$template->setTemplateParamsFromArray($children);
		$state_list = $manager->getItems($manager->getFieldNames(), $conditions);

		foreach ($state_list as $state) {
			$params = array(
						'selected'	=> $selected,
						'name'		=> $state->name,
						'short'		=> $state->short
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Handle contact form field for country list.
	 *
	 * @param array $params
	 */
	public function field_CountryList($params) {
		$template = $this->loadTemplate($params, 'country_list_field.xml');
		$template->registerTagHandler('cms:country_list', $this, 'tag_CountryList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Handle contact form field for state list.
	 *
	 * @param array $params
	 */
	public function field_StateList($params) {
		$template = $this->loadTemplate($params, 'state_list_field.xml');
		$template->registerTagHandler('cms:state_list', $this, 'tag_StateList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
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


class CountryManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('countries');

		$this->addProperty('id', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('short', 'char');
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


class CountryStateManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('country_states');

		$this->addProperty('id', 'int');
		$this->addProperty('country', 'char');
		$this->addProperty('name', 'varchar');
		$this->addProperty('short', 'char');
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
