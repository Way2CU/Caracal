<?php

/**
 * Handle shop discounts.
 *
 * Author: Mladen Mijatov
 */

namespace Modules\Shop\Promotion;

require_once('discount_manager.php');


class DiscountHandler {
	private static $_instance;
	private $parent;
	private $name;
	private $path;

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
				$this->addDiscount();
				break;

			case 'change':
				$this->changeDiscount();
				break;

			case 'save':
				$this->saveDiscount();
				break;

			case 'delete':
				$this->deleteDiscount();
				break;

			case 'delete_commit':
				$this->deleteDiscount_Commit();
				break;

			default:
				$this->showDiscounts();
				break;
		}
	}

	/**
	 * Show list of discounts
	 */
	private function showDiscounts() {
		$template = new TemplateHandler('item_list.xml', $this->path.'templates/');

		$params = array(
					'link_new' => url_MakeHyperlink(
										$this->parent->getLanguageConstant('add_discount'),
										window_Open( // on click open window
											'shop_discounts_add',
											505,
											$this->parent->getLanguageConstant('title_discounts_add'),
											true, true,
											backend_UrlMake($this->name, 'discounts', 'add')
										)
									),
					);

		// register tag handler
		$template->registerTagHandler('cms:item_list', $this, 'tag_ItemList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for adding new discount
	 */
	private function addDiscount() {
		$template = new TemplateHandler('discount_add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'discounts', 'save'),
					'cancel_action'	=> window_Close('shop_discount_add')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show form for changing existing discount
	 */
	private function changeDiscount() {
		$id = fix_id($_REQUEST['id']);
		$manager = ShopDiscountManager::getInstance();

		$item = $manager->getSingleItem($manager->get
	}

	/**
	 * Save new or changed discount data
	 */
	private function saveDiscount() {
		$manager = ShopDiscountManager::getInstance();

		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$data = array(
				'name'			=> $this->parent->getMultilanguageField('name'),
				'description'	=> $this->parent->getMultilanguageField('description'),
				'type'			=> fix_id($_REQUEST['type']),
				'percent'		=> fix_chars($_REQUEST['percent']))
			);

		if (is_null($id)) {
			$manager->insertData($data);
			$window = "shop_discounts_add";
		} else {
			$manager->updateData($data, array('id' => $id))
			$window = "shop_discounts_change";
		}

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->parent->getLanguageConstant('message_discount_saved'),
					'button'	=> $this->parent->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('shop_discounts')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * show confirmation form before deleting discount
	 */
	private function deleteDiscount() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = ShopItemManager::getInstance();

		$item = $manager->getSingleItem(array('name'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->parent->name);

		$params = array(
					'message'		=> $this->parent->getLanguageConstant("message_item_delete"),
					'name'			=> $item->name[$language],
					'yes_text'		=> $this->parent->getLanguageConstant("delete"),
					'no_text'		=> $this->parent->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'shop_item_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'items'),
												array('sub_action', 'delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('shop_item_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * actually delete discount from the system
	 */
	private function deleteDiscount_Commit() {
	}

	/**
	 * Handle tag list tag drawing
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Discount($tag_params, $children) {
	}

	/**
	 * Handle tag list tag drawing
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_DiscountList($tag_params, $children) {
	}

}

?>
