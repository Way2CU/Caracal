<?php

/**
 * Manager class for file downloads. This class implements standard
 * behavior of any other manager with exception that upon removal of
 * items from database it also removes files from hard disk.
 *
 * Author: Mladen Mijatov
 */
namespace Modules\Downloads;


class Manager extends \ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('downloads');

		$this->add_property('id', 'int');
		$this->add_property('text_id', 'varchar');
		$this->add_property('category', 'int');
		$this->add_property('name', 'ml_varchar');
		$this->add_property('description', 'ml_text');
		$this->add_property('count', 'int');
		$this->add_property('filename', 'varchar');
		$this->add_property('size', 'int');
		$this->add_property('visible', 'boolean');
		$this->add_property('timestamp', 'timestamp');
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
	 * Override function in order to remove required files along with database data
	 *
	 * @param array $conditionals
	 * @param integer $limit
	 */
	function delete_items($conditionals, $limit=null) {
		$items = $this->get_items(array('filename'), $conditionals);

		$path = downloads::get_instance()->file_path;

		if (count($items) > 0)
			foreach ($items as $item)
				unlink($path.$item->filename);

		parent::delete_items($conditionals, $limit);
	}
}

?>
