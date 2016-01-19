<?php

/**
 * Handler class for item properties.
 *
 * Author: Mladen Mijatov
 */
namespace Modules\Shop\Handlers;

require_once('property_manager.php');

use \TemplateHandler as TemplateHandler;


class Property {
	private static $instance;
	private $parent;
	private $name;
	private $path;

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		$this->parent = $parent;
		$this->name = $this->_parent->name;
		$this->path = $this->_parent->path;
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance($parent) {
		if (!isset(self::$instance))
			self::$instance = new self($parent);

		return self::$instance;
	}

	/**
	 * Save properties for specified item.
	 *
	 * @param integer $item_id
	 * @return integer
	 */
	public function save_properties($item_id) {
		$manager = Managers\Property::getInstance();

		// remove existing properties
		$manager->deleteData(array('item' => $item_id));

		// insert new data
		$count = 0;
		foreach ($_REQUEST as $key => $value) {
			if (substr($key, 0, 14) != 'property_data_')
				continue;

			// decode data prepared by user interface
			$decoded = json_decode($value, true);

			$data = array(
					'item'    => $item_id,
					'text_id' => $decoded['text_id'],
					'name'    => $decoded['name'],
					'type'    => fix_id($decoded['type']),
					'value'   => serialize($decoded['value'])
				);

			$manager->insertData($data);
			$count++;
		}

		return $count;
	}

	/**
	 * Tag handler for list of item properties.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_PropertyList($tag_params, $children) {
		$manager = Managers\Property::getInstance();
		$membership_manager = Managers\PropertyMembership::getInstance();
		$conditions = array();

		// get item properties from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// we need items to display
		if (count($items) == 0)
			return;

		// create template
		$template = $this->parent->loadTemplate($tag_params, 'item_property_list_item.xml');

		foreach ($items as $item) {
			// prepare parameters
			$params = array(
				'id'         => $item->id,
				'text_id'    => $item->text_id,
				'name'       => $item->name,
				'type'       => $item->type,
				'values'     => unserialize($item->values),
				'raw_values' => $item->values
			);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}
}

?>
