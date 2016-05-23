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
												400,
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
	 * Render coupon list tag.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CouponList($tag_params, $children) {
	}
}

?>
