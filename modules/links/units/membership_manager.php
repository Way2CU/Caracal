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

		$this->addProperty('id', 'int');
		$this->addProperty('link', 'int');
		$this->addProperty('group', 'int');
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
