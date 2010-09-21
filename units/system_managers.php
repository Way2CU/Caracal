<?php

/**
 * System Managers
 * 
 * @author MeanEYE.rcf
 */

class ModuleManager extends ItemManager {
	private static $_instance;
	
	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_modules');

		$this->addProperty('id', 'int');
		$this->addProperty('order', 'int');
		$this->addProperty('name', 'varchar');
		$this->addProperty('preload', 'int');
		$this->addProperty('active', 'int');
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


class AccessManager extends ItemManager {
	private static $_instance;
	
	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_modules');

		$this->addProperty('id', 'int');
		$this->addProperty('username', 'varchar');
		$this->addProperty('password', 'varchar');
		$this->addProperty('fullname', 'varchar');
		$this->addProperty('level', 'smallint');
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


class AdministratorManager extends ItemManager {
	private static $_instance;
	
	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_access');

		$this->addProperty('id', 'int');
		$this->addProperty('username', 'varchar');
		$this->addProperty('password', 'varchar');
		$this->addProperty('fullname', 'varchar');
		$this->addProperty('level', 'int');
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