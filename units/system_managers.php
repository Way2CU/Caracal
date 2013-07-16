<?php

/**
 * System Managers
 *
 * Author: Mladen Mijatov
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


class AdministratorManager extends ItemManager {
	private static $_instance;
	const SALT = '5sWeaGqp53loh7hYFDEjBi6VHMYDznrx5ITUF9Bzni7WXU9IJOBmr/80u2vjklSfhK+lvPBel/T9';

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_access');

		$this->addProperty('id', 'int');
		$this->addProperty('username', 'varchar');
		$this->addProperty('password', 'varchar');
		$this->addProperty('fullname', 'varchar');
		$this->addProperty('email', 'varchar');
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

class LoginRetryManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('system_retries');

		$this->addProperty('id', 'int');
		$this->addProperty('day', 'int');
		$this->addProperty('address', 'varchar');
		$this->addProperty('count', 'int');
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
	 * Purge outdated entries.
	 */
	private function purgeOutdated() {
		$this->deleteData(array(
						'day' => array(
							'operator'	=> '!=',
							'value'		=> date('j')
						)));
	}

	/**
	 * Get number of retries for current or specified address.
	 *
	 * @param string $address
	 * @return integer
	 */
	public function getRetryCount($address=null) {
		if (is_null($address))
			$address = $_SERVER['REMOTE_ADDR'];

		// purge outdated entries
		$this->purgeOutdated();

		// try to get existing entry
		$result = 0;
		$entry = $this->getSingleItem(array('count'), array('address' => $address));

		if (is_object($entry))
			$result = $entry->count;

		return $result;
	}

	/**
	 * Increase number of retries for current or specified address.
	 *
	 * @param string $address
	 * @return integer
	 */
	public function increaseCount($address=null) {
		if (is_null($address))
			$address = $_SERVER['REMOTE_ADDR'];

		// get existing entry if it exists
		$entry = $this->getSingleItem($this->getFieldNames(), array('address' => $address));

		if (is_object($entry)) {
			// don't allow counter to go over 10
			$count = ($entry->count < 10) ? $entry->count+1 : 10;
			$this->updateData(
							array('count'	=> $count),
							array('id'		=> $entry->id)
						);
			$result = $count;

		} else {
			// there's no existing entry so we create one
			$this->insertData(array(
								'day'		=> date('j'),
								'address'	=> $_SERVER['REMOTE_ADDR'],
								'count'		=> 1
							));
			$result = 1;
		}

		return $result;
	}

	/**
	 * Clear number of retries for current or specified address.
	 *
	 * @param string $address
	 */
	public function clearAddress($address=null) {
		if (is_null($address))
			$address = $_SERVER['REMOTE_ADDR'];

		$this->deleteData(array('address' => $address));
	}
}
?>
