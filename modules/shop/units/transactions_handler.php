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
			case 'details':
				$this->showTransactionDetails();
				break;

			case 'print':
				$this->printTransaction();
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
							$this->_parent->get_language_constant('reload'),
							window_ReloadContent('shop_transactions')
						)
			);

		// register tag handlers
		$template->register_tag_handler('cms:transaction_list', $this, 'tag_TransactionList');
		$template->register_tag_handler('cms:status_list', $this, 'tag_TransactionStatus');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show details for specified transaction
	 */
	private function showTransactionDetails() {
		$manager = ShopTransactionsManager::get_instance();
		$buyer_manager = ShopBuyersManager::get_instance();
		$address_manager = ShopDeliveryAddressManager::get_instance();
		$user_manager = UserManager::get_instance();

		$id = fix_id($_REQUEST['id']);
		$transaction = $manager->get_single_item(
								$manager->get_field_names(),
								array('id' => $id)
							);
		$address = $address_manager->get_single_item(
								$address_manager->get_field_names(),
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
				'address_email'		=> $address->email,
				'address_phone'		=> $address->phone,
				'address_access_code'	=> $address->access_code,
				'full_address'		=> $full_address,
				'cancel_action'		=> window_Close('shop_transaction_details_'.$transaction->id),
				'print_url'			=> url_Make(
											'transfer_control',
											'backend_module',
											array('module', $this->_parent->name),
											array('backend_action', 'transactions'),
											array('sub_action', 'print'),
											array('id', $transaction->id)
										)
			);

		// regular or guest buyer
		$buyer = $buyer_manager->get_single_item(
								$buyer_manager->get_field_names(),
								array('id' => $transaction->buyer)
							);

		$params['first_name'] = $buyer->first_name;
		$params['last_name'] = $buyer->last_name;
		$params['email'] = $buyer->email;
		$params['phone'] = $buyer->phone;

		$template = new TemplateHandler('transaction_details.xml', $this->path.'templates/');

		// register tag handler
		$template->register_tag_handler('cms:item_list', $this, 'tag_TransactionItemList');
		$template->register_tag_handler('cms:transaction_status', $this, 'tag_TransactionStatus');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show template for printing and automatically show print dialog.
	 */
	private function printTransaction() {
		$manager = ShopTransactionsManager::get_instance();
		$buyer_manager = ShopBuyersManager::get_instance();
		$address_manager = ShopDeliveryAddressManager::get_instance();
		$user_manager = UserManager::get_instance();
		$item_manager = ShopTransactionItemsManager::get_instance();

		$id = fix_id($_REQUEST['id']);
		$transaction = $manager->get_single_item(
								$manager->get_field_names(),
								array('id' => $id)
							);
		$address = $address_manager->get_single_item(
								$address_manager->get_field_names(),
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
				'address_email'		=> $address->email,
				'address_phone'		=> $address->phone,
				'address_access_code'	=> $address->access_code,
				'full_address'		=> $full_address,
				'style_url'			=> url_GetFromFilePath($this->path.'include/transaction_print.css'),
			);

		// regular or guest buyer
		$buyer = $buyer_manager->get_single_item(
								$buyer_manager->get_field_names(),
								array('id' => $transaction->buyer)
							);

		$params['first_name'] = $buyer->first_name;
		$params['last_name'] = $buyer->last_name;
		$params['email'] = $buyer->email;

		// calculate total count
		$total_count = 0;
		$item_list = $item_manager->get_items(array('amount'), array('transaction' => $transaction->id));

		if (count($item_list) > 0)
			foreach ($item_list as $item)
				$total_count += $item->amount;

		$params['total_count'] = $total_count;

		// load template
		$template = new TemplateHandler('transaction_print.xml', $this->path.'templates/');

		// register tag handler
		$template->register_tag_handler('cms:item_list', $this, 'tag_TransactionItemList');
		$template->register_tag_handler('cms:transaction_status', $this, 'tag_TransactionStatus');

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show list of transactions
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_TransactionList($tag_params, $children) {
		$manager = ShopTransactionsManager::get_instance();
		$buyers_manager = ShopBuyersManager::get_instance();
		$user_manager = UserManager::get_instance();
		$conditions = array();
		$order_by = array('id');
		$order_asc = true;

		// get conditionals
		if (isset($tag_params['buyer']))
			$conditions['buyer'] = fix_id($tag_params['buyer']);

		if (isset($_REQUEST['status']) && $_REQUEST['status'] != '')
			$conditions['status'] = fix_id($_REQUEST['status']);

		if (isset($tag_params['order_by']))
			$order_by = fix_chars(explode(',', $tag_params['order_by']));

		if (isset($tag_params['order_asc']))
			$order_asc = $tag_params['order_asc'] == 1 ? true : false;

		if (isset($tag_params['system_user']) && $_SESSION['logged']) {
			$user_id = fix_id($tag_params['system_user']);
			$buyer = $buyers_manager->get_single_item(array('id'), array('system_user' => $user_id));

			if (is_object($buyer))
				$conditions['buyer'] = $buyer->id; else
				$conditions['buyer'] = -1;
		}

		// load template
		$delivery_address_handler = \Modules\Shop\DeliveryAddressHandler::get_instance($this->_parent);

		$template = $this->_parent->load_template($tag_params, 'transaction_list_item.xml');
		$template->set_template_params_from_array($children);
		$template->register_tag_handler('cms:item_list', $this, 'tag_TransactionItemList');
		$template->register_tag_handler('cms:address', $delivery_address_handler, 'tag_DeliveryAddress');

		// get all buyers
		$buyer_names = array();
		$buyers = $buyers_manager->get_items(array('id', 'first_name', 'last_name'), array());

		if (count($buyers) > 0)
			foreach ($buyers as $buyer)
				$buyer_names[$buyer->id] = $buyer->first_name.' '.$buyer->last_name;

		// get items from database
		$items = $manager->get_items($manager->get_field_names(), $conditions, $order_by, $order_asc);

		if (count($items) > 0)
			foreach($items as $item) {
				// prepare window parameters
				$title = $this->_parent->get_language_constant('title_transaction_details');
				$title .= ' '.$item->uid;
				$window = 'shop_transaction_details_'.$item->id;

				// prepare buyer name
				$name = '';
				if ($item->buyer > 0)
					$name = $buyer_names[$item->buyer];

				// prepare language constants
				$transaction_status = $this->_parent->get_language_constant(TransactionStatus::$reverse[$item->status]);
				$transaction_type = $this->_parent->get_language_constant(TransactionType::$reverse[$item->type]);

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
													$this->_parent->get_language_constant('details'),
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

				$template->set_local_params($params);
				$template->restore_xml();
				$template->parse();
			}
	}

	/**
	 * Handle drawing list of items in transaction
	 */
	public function tag_TransactionItemList($tag_params, $children) {
		$manager = ShopTransactionItemsManager::get_instance();
		$item_manager = ShopItemManager::get_instance();
		$transaction_manager = ShopTransactionsManager::get_instance();
		$currency_manager = ShopCurrenciesManager::get_instance();
		$conditions = array();

		// get conditions
		if (isset($tag_params['transaction']))
			$conditions['transaction'] = fix_id($tag_params['transaction']);

		// if we don't have transaction id, get out
		if (!isset($conditions['transaction']))
			return;

		$currency_id = $transaction_manager->get_item_value('currency', array('id' => $conditions['transaction']));
		$currency = $currency_manager->get_item_value('currency', array('id' => $currency_id));

		// get items from database
		$items = array();
		$raw_items = $manager->get_items($manager->get_field_names(), $conditions);

		if (count($raw_items) > 0)
			foreach ($raw_items as $item) {
				$properties = unserialize($item->description);
				$description = '';
				foreach ($properties as $key => $value)
					$description .= '<span class="property">'.$key.'<span class="value">'.$value.'</span></span>';

				$items[$item->item] = array(
							'id'              => $item->id,
							'price'           => $item->price,
							'tax'             => $item->tax,
							'amount'          => $item->amount,
							'description'     => $description,
							'uid'             => '',
							'name'            => '',
							'gallery'         => '',
							'size_definition' => '',
							'colors'          => '',
							'manufacturer'    => '',
							'author'          => '',
							'views'           => '',
							'weight'          => '',
							'votes_up'        => '',
							'votes_down'      => '',
							'timestamp'       => '',
							'priority'        => '',
							'visible'         => '',
							'deleted'         => '',
							'total'           => ($item->price + ($item->price * ($item->tax / 100))) * $item->amount,
							'currency'        => $currency
						);
			}

		// get the rest of item details from database
		$id_list = array_keys($items);
		$raw_items = $item_manager->get_items($item_manager->get_field_names(), array('id' => $id_list));

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
			$sizes_handler = ShopItemSizesHandler::get_instance($this->_parent);
			$template = $this->_parent->load_template($tag_params, 'transaction_details_item.xml');
			$template->set_template_params_from_array($children);
			$template->register_tag_handler('cms:value_list', $sizes_handler, 'tag_ValueList');

			foreach ($items as $id => $params) {
				$template->set_local_params($params);
				$template->restore_xml();
				$template->parse();
			}
		}
	}

	/**
	 * Print transaction status
	 */
	public function tag_TransactionStatus($tag_params, $children) {
		$template = $this->_parent->load_template($tag_params, 'transaction_status_option.xml');
		$template->set_template_params_from_array($children);
		$transaction = null;

		// get selected
		$active = -1;
		if (isset($tag_params['active']))
			$active = fix_id($tag_params['active']);
		if (isset($_REQUEST['status']) && $_REQUEST['status'] != '')
			$active = fix_id($_REQUEST['status']);

		// get transaction id
		if (isset($tag_params['transaction'])) {
			$manager = ShopTransactionsManager::get_instance();
			$conditions = array();

			if (is_numeric($tag_params['transaction']))
				$conditions['id'] = fix_id($tag_params['transaction']); else
				$conditions['uid'] = escape_chars($tag_params['transaction']);

			$transaction = $manager->get_single_item(array('type', 'status'), $conditions);
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
					'text'		=> $this->_parent->get_language_constant($constant),
					'selected'	=> $active
				);

			$template->set_local_params($params);
			$template->restore_xml();
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
		$result = shop::get_instance()->setTransactionStatus($id, $status);

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

		Transaction::set_totals($transaction, $total, null, $handling);
		print json_encode($result);
	}
}

?>
