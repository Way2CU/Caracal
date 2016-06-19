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

		$this->addProperty('id', 'int');
		$this->addProperty('address', 'varchar');
		$this->addProperty('article', 'int');
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
