<?php

/**
 * Handler class for coupon related operations.
 */
namespace Modules\Shop\Promotion;

require_once('coupons_manager.php');

use \TemplateHandler as TemplateHandler;


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

		// create main menu
		if ($section == 'backend') {
			$backend = \backend::getInstance();
			$method_menu = $backend->getMenu('shop_special_offers');

			if (!is_null($method_menu))
				$method_menu->addChild('', new \backend_MenuItem(
									$this->parent->getLanguageConstant('menu_coupons'),
									url_GetFromFilePath($this->path.'images/coupons.svg'),

									window_Open( // on click open window
												'shop_coupons',
												450,
												$this->parent->getLanguageConstant('title_coupons'),
												true, true,
												backend_UrlMake($this->name, self::SUB_ACTION, 'show')
											),
									$level=5
								));
		}
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance($parent) {
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
	public function transferControl($params = array(), $children = array()) {
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
			default:
				$this->show_coupons();
				break;
		}
	}

	/**
	 * Show coupons management form.
	 */
	private function show_coupons() {
		$template = new TemplateHandler('coupon_list.xml', $this->path.'templates/');

		$params = array(
					'link_new' => url_MakeHyperlink(
										$this->parent->getLanguageConstant('add_coupon'),
										window_Open( // on click open window
											'shop_coupon_add',
											350,
											$this->parent->getLanguageConstant('title_coupon_add'),
											true, true,
											backend_UrlMake($this->name, self::SUB_ACTION, 'add')
										)
									)
					);

		// register tag handler
		$template->registerTagHandler('cms:coupon_list', $this, 'tag_CouponList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
 	 * Show form for adding coupons.
	 */
	private function add_coupon() {
	}

	/**
	 * Show form for changing coupons.
	 */
	private function change_coupon() {
	}

	/**
	 * Save new or changed coupon data.
	 */
	private function save_coupon() {
	}

	/**
	 * Show confirmation form before removing coupon.
	 */
	private function delete_coupon() {
	}

	/**
 	 * Perform coupon data removal.
	 */
	private function delete_coupon_commit() {
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
	}
}

?>
