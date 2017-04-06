<?php

class GalleryManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('gallery');

		$this->add_property('id', 'int');
		$this->add_property('text_id', 'varchar');
		$this->add_property('group', 'int');
		$this->add_property('title', 'ml_varchar');
		$this->add_property('description', 'ml_text');
		$this->add_property('size', 'bigint');
		$this->add_property('filename', 'varchar');
		$this->add_property('timestamp', 'timestamp');
		$this->add_property('visible', 'boolean');
		$this->add_property('protected', 'boolean');
		$this->add_property('slideshow', 'boolean');
	}

	/**
	 * Override function in order to remove required files along with database data
	 *
	 * @param array $conditionals
	 * @param integer $limit
	 */
	function delete_items($conditionals, $limit=null) {
		global $site_path;

		$items = $this->get_items(array('filename'), $conditionals);

		$image_path = _BASEPATH.'/'.$site_path.'gallery/images/';
		$thumbnail_path = _BASEPATH.'/'.$site_path.'gallery/thumbnails/';

		if (count($items) > 0)
			foreach ($items as $item) {
				unlink($image_path.$item->filename);
				array_map('unlink', glob($thumbnail_path.'*'.$item->filename));
			}

		parent::delete_items($conditionals, $limit);
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
