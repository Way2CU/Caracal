<?php

/**
 * Article vote manager class.
 * Author: Mladen Mijatov
 */
namespace Modules\Articles;

use \ItemManager as ItemManager;


class VoteManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('article_votes');

		$this->add_property('id', 'int');
		$this->add_property('address', 'varchar');
		$this->add_property('article', 'int');
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
