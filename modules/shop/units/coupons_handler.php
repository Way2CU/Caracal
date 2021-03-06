<?php

/**
 * Handler class for coupon related operations.
 */
namespace Modules\Shop\Promotion;

use Core\Events;
use URL;
use TemplateHandler;

require_once('coupons_manager.php');
require_once('coupon_codes_manager.php');


class CouponHandler {
	private static $_instance;
	private $parent;
	private $name;
	private $path;

	const SUB_ACTION = 'coupons';

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		global $section;

		$this->parent = $parent;
		$this->name = $this->parent->name;
		$this->path = $this->parent->path;

		// connect events
		Events::connect('backend', 'add-menu-items', 'add_menu_items', $this);
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
				$this->add_coupon();
				break;

			case 'change':
				$this->change_coupon();
				break;

			case 'save':
				$this->save_coupon();
				break;

			case 'delete':
				$this->delete_coupon();
				break;

			case 'delete_commit':
				$this->delete_coupon_commit();
				break;

			case 'show':
				$this->show_coupons();
				break;

			case 'codes':
				$this->show_codes();
				break;

			case 'codes_generate':
				$this->generate_codes();
				break;

			case 'codes_add':
				$this->add_code();
				break;

			case 'codes_change':
				$this->change_code();
				break;

			case 'codes_save':
				$this->save_code();
				break;
		}
	}

	/**
	 * Add items to backend menu.
	 */
	public function add_menu_items() {
		$backend = \backend::get_instance();
		$method_menu = $backend->getMenu('shop_special_offers');

		if (!is_null($method_menu))
			$method_menu->addChild('', new \backend_MenuItem(
								$this->parent->get_language_constant('menu_coupons'),
								$this->path.'images/coupons.svg',

								window_Open( // on click open window
											'shop_coupons',
											450,
											$this->parent->get_language_constant('title_coupons'),
											true, true,
											URL::make_query($this->name, self::SUB_ACTION, 'show')
										),
								$level=5
							));
	}

	/**
	 * Show coupons management form.
	 */
	private function show_coupons() {
		$template = new TemplateHandler('coupon_list.xml', $this->path.'templates/');

		$params = array(
					'link_new' => URL::make_hyperlink(
							$this->parent->get_language_constant('add_coupon'),
							window_Open( // on click open window
								'shop_coupon_add',
								430,
								$this->parent->get_language_constant('title_coupon_add'),
								true, true,
								URL::make_query($this->name, self::SUB_ACTION, 'add')
							))
					);

		// register tag handler
		$template->register_tag_handler('cms:list', $this, 'tag_CouponList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
 	 * Show form for adding coupons.
	 */
	private function add_coupon() {
		$template = new TemplateHandler('coupon_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, self::SUB_ACTION, 'save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for changing coupons.
	 */
	private function change_coupon() {
		$id = fix_id($_REQUEST['id']);
		$manager = CouponsManager::get_instance();

		// get item from the database
		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		// make sure item is valid
		if (!is_object($item))
			return;

		// load template
		$template = new TemplateHandler('coupon_change.xml', $this->path.'templates/');

		// prepare parameters
		$params = array(
			'id'            => $item->id,
			'text_id'       => $item->text_id,
			'name'          => $item->name,
			'has_limit'     => $item->has_limit,
			'has_timeout'   => $item->has_timeout,
			'limit'         => $item->limit,
			'timeout'       => $item->timeout,
			'form_action'   => backend_UrlMake($this->name, self::SUB_ACTION, 'save'),
			);

		// parse template
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save new or changed coupon data.
	 */
	private function save_coupon() {
		$manager = CouponsManager::get_instance();
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$data = array(
				'text_id'     => escape_chars($_REQUEST['text_id']),
				'name'        => $this->parent->get_multilanguage_field('name'),
				'has_limit'   => $this->parent->get_boolean_field('has_limit') ? 1 : 0,
				'has_timeout' => $this->parent->get_boolean_field('has_timeout') ? 1 : 0,
				'limit'       => fix_id($_REQUEST['limit']),
				'timeout'     => escape_chars($_REQUEST['timeout'])
			);

		// store data
		if (is_null($id)) {
			$window = 'shop_coupon_add';
			$manager->insertData($data);

		} else {
			$window = 'shop_coupon_change';
			$manager->updateData($data,	array('id' => $id));
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->parent->get_language_constant('message_coupon_saved'),
					'button'	=> $this->parent->get_language_constant('close'),
					'action'	=> window_Close($window).';'.window_ReloadContent('shop_coupons'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show confirmation form before removing coupon.
	 */
	private function delete_coupon() {
		global $language;

		// get coupon from the database
		$id = fix_id($_REQUEST['id']);
		$manager = CouponsManager::get_instance();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		// load template
		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');

		// prepare parameters
		$params = array(
					'message'		=> $this->parent->get_language_constant('message_coupon_delete'),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->parent->get_language_constant('delete'),
					'no_text'		=> $this->parent->get_language_constant('cancel'),
					'yes_action'	=> window_LoadContent(
											'shop_coupon_delete',
											URL::make_query(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', self::SUB_ACTION),
												array('sub_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('shop_coupon_delete')
				);

		// parse template
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
 	 * Perform coupon data removal.
	 */
	private function delete_coupon_commit() {
		// remove data
		$id = fix_id($_REQUEST['id']);
		$manager = CouponsManager::get_instance();
		$code_manager = CouponCodesManager::get_instance();

		$manager->deleteData(array('id' => $id));
		$code_manager->deleteData(array('coupon' => $id));

		// show confirmation message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$params = array(
					'message'	=> $this->parent->get_language_constant('message_coupon_deleted'),
					'button'	=> $this->parent->get_language_constant('close'),
					'action'	=> window_Close('shop_coupon_delete').';'.window_ReloadContent('shop_coupons')
				);

		// parse message template
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show codes management window.
	 */
	private function show_codes() {
		$coupon_id = fix_id($_REQUEST['id']);
		$template = new TemplateHandler('coupon_code_list.xml', $this->path.'templates/');

		$params = array(
					'coupon'        => $coupon_id,
					'form_action'   => backend_UrlMake($this->name, self::SUB_ACTION, 'codes_save'),
					'link_new'      => URL::make_hyperlink(
							$this->parent->get_language_constant('add_code'),
							window_Open( // on click open window
								'shop_coupon_codes_add',
								300,
								$this->parent->get_language_constant('title_coupon_code_add'),
								true, true,
								backend_UrlMake($this->name, self::SUB_ACTION, 'codes_add')
							)),
					'link_generate' => URL::make_hyperlink(
							$this->parent->get_language_constant('generate_codes'),
							window_Open( // on click open window
								'shop_coupon_codes_generate',
								300,
								$this->parent->get_language_constant('title_coupon_code_generate'),
								true, true,
								backend_UrlMake($this->name, self::SUB_ACTION, 'codes_generate')
							))
					);

		// register tag handler
		$template->register_tag_handler('cms:list', $this, 'tag_CodeList');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for adding new code to the coupon.
	 */
	private function add_code() {
		$template = new TemplateHandler('coupon_code_add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:list', $this->parent, 'tag_DiscountList');

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, self::SUB_ACTION, 'save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show form for changing existing code in coupon.
	 */
	private function change_code() {
	}

	/**
	 * Show for for generating coupon codes.
	 */
	private function generate_codes() {
		$template = new TemplateHandler('coupon_code_generate.xml', $this->path.'templates/');
		$template->register_tag_handler('cms:list', $this->parent, 'tag_DiscountList');
		$template->set_mapped_module($this->name);

		$params = array(
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save new or changed coupon codes.
	 */
	private function save_code() {
		$coupon_id = fix_id($_REQUEST['coupon']);
		$manager = CouponCodesManager::get_instance();

		// collect all data for comparison
		$data = array();
		$codes = array();

		foreach ($_REQUEST as $key => $value)
			if (substr($key, 0, 5) == 'code_') {
				$index = substr($key, 5);

				// get discount value
				$discount = null;
				if (isset($_REQUEST['discount_'.$index]))
					$discount = fix_id($_REQUEST['discount_'.$index]);

				// store data for later use
				$data[] = array(
						'code'     => $value,
						'discount' => $discount
					);
				$codes[] = $value;
			}

		// remove all associated coupon codes which are not in final list
		$manager->deleteData(array(
					'coupon'       => $coupon_id,
					'code'         => array(
						'operator' => 'NOT IN',
						'value'    => $codes
					)));

		// get remaining codes
		$remaining = $manager->getItems(
				$manager->getFieldNames(),
				array('coupon' => $coupon_id)
			);
		$remaining_id_list = array();

		if (count($remaining) > 0)
			foreach ($remaining as $code)
				$remaining_id_list[$code->code] = $code->id;

		// update or insert data
		foreach ($data as $code_data) {
			if (array_key_exists($code_data['code'], $remaining_id_list)) {
				// update existing data
				$id = $remaining_id_list[$code_data['code']];
				$manager->updateData(
						array('discount' => $code_data['discount']),
						array('id' => $id)
					);

			} else {
				// insert new data
				$new_data = $code_data;
				$new_data['coupon'] = $coupon_id;
				$manager->insertData($new_data);
			}
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->parent->get_language_constant('message_coupon_code_saved'),
					'button'	=> $this->parent->get_language_constant('close'),
					'action'	=> window_Close('shop_coupon_codes')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Render single coupon tag.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Coupon($tag_params, $children) {
	}

	/**
	 * Render coupon list tag.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CouponList($tag_params, $children) {
		$manager = CouponsManager::get_instance();
		$conditions = array();

		// get items from the database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// load template
		$template = $this->parent->load_template($tag_params, 'coupon_list_item.xml');

		// parse template
		if (count($items) == 0)
			return;

		foreach ($items as $item) {
			$params = array(
				'id'          => $item->id,
				'text_id'     => $item->text_id,
				'name'        => $item->name,
				'has_limit'   => $item->has_limit,
				'has_timeout' => $item->has_timeout,
				'limit'       => $item->limit,
				'timeout'     => $item->timeout,
				'item_change' => URL::make_hyperlink(
						$this->parent->get_language_constant('change'),
						window_Open(
							'shop_coupon_change', 	// window id
							430,				// width
							$this->parent->get_language_constant('title_coupon_change'), // title
							true, true,
							URL::make_query(
								'transfer_control',
								'backend_module',
								array('module', $this->name),
								array('backend_action', self::SUB_ACTION),
								array('sub_action', 'change'),
								array('id', $item->id)
							)
						)),
				'item_delete' => URL::make_hyperlink(
						$this->parent->get_language_constant('delete'),
						window_Open(
							'shop_coupon_delete', 	// window id
							400,				// width
							$this->parent->get_language_constant('title_coupon_delete'), // title
							false, false,
							URL::make_query(
								'transfer_control',
								'backend_module',
								array('module', $this->name),
								array('backend_action', self::SUB_ACTION),
								array('sub_action', 'delete'),
								array('id', $item->id)
							)
						)),
				'item_codes' => URL::make_hyperlink(
						$this->parent->get_language_constant('codes'),
						window_Open(
							'shop_coupon_codes', 	// window id
							550,				// width
							$this->parent->get_language_constant('title_coupon_codes'), // title
							true, true,
							URL::make_query(
								'transfer_control',
								'backend_module',
								array('module', $this->name),
								array('backend_action', self::SUB_ACTION),
								array('sub_action', 'codes'),
								array('id', $item->id)
							)
						))
				);

			$template->set_local_params($params);
			$template->restore_xml();
			$template->parse();
		}
	}

	/**
 	 * Generator for single coupon code tag.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Code($tag_params, $children) {
	}

	/**
	 * Generator for list of codes.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CodeList($tag_params, $children) {
		$manager = CouponCodesManager::get_instance();
		$conditions = array();

		// get parameters
		if (isset($tag_params['coupon']))
			$conditions['coupon'] = fix_id($tag_params['coupon']); else
			$conditions['coupon'] = -1;

		// get items from the database
		define('SQL_DEBUG', 1);
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		// make sure we have items
		if (count($items) == 0)
			return;

		// load template
		$template = $this->parent->load_template($tag_params, 'coupon_code_list_item.xml');
		$template->register_tag_handler('cms:discount', $this->parent, 'tag_DiscountList');

		// parse template
		foreach ($items as $item) {
			$params = array(
				'id'          => $item->id,
				'coupon'      => $item->coupon,
				'code'        => $item->code,
				'times_used'  => $item->times_used,
				'timestamp'   => $item->timestamp,
				'discount'    => $item->discount,
				'item_delete' => URL::make_hyperlink(
							$this->parent->get_language_constant('delete'),
							'javascript: Caracal.Shop.delete_coupon_code(this);'
						)
			);

			$template->set_local_params($params);
			$template->restore_xml();
			$template->parse();
		}
	}
}

?>
