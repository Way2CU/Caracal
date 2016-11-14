<?php

/**
 * YouTube Implmenetation Module
 * Group Membership Manager
 *
 * @copyright Way2CU, 2011.
 * @author MeanEYE
 */

class YouTube_MembershipManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('youtube_group_membership');

		$this->add_property('id', 'int');
		$this->add_property('group', 'int');
		$this->add_property('video', 'int');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}

?>
