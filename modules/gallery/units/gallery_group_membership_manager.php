<?php

class GalleryGroupMembershipManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('gallery_group_membership');

		$this->add_property('id', 'int');
		$this->add_property('group', 'int');
		$this->add_property('container', 'int');
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
