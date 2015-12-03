<?php

/**
 * Handler class for shop delivery addresses.
 */

namespace Modules\Shop;

use \ShopDeliveryAddressManager as DeliveryAddressManager;


class DeliveryAddressHandler {
	private static $_instance;
	private $_parent;

	/**
	 * Constructor
	 */
	protected function __construct($parent) {
		$this->_parent = $parent;
		$this->name = $this->_parent->name;
		$this->path = $this->_parent->path;
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
	}

	/**
	 * Handle drawing delivery address tag.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_DeliveryAddress($tag_params, $children) {
		$manager = DeliveryAddressManager::getInstance();
		$conditions = array();

		// get conditions
		if (isset($tag_params['id']))
			$conditions['id'] = fix_id($tag_params['id']);

		if (isset($tag_params['buyer']))
			$conditions['buyer'] = fix_id($tag_params['buyer']);

		// get address
		$address = $manager->getSingleItem($manager->getFieldNames(), $conditions);

		// load template
		$template = $this->_parent->loadTemplate($tag_params, 'address.xml');
		$template->setTemplateParamsFromArray($children);

		// parse template
		if (is_object($address)) {
			$params = array(
					'id'		=> $address->id,
					'buyer'		=> $address->buyer,
					'name'		=> $address->name,
					'street'	=> $address->street,
					'street2'	=> $address->street2,
					'phone'		=> $address->phone,
					'city'		=> $address->city,
					'zip'		=> $address->zip,
					'state'		=> $address->state,
					'country'	=> $address->country,
					'access_code'	=> $address->access_code
				);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}
}

?>
