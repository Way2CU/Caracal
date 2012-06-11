<?php

/**
 * Handler for shop transactions
 */

class ShopTransactionsHandler {
	private static $_instance;
	private $_parent;
	private $name;
	private $path;

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
		$action = isset($params['sub_action']) ? $params['sub_action'] : null;

		switch ($action) {
			case 'details':
				$this->showTransactionDetails();
				break;

			default:
				$this->showTransactions();
				break;
		}
	}
	
	/**
	 * Show list of transactions
	 */
	private function showTransactions() {
		$template = new TemplateHandler('transaction_list.xml', $this->path.'templates/');

		$params = array(
					);

		// register tag handler
		$template->registerTagHandler('_transaction_list', &$this, 'tag_TransactionList');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show list of transactions
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_TransactionList($tag_params, $children) {
		$manager = ShopTransactionsManager::getInstance();
		$conditions = array();

		// load template
		$template = $this->_parent->loadTemplate($tag_params, 'transaction_list_item.xml');

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		if (count($items) > 0)
			foreach($items as $item) {
				$title = $this->_parent->getLanguageConstant('title_transaction_details');
				$title .= ' '.$item->uid;
				$window = 'shop_transation_details_'.$item->id;

				$params = array(
							'buyer'			=> $item->buyer,
							'address'		=> $item->address,
							'uid'			=> $item->uid,
							'type'			=> $item->type,
							'type_value'	=> '',
							'status'		=> $item->status,
							'status_value'	=> '',
							'currency'		=> $item->currency,
							'currency_value'=> '',
							'handling'		=> $item->handling,
							'shipping'		=> $item->shipping,
							'total'			=> $item->total,
							'timestamp'		=> $item->timestamp,
							'item_details'	=> url_MakeHyperlink(
													$this->_parent->getLanguageConstant('details'),
													window_Open(
														$window, 400, $title, true, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'transactions'),
															array('sub_action', 'details'),
															array('id', $item->id)
														)
													)
												)
						);

				$template->setLocalParams($params);
				$template->restoreXML();
				$template->parse();
			}
	}
}

?>
