<?php

class GalleryManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('gallery');

		$this->addProperty('id', 'int');
		$this->addProperty('text_id', 'varchar');
		$this->addProperty('group', 'int');
		$this->addProperty('title', 'ml_varchar');
		$this->addProperty('description', 'ml_text');
		$this->addProperty('size', 'bigint');
		$this->addProperty('filename', 'varchar');
		$this->addProperty('timestamp', 'timestamp');
		$this->addProperty('visible', 'boolean');
		$this->addProperty('protected', 'boolean');
		$this->addProperty('slideshow', 'boolean');
	}

	/**
	 * Override function in order to remove required files along with database data
	 *
	 * @param array $conditionals
	 * @param integer $limit
	 */
	function deleteData($conditionals, $limit=null) {
		global $site_path;

		$items = $this->getItems(array('filename'), $conditionals);

		$image_path = _BASEPATH.'/'.$site_path.'gallery/images/';
		$thumbnail_path = _BASEPATH.'/'.$site_path.'gallery/thumbnails/';

		if (count($items) > 0)
			foreach ($items as $item) {
				unlink($image_path.$item->filename);
				array_map('unlink', glob($thumbnail_path.'*'.$item->filename));
			}

		parent::deleteData($conditionals, $limit);
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
