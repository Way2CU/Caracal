<?php

/**
 * Currency converter and manager
 * 
 * http://www.google.com/ig/calculator?hl=en&q=100EUR%3D%3Frsd
 *
 */

class ShopCurrenciesHandler {
	private static $_instance;
	private $_parent;
	private $name;
	private $path;
	
	/**
	* Constructor
	*/
	protected function __construct($parent) {
		$this->_parent = $parent;
		$this->name = $this->_parent->name;
		$this->path = $this->_parent->path;
	}
	
	/**
	* Public function that creates a single instance
	*/
	public static function getInstance($parent) {
		if (!isset(self::$_instance))
		self::$_instance = new self($parent);
	
		return self::$_instance;
	}
	
	/**
	 * Transfer control to group
	 * 
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params = array(), $children = array()) {
		$action = isset($params['sub_action']) ? $params['sub_action'] : null;
		
		switch ($action) {
			
			default:
				break;
		}
	}

}

?>