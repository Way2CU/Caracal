<?php

/**
 * Handler class for item properties.
 *
 * Author: Mladen Mijatov
 */
namespace Modules\Shop\Handlers;

require_once('property_manager.php');
require_once('property_membership_manager.php');

use \TemplateHandler as TemplateHandler;


class Property {
	private static $instance;
	private $parent;
	private $name;
	private $path;

	const SUB_ACTION = 'properties';

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
	 * Transfer control to group
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params = array(), $children = array()) {
		$action = isset($params['sub_action']) ? $params['sub_action'] : null;

		switch ($action) {
			default:
				break;
		}
	}

	/**
	 * Tag handler for item property.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Property($tag_params, $children) {
		$manager = Managers\Property::getInstance();
		$membership_manager = Managers\PropertyMembership::getInstance();
		$conditions = array();

		// get id or one of the properties associated with item
		if (isset($tag_params['id'])) {
			$conditions['id'] = fix_id($tag_params['id']);

		} else if (isset($tag_params['text_id'])) {
			$conditions['text_id'] = fix_id($tag_params['text_id']);

		} else if (isset($tag_params['item'])) {
			$list = $membership_manager->getItems(
					array('property'),
					array('item' => fix_id($tag_params['item']))
				);

			// prepare list of properties available for display
			$id_list = array();
			foreach ($list as $membership)
				$id_list[] = $membership->property;
		}

		// get property for specified conditions
		$item = $manager->getSingleItem($manager->getFieldNames(), $conditions);

		if (!is_object($item))
			return;

		// load template
		$template = $this->parent->loadTemplate($tag_params, 'item_property.xml');

		// prepare parameters
		$params = array(
			'id'         => $item->id,
			'text_id'    => $item->text_id,
			'name'       => $item->name,
			'type'       => $item->type,
			'values'     => unserialize($item->values),
			'raw_values' => $item->values
		);

		// parse and display template
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
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
