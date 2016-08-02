<?php

/**
 * Tools Module
 *
 * Collection of small functions that requre code on the server-side.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;


class tools extends Module {
	private static $_instance;

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
	public function transfer_control($params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'check_domain':
					$this->json_CheckDomain();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function on_init() {
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function on_disable() {
	}

	/**
	 * Check if domain exists.
	 */
	private function json_CheckDomain() {
		$result = false;
		$domain = fix_chars($_REQUEST['domain']);

		// prepare header
		$header = "HEAD / HTTP/1.1\n";
		$header .= "User-Agent: Caracal\n";
		$header .= "Host: ".$domain."\n";
		$header .= "Connection: close\n\n";

		// open socket
		$socket = fsockopen($domain, 80, $error_number, $error_string, 5);

		if ($socket) {
			fputs($socket, $header);
			$raw_data = stream_get_contents($socket, 1024);

			fclose($socket);
			$result = true;
		}

		// show result
		print json_encode($result);
	}
}

?>
