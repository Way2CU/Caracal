<?php

/**
 * Kodkod Integration
 *
 * This module is used as adapter for communication with Kodkod service. It provides
 * various third party integrations as well as authentication mechanism.
 */
use Core\Module;
use Core\Session\Manager as Session;
use Modules\Kodkod\Mechanism;

require_once('units/login_mechanism.php');


class kodkod extends Module {
	private static $_instance;
	private $login_mechanism;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);

		// create login mechanism
		$this->login_mechanism = new Mechanism();
		Session::register_login_mechanism('kodkod', $this->login_mechanism);
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
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
	public function transfer_control($params = array(), $children = array()) {
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function initialize() {
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
	}
}

?>
