<?php

/**
 * Link Membership Manager
 *
 * Manager used to organize connections between links and groups in
 * many-to-many relation pattern.
 *
 * Author: Mladen Mijatov
 */
namespace Modules\Links;

use \ItemManager as ItemManager;


class MembershipManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('link_membership');

		$this->add_property('id', 'int');
		$this->add_property('link', 'int');
		$this->add_property('group', 'int');
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
