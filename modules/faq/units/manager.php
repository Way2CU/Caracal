<?php

/**
 * FAQ Question Manager
 * @author Mladen Mijatov
 */

class QuestionManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('faq');

		$this->addProperty('id', 'int');
		$this->addProperty('question', 'ml_text');
		$this->addProperty('answer', 'ml_text');
		$this->addProperty('visible', 'boolean');
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
