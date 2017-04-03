<?php

/**
 * Shop Supplier Handler
 *
 * Provides functionality for associating suppliers with individual
 * items.
 */

namespace Modules\Shop\Supplier;

use \URL;
use \TemplateHandler;


class Handler {
	private static $_instance;

	const SUB_ACTION = 'suppliers';

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		$this->parent = $parent;
		$this->name = $this->parent->name;
		$this->path = $this->parent->path;
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance($parent) {
		if (!isset(self::$_instance))
			self::$_instance = new self($parent);

		return self::$_instance;
	}

	/**
	 * Transfer control to group
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transfer_control($params = array(), $children = array()) {
		$action = isset($params['sub_action']) ? $params['sub_action'] : null;

		switch ($action) {
			case 'add':
				$this->add_supplier();
				break;

			case 'change':
				$this->change_supplier();
				break;

			case 'save':
				$this->save_supplier();
				break;

			case 'delete':
				$this->delete_supplier();
				break;

			case 'delete_commit':
				$this->delete_supplier_commit();
				break;

			default:
				$this->show_suppliers();
				break;
		}
	}

	/**
	 * Show window content for managing suppliers.
	 */
	private function show_suppliers() {
		$template = new TemplateHandler('supplier_list.xml', $this->path.'templates/');

		$params = array(
					'link_new' => URL::make_hyperlink(
									$this->parent->get_language_constant('add_supplier'),
									window_Open( // on click open window
										'shop_supplier_add',
										360,
										$this->parent->get_language_constant('title_supplier_add'),
										true, true,
										backend_UrlMake($this->name, self::SUB_ACTION, 'add')
									)
								),
					);

		// register tag handler
		$template->register_tag_handler('cms:supplier_list', $this, 'tag_SupplierList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for adding new supplier to the system.
	 */
	private function add_supplier() {
	}

	/**
	 * Show supplier editing interface.
	 */
	private function change_supplier() {
	}

	/**
	 * Save new or existing supplier data to database.
	 */
	private function save_supplier() {
	}

	/**
	 * Show confirmation dialog before removing supplier.
	 */
	private function delete_supplier() {
	}

	/**
	 * Perform supplier removal. Items associated with the
	 * supplier won't be affected, instead value will be reset to 0
	 * for those items.
	 */
	private function delete_supplier_commit() {
	}

	/**
	 * Function for rendering list of suppliers.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_SupplierList($tag_params, $children) {
	}
}


?>
