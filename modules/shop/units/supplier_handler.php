<?php

/**
 * Shop Supplier Handler
 *
 * Provides functionality for associating suppliers with individual
 * items.
 */

namespace Modules\Shop\Supplier;

require_once('supplier_manager.php');

use \URL;
use \TemplateHandler;
use Modules\Shop\Item\Manager as ItemManager;


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
		$template = new TemplateHandler('supplier_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, self::SUB_ACTION, 'save'),
					'cancel_action'	=> window_Close('shop_supplier_add')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show supplier editing interface.
	 */
	private function change_supplier() {
		// get supplier id
		$id = fix_id($_REQUEST['id']);

		// get item from the database
		$manager = Manager::get_instance();
		$item = $manager->get_single_item(
				$manager->get_field_names(),
				array('id' => $id)
			);

		// make sure specified supplier exists
		if (!is_object($item))
			return;

		// load template
		$template = new TemplateHandler('supplier_change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'id'			=> $item->id,
					'name'			=> $item->name,
					'phone'			=> $item->phone,
					'email'			=> $item->email,
					'url'			=> $item->url,
					'form_action'	=> backend_UrlMake($this->name, self::SUB_ACTION, 'save'),
					'cancel_action'	=> window_Close('shop_supplier_change')
				);

		// render form template
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save new or existing supplier data to database.
	 */
	private function save_supplier() {
		// get supplier id
		$id = null;
		if (isset($_REQUEST['id']))
			$id = fix_id($_REQUEST['id']);

		// get data from request
		$data = array(
				'name'     => escape_chars($_REQUEST['name']),
				'phone'    => escape_chars($_REQUEST['phone']),
				'email'    => escape_chars($_REQUEST['email']),
				'url'      => escape_chars($_REQUEST['url'])
			);

		// store or update data in database
		$manager = Manager::get_instance();
		if (is_null($id)) {
			// insert new supplier data
			$manager->insert_item($data);
			$window = 'shop_supplier_add';

		} else {
			// update existing data
			$manager->update_items($data, array('id' => $id));
			$window = 'shop_supplier_change';
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->parent->get_language_constant('message_supplier_saved'),
					'button'	=> $this->parent->get_language_constant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('shop_suppliers')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show confirmation dialog before removing supplier.
	 */
	private function delete_supplier() {
		global $language;

		// get supplier data
		$id = fix_id($_REQUEST['id']);
		$manager = Manager::get_instance();
		$item = $manager->get_single_item(array('name'), array('id' => $id));

		// load confirmation tempalte
		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		// prepare parameters
		$params = array(
					'message'		=> $this->parent->get_language_constant('message_supplier_delete'),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->parent->get_language_constant('delete'),
					'no_text'		=> $this->parent->get_language_constant('cancel'),
					'yes_action'	=> window_LoadContent(
											'shop_supplier_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', self::SUB_ACTION),
												array('sub_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('shop_supplier_delete')
				);

		// render confirmation template
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Perform supplier removal. Items associated with the
	 * supplier won't be affected, instead value will be reset to 0
	 * for those items.
	 */
	private function delete_supplier_commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = Manager::get_instance();
		$item_manager = ItemManager::get_instance();

		// clear supplier association with items and remove the supplier
		$manager->delete_items(array('id' => $id));
		$item_manager->update_items(array('supplier' => 0), array('supplier' => $id));

		// load message template
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		// configure parameters
		$params = array(
					'message'	=> $this->parent->get_language_constant('message_supplier_deleted'),
					'button'	=> $this->parent->get_language_constant('close'),
					'action'	=> window_Close('shop_supplier_delete').';'.window_ReloadContent('shop_suppliers')
				);

		// render message
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Function for rendering list of suppliers.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_SupplierList($tag_params, $children) {
		global $section;

		$manager = Manager::get_instance();
		$conditions = array();

		// get suppliers from the database
		$items = $manager->get_items($manager->get_field_names(), $conditions);

		if (count($items) == 0)
			return;

		// load template
		$template = $this->parent->load_template($tag_params, 'supplier_list_item.xml');

		// render tags
		foreach ($items as $item) {
			$params = array(
					'id'    => $item->id,
					'name'  => $item->name,
					'phone' => $item->phone,
					'email' => $item->email,
					'url'   => $item->url
				);

			if ($section == 'backend' || $section == 'backend_module') {
				$params['item_change'] = URL::make_hyperlink(
									$this->parent->get_language_constant('change'),
									window_Open(
										'shop_supplier_change', 	// window id
										360,				// width
										$this->parent->get_language_constant('title_supplier_change'), // title
										true, true,
										URL::make_query(
											'backend_module',
											'transfer_control',
											array('module', $this->name),
											array('backend_action', self::SUB_ACTION),
											array('sub_action', 'change'),
											array('id', $item->id)
										))
								);

				$params['item_delete'] = URL::make_hyperlink(
										$this->parent->get_language_constant('delete'),
										window_Open(
											'shop_supplier_delete', 	// window id
											400,				// width
											$this->parent->get_language_constant('title_supplier_delete'), // title
											false, false,
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', self::SUB_ACTION),
												array('sub_action', 'delete'),
												array('id', $item->id)
											)
										)
									);
			}

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}
}


?>
