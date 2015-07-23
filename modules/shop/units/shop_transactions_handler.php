<?php

/**
 * Handler for shop transactions
 */

use Modules\Shop\Transaction;


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

			case 'json_update_status':
				$this->json_UpdateTransactionStatus();
				break;

			case 'json_update_total':
				$this->json_UpdateTransactionTotal();
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
			'link_reload'	=> url_MakeHyperlink(
							$this->_parent->getLanguageConstant('reload'),
							window_ReloadContent('shop_transactions')
						)
			);

		// register tag handlers
		$template->registerTagHandler('cms:transaction_list', $this, 'tag_TransactionList');
		$template->registerTagHandler('cms:status_list', $this, 'tag_TransactionStatus');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show details for specified transaction
	 */
	private function showTransactionDetails() {
		$manager = ShopTransactionsManager::getInstance();
		$buyer_manager = ShopBuyersManager::getInstance();
		$address_manager = ShopDeliveryAddressManager::getInstance();
		$user_manager = UserManager::getInstance();

		$id = fix_id($_REQUEST['id']);
		$transaction = $manager->getSingleItem(
								$manager->getFieldNames(),
								array('id' => $id)
							);
		$address = $address_manager->getSingleItem(
								$address_manager->getFieldNames(),
								array('id' => $transaction->address)
							);

		$full_address = $address->street."\n";

		if (!empty($address->street2))
			$full_address .= $address->street2."\n";

		$full_address .= "{$address->zip} {$address->city}\n";

		if (empty($address->state))
			$full_address .= $address->country; else
			$full_address .= "{$address->state}, {$address->country}";

		$params = array(
				'id'				=> $transaction->id,
				'uid'				=> $transaction->uid,
				'type'				=> $transaction->type,
				'type_string'		=> '',
				'status'			=> $transaction->status,
				'currency'			=> $transaction->currency,
				'handling'			=> $transaction->handling,
				'shipping'			=> $transaction->shipping,
				'timestamp'			=> $transaction->timestamp,
				'delivery_method'	=> $transaction->delivery_method,
				'delivery_type'		=> $transaction->delivery_type,
				'remark'			=> $transaction->remark,
				'total'				=> $transaction->total,
				'address_name'		=> $address->name,
				'address_street'	=> $address->street,
				'address_city'		=> $address->city,
				'address_zip'		=> $address->zip,
				'address_state'		=> $address->state,
				'address_country'	=> $address->country,
				'address_phone'		=> $address->phone,
				'address_access_code'	=> $address->access_code,
				'full_address'		=> $full_address
			);

		// regular or guest buyer
		$buyer = $buyer_manager->getSingleItem(
								$buyer_manager->getFieldNames(),
								array('id' => $transaction->buyer)
							);

		$params['first_name'] = $buyer->first_name;
		$params['last_name'] = $buyer->last_name;
		$params['email'] = $buyer->email;

		$template = new TemplateHandler('transaction_details.xml', $this->path.'templates/');

		// register tag handler
		$template->registerTagHandler('cms:item_list', $this, 'tag_TransactionItemList');
		$template->registerTagHandler('cms:transaction_status', $this, 'tag_TransactionStatus');

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
		$buyers_manager = ShopBuyersManager::getInstance();
		$user_manager = UserManager::getInstance();
		$conditions = array();

		// get conditionals
		if (isset($tag_params['buyer']))
			$conditions['buyer'] = fix_id($tag_params['buyer']);

		if (isset($_REQUEST['status']))
			$conditions['status'] = fix_id($_REQUEST['status']);

		if (isset($tag_params['system_user']) && $_SESSION['logged']) {
			$user_id = fix_id($tag_params['system_user']);
			$buyer = $buyers_manager->getSingleItem(array('id'), array('system_user' => $user_id));

			if (is_object($buyer))
				$conditions['buyer'] = $buyer->id; else
				$conditions['buyer'] = -1;
		}

		// load template
		$delivery_address_handler = \Modules\Shop\DeliveryAddressHandler::getInstance($this->_parent);

		$template = $this->_parent->loadTemplate($tag_params, 'transaction_list_item.xml');
		$template->registerTagHandler('cms:item_list', $this, 'tag_TransactionItemList');
		$template->registerTagHandler('cms:address', $delivery_address_handler, 'tag_DeliveryAddress');

		// get all buyers
		$buyer_names = array();
		$buyers = $buyers_manager->getItems(array('id', 'first_name', 'last_name'), array());

		if (count($buyers) > 0)
			foreach ($buyers as $buyer)
				$buyer_names[$buyer->id] = $buyer->first_name.' '.$buyer->last_name;

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		if (count($items) > 0)
			foreach($items as $item) {
				// prepare window parameters
				$title = $this->_parent->getLanguageConstant('title_transaction_details');
				$title .= ' '.$item->uid;
				$window = 'shop_transation_details_'.$item->id;

				// prepare buyer name
				$name = '';
				if ($item->buyer > 0)
					$name = $buyer_names[$item->buyer];

				// prepare language constants
				$transaction_status = $this->_parent->getLanguageConstant(TransactionStatus::$reverse[$item->status]);
				$transaction_type = $this->_parent->getLanguageConstant(TransactionType::$reverse[$item->type]);

				// prepare template parameters
				$params = array(
							'id'				=> $item->id,
							'buyer'				=> $item->buyer,
							'buyer_name'		=> $name,
							'address'			=> $item->address,
							'uid'				=> $item->uid,
							'type'				=> $item->type,
							'type_value'		=> $transaction_type,
							'status'			=> $item->status,
							'status_value'		=> $transaction_status,
							'currency'			=> $item->currency,
							'currency_value'	=> '',
							'handling'			=> $item->handling,
							'shipping'			=> $item->shipping,
							'total'				=> $item->total,
							'summary'			=> $item->total + $item->shipping + $item->handling,
							'delivery_method'	=> $item->delivery_method,
							'delivery_type'		=> $item->delivery_type,
							'remark'			=> $item->remark,
							'timestamp'			=> $item->timestamp,
							'item_details'		=> url_MakeHyperlink(
													$this->_parent->getLanguageConstant('details'),
													window_Open(
														$window, 800, $title, true, false,
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

	/**
	 * Handle drawing list of items in transaction
	 */
	public function tag_TransactionItemList($tag_params, $children) {
		$manager = ShopTransactionItemsManager::getInstance();
		$item_manager = ShopItemManager::getInstance();
		$transaction_manager = ShopTransactionsManager::getInstance();
		$currency_manager = ShopCurrenciesManager::getInstance();
		$conditions = array();

		// get conditions
		if (isset($tag_params['transaction']))
			$conditions['transaction'] = fix_id($tag_params['transaction']);

		// if we don't have transaction id, get out
		if (!isset($conditions['transaction']))
			return;

		$currency_id = $transaction_manager->getItemValue('currency', array('id' => $conditions['transaction']));
		$currency = $currency_manager->getItemValue('currency', array('id' => $currency_id));

		// get items from database
		$items = array();
		$raw_items = $manager->getItems($manager->getFieldNames(), $conditions);

		if (count($raw_items) > 0)
			foreach ($raw_items as $item) {
				$description = implode(', ', array_values(unserialize($item->description)));
				$items[$item->item] = array(
							'id'			=> $item->id,
							'price'			=> $item->price,
							'tax'			=> $item->tax,
							'amount'		=> $item->amount,
							'description'	=> $description,
							'uid' 			=> '',
							'name'			=> '',
							'gallery'		=> '',
							'size_definition'=> '',
							'colors'		=> '',
							'manufacturer'	=> '',
							'author' 		=> '',
							'views' 		=> '',
							'weight' 		=> '',
							'votes_up' 		=> '',
							'votes_down' 	=> '',
							'timestamp' 	=> '',
							'priority' 		=> '',
							'visible' 		=> '',
							'deleted' 		=> '',
							'total'			=> ($item->price + ($item->price * ($item->tax / 100))) * $item->amount,
							'currency'		=> $currency
						);
			}

		// get the rest of item details from database
		$id_list = array_keys($items);
		$raw_items = $item_manager->getItems($item_manager->getFieldNames(), array('id' => $id_list));

		if (count($raw_items) > 0)
			foreach ($raw_items as $item) {
				$id = $item->id;
				$items[$id]['uid'] = $item->uid;
				$items[$id]['name'] = $item->name;
				$items[$id]['gallery'] = $item->gallery;
				$items[$id]['size_definition'] = $item->size_definition;
				$items[$id]['colors'] = $item->colors;
				$items[$id]['manufacturer'] = $item->manufacturer;
				$items[$id]['author'] = $item->author;
				$items[$id]['views'] = $item->views;
				$items[$id]['weight'] = $item->weight;
				$items[$id]['votes_up'] = $item->votes_up;
				$items[$id]['votes_down'] = $item->votes_down;
				$items[$id]['timestamp'] = $item->timestamp;
				$items[$id]['priority'] = $item->priority;
				$items[$id]['visible'] = $item->visible;
				$items[$id]['deleted'] = $item->deleted;
			}

		if (count($items) > 0) {
			$sizes_handler = ShopItemSizesHandler::getInstance($this->_parent);
			$template = $this->_parent->loadTemplate($tag_params, 'transaction_details_item.xml');
			$template->registerTagHandler('cms:value_list', $sizes_handler, 'tag_ValueList');

			foreach ($items as $id => $params) {
				$template->setLocalParams($params);
				$template->restoreXML();
				$template->parse();
			}
		}
	}

	/**
	 * Print transaction status
	 */
	public function tag_TransactionStatus($tag_params, $children) {
		$template = $this->_parent->loadTemplate($tag_params, 'transaction_status_option.xml');
		$transaction = null;

		// get selected
		$active = -1;
		if (isset($tag_params['active']))
			$active = fix_id($tag_params['active']);
		if (isset($_REQUEST['status']) && !empty($_REQUEST['status']))
			$active = fix_id($_REQUEST['status']);

		// get transaction id
		if (isset($tag_params['transaction'])) {
			$manager = ShopTransactionsManager::getInstance();
			$conditions = array();

			if (is_numeric($tag_params['transaction']))
				$conditions['id'] = fix_id($tag_params['transaction']); else
				$conditions['uid'] = escape_chars($tag_params['transaction']);

			$transaction = $manager->getSingleItem(array('type', 'status'), $conditions);
		}

		// prepare available statuses
		if (is_object($transaction) && isset(TransactionStatus::$flow[$transaction->type])) {
			$transaction_flow = TransactionStatus::$flow[$transaction->type];

			// get list of codes available for this transaction
			$code_list = array();
			if (isset($transaction_flow[$transaction->status]))
				$code_list = $transaction_flow[$transaction->status];

			// prepare status list
			$status_list = array();
			if (count($code_list) > 0)
				foreach($code_list as $code)
					$status_list[$code] = TransactionStatus::$reverse[$code];

		} else {
			// default complete status list
			$status_list = TransactionStatus::$reverse;
		}

		// parse templates
		foreach ($status_list as $id => $constant) {
			$params = array(
					'id'		=> $id,
					'text'		=> $this->_parent->getLanguageConstant($constant),
					'selected'	=> $active
				);

			$template->setLocalParams($params);
			$template->restoreXML();
			$template->parse();
		}
	}

	/**
	 * Handle updating transaction status through AJAX request.
	 */
	private function json_UpdateTransactionStatus() {
		$id = escape_chars($_REQUEST['id']);
		$status = fix_id($_REQUEST['status']);
		$result = false;

		// set transaction status
		$result = shop::getInstance()->setTransactionStatus($id, $status);

		print json_encode($result);
	}

	/**
 	 * Update handling and total amount for specified transaction through AJAX request.
	 */
	private function json_UpdateTransactionTotal() {
		$id = escape_chars($_REQUEST['id']);
		$total = is_numeric($_REQUEST['total']) ? $_REQUEST['total'] : 0;
		$handling = is_numeric($_REQUEST['handling']) ? $_REQUEST['handling'] : 0;
		$result = false;

		try {
			$transaction = Transaction::get($id);

		} catch (UnknownTransactionError $error) {
			trigger_error('Unable to change transaction totals.', E_USER_NOTICE);
			print json_encode($result);
			return;
		}

		Transaction::set_totals($transaction, $total, $handling);
		print json_encode($result);
	}
}

?>
