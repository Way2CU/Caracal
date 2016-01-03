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
			case 'add':
				$this->add();
				break;

			case 'change':
				$this->change();
				break;

			case 'save':
				$this->save();
				break;

			case 'delete':
				$this->delete();
				break;

			case 'delete_commit':
				$this->delete_commit();
				break;

			default:
				$this->show();
				break;
		}
	}

	/**
	 * Show item properties.
	 */
	private function show() {
		$template = new TemplateHandler('item_property_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		// prepare template params
		$params = array();

		// parse and show template
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show window for adding new item property.
	 */
	private function add() {
		// load template
		$template = new TemplateHandler('item_property_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		// prepare template parameters
		$params = array(
					'form_action'	=> backend_UrlMake($this->name, self::SUB_ACTION, 'save'),
					'cancel_action'	=> window_Close('shop_item_property_add')
				);

		// parse and show template
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show window for changing existing item property.
	 */
	private function change() {
		// get manager and data
		$id = fix_id($_REQUEST['id']);
		$manager = Managers\Property::getInstance();
		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		// bail if there's no item with specified id found
		if (!is_object($item))
			return;

		// load template
		$template = new TemplateHandler('item_property_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		// prepare template parameters
		$params = array(
					'id'            => $item->id,
					'text_id'       => $item->text_id,
					'name'          => $item->name,
					'type'          => $item->type,
					'values'        => $item->values,
					'form_action'   => backend_UrlMake($this->name, self::SUB_ACTION, 'save'),
					'cancel_action' => window_Close('shop_item_property_add')
				);

		// parse and show template
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save changes or new item property.
	 */
	private function save() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$item = fix_id($_REQUEST['item']);
		$data = array(
				'text_id' => fix_chars($_REQUEST['text_id']),
				'name'    => $this->parent->getMultilanguageField('name'),
				'type'    => fix_id($_REQUEST['type']),
				'values'  => escape_chars($_REQUEST['values'])
			);
		$manager = Managers\Property::getInstance();
		$membership_manager = Managers\PropertyMembership::getInstance();

		if (is_null($id)) {
			// insert new data
			$manager->insertData($data);

			// store property membership
			$membership_manager->insertData(array(
					'item'     => $item,
					'property' => $managet->getInsertedID()
				));
			$window = 'shop_item_property_add';

		} else {
			// update existing data
			$manager->updateData($data, array('id' => $id));
			$window = 'shop_item_property_change';
		}

		// show message
		$template = $this->parent->loadTemplate($tag_params, 'message.xml');

		$params = array(
					'message'	=> $this->parent->getLanguageConstant('message_item_property_saved'),
					'button'	=> $this->parent->getLanguageConstant('close'),
					'action'	=> window_Close($window)
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show confirmation dialog before removing item property.
	 */
	private function delete() {
		$id = fix_id($_REQUEST['id']);
		$manager = Managers\Property::getInstance();
		$membership_manager = Managers\PropertyMembership::getInstance();

		// load template
		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->parent->name);

		// prepare parameters
		$params = array(
					'message'		=> $this->parent->getLanguageConstant('message_item_property_delete'),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->parent->getLanguageConstant('delete'),
					'no_text'		=> $this->parent->getLanguageConstant('cancel'),
					'yes_action'	=> window_LoadContent(
											'shop_item_property_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', self::SUB_ACTION),
												array('sub_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('shop_item_property_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Perform property removal
	 */
	private function delete_commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = Managers\Property::getInstance();
		$membership_manager = Managers\PropertyMembership::getInstance();

		// remove property and its membership
		$manager->deleteData(array('id' => $id));
		$membership_manager->deleteData(array('property' => $id));
		$window = 'shop_item_property_delete';

		// show message
		$template = $this->parent->loadTemplate($tag_params, 'message.xml');
		$params = array(
					'message'	=> $this->parent->getLanguageConstant('message_item_propety_deleted'),
					'button'	=> $this->parent->getLanguageConstant('close'),
					'action'	=> window_Close($window).';'.window_ReloadContent('shop_item_properties')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
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
		if (isset($tag_params['id']) {
			$conditions['id'] = fix_id($tag_params['id']);

		} else if (isset($tag_params['text_id'])) {
			$conditions['text_id'] = fix_id($tag_params['text_id']);

		} else if (isset($tag_params['item']) {
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
