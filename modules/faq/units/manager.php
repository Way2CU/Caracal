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

		$this->add_property('id', 'int');
		$this->add_property('question', 'ml_text');
		$this->add_property('answer', 'ml_text');
		$this->add_property('visible', 'boolean');
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
