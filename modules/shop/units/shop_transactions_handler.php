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

		$params = array();

		// register tag handler
		$template->registerTagHandler('_transaction_list', &$this, 'tag_TransactionList');

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
		$address_manager = ShopBuyerAddressesManager::getInstance();
		
		$id = fix_id($_REQUEST['id']);
		$transaction = $manager->getSingleItem(
								$manager->getFieldNames(), 
								array('id' => $id)
							);
		$buyer = $buyer_manager->getSingleItem(
								$buyer_manager->getFieldNames(), 
								array('id' => $transaction->buyer)
							);
		$address = $address_manager->getSingleItem(
								$address_manager->getFieldNames(),
								array('id' => $transaction->address)
							);

		$full_address = "{$address->name}\n\n{$address->street}\n";
		$full_address .= "{$address->zip} {$address->city}\n";
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
				'total'				=> $transaction->total,
				'first_name'		=> $buyer->first_name,
				'last_name'			=> $buyer->last_name,
				'email'				=> $buyer->email,
				'address_name'		=> $address->name,
				'address_street'	=> $address->street,
				'address_city'		=> $address->city,
				'address_zip'		=> $address->zip,
				'address_state'		=> $address->state,
				'address_country'	=> $address->country,
				'full_address'		=> $full_address
			);

		$template = new TemplateHandler('transaction_details.xml', $this->path.'templates/');

		// register tag handler
		$template->registerTagHandler('_item_list', &$this, 'tag_TransactionItemList');
		$template->registerTagHandler('_transaction_status', &$this, 'tag_TransactionStatus');

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
		$id = null;

		if (isset($tag_params['id'])) {
			// get id from tag params
			$id = fix_id($tag_params['id']);

		} else if (isset($_REQUEST['id'])) {
			// get id from request params
			$id = fix_id($_REQUEST['id']);
		}

		// if we don't have transaction Id, get out
		if (is_null($id))
			return;

		$currency_id = $transaction_manager->getItemValue('currency', array('id' => $id));
		$currency = $currency_manager->getItemValue('currency', array('id' => $currency_id));

		// get items from database
		$items = array();
		$raw_items = $manager->getItems($manager->getFieldNames(), array('transaction' => $id));

		if (count($raw_items) > 0) 
			foreach ($raw_items as $item) {
				$items[$item->id] = array(
							'id'			=> $item->id,
							'price'			=> $item->price,
							'tax'			=> $item->tax,
							'amount'		=> $item->amount,
							'description'	=> $item->description,
							'uid' 			=> '',
							'name'			=> '',
							'description'	=> '',
							'gallery'		=> '',
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
							'total'			=> 0,
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
				$items[$id]['description'] = $item->description;
				$items[$id]['gallery'] = $item->gallery;
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
			$template = $this->_parent->loadTemplate($tag_params, 'transaction_details_item.xml');

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
		$active = isset($tag_params['active']) ? fix_id($tag_params['active']) : -1;
		$constants = array(
				TransactionStatus::PENDING 		=> 'status_pending',
				TransactionStatus::DENIED		=> 'status_denied',
				TransactionStatus::COMPLETED	=> 'status_completed',
				TransactionStatus::CANCELED		=> 'status_canceled',
				TransactionStatus::SHIPPING		=> 'status_shipping',
				TransactionStatus::SHIPPED		=> 'status_shipped',
				TransactionStatus::LOST			=> 'status_lost',
				TransactionStatus::DELIVERED	=> 'status_delivered'
			);

		$template = $this->_parent->loadTemplate($tag_params, 'transaction_status_option.xml');

		foreach ($constants as $id => $constant) {
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
	 * Handle updating transaction status through AJAX request
	 */
	public function json_UpdateTransactionStatus() {
		$manager = ShopTransactionsManager::getInstance();
		$id = fix_id($_REQUEST['id']);
		$status = fix_id($_REQUEST['status']);
		$result = false;
		$transaction = null;

		if ($_SESSION['logged']) {
			// get transaction
			$transaction = $manager->getSingleItem(array('id'), array('id' => $id));
			
			// update status
			if (is_object($transaction)) {
				$manager->updateData(array('status' => $status), array('id' => $id));
				$result = true;
			}
		}

		print json_encode($result);
	}

	/**
	 * Send email to client and site owner
	 * @param string $uid
	 * @return boolean
	 */
	public function sendMail($uid) {
		global $language, $language_rtl;

		$result = false;
		$html_body = '';
		$subject = '';
		$body = '';

		// grab some objects to play with
		$transaction_manager = ShopTransactionsManager::getInstance();
		$transaction_items_manager = ShopTransactionItemsManager::getInstance();
		$items_manager = ShopItemManager::getInstance();
		$buyer_manager = ShopBuyersManager::getInstance();
		$address_manager = ShopBuyerAddressesManager::getInstance();
		$language_handler = MainLanguageHandler::getInstance();

		$transaction = $transaction_manager->getSingleItem(
							$transaction_manager->getFieldNames(),
							array('uid' => $uid)
						);

		// get contact module 
		if (class_exists('contact_form')) {
			$contact_form = contact_form::getInstance();

		} else {
			$contact_form = null;
		}

		// get email body
		if (class_exists('articles') && isset($this->_parent->settings['email_article'])) { 
			$article_id = $this->_parent->settings['email_article'];
			$article_manager = ArticleManager::getInstance();
			$article = $article_manager->getSingleItem(
							$article_manager->getFieldNames(),
							array('id' => $article_id)
						);

			if (is_object($article)) {
				$body = $article->content[$language];
				$subject = $article->title[$language];
			}
		}

		// create email bodies
		if (is_object($transaction) && is_object($contact_form) && !empty($body)) {
			$items = array();

			// get buyer and address
			$buyer = $buyer_manager->getSingleItem(
							$buyer_manager->getFieldNames(),
							array('id' => $transaction->buyer)
						);
			$address = $address_manager->getSingleItem(
							$address_manager->getFieldNames(),
							array('id' => $transaction->address)
						);

			// get transaction items
			$transaction_items = $transaction_items_manager->getItems(
							$transaction_items_manager->getFieldNames(),
							array('transaction' => $transaction->id)
						);

			foreach ($transaction_items as $item) {
				$items[$item->item] = array(
							'price'			=> $item->price,
							'tax'			=> $item->tax,
							'amount'		=> $item->amount,
							'description'	=> $item->description,
							'uid'			=> '',
							'name'			=> '',
						);
			}

			// get additional information for items
			$id_list = array_keys($items);
			$raw_items = $items_manager->getItems(array('id', 'uid', 'name'), array('id' => $id_list));
			$subtotal = 0;

			foreach ($raw_items as $item) {
				$id = $item->id;

				$items[$id]['name'] = $item->name;
				$items[$id]['uid'] = $item->uid;
			}

			// create items table
			$text_table = str_pad($this->_parent->getLanguageConstant('column_name'), 40);
			$text_table .= str_pad($this->_parent->getLanguageConstant('column_price'), 8);
			$text_table .= str_pad($this->_parent->getLanguageConstant('column_amount'), 6);
			$text_table .= str_pad($this->_parent->getLanguageConstant('column_item_total'), 8);
			$text_table .= "\n" . str_repeat('-', 40 + 8 + 6 + 8) . "\n";

			$html_table = '<table border="0" cellspacing="5" cellpadding="0">';
			$html_table .= '<thead><tr>';
			$html_table .= '<td>'.$this->_parent->getLanguageConstant('column_name').'</td>';
			$html_table .= '<td>'.$this->_parent->getLanguageConstant('column_price').'</td>';
			$html_table .= '<td>'.$this->_parent->getLanguageConstant('column_amount').'</td>';
			$html_table .= '<td>'.$this->_parent->getLanguageConstant('column_item_total').'</td>';
			$html_table .= '</td></thead><tbody>';

			foreach ($items as $id => $data) {
				// append item name with description
				if (empty($data['description']))
					$line = $data['name'][$language] . ' (' . $data['description'] . ')'; else
					$line = $data['name'][$language];

				$line = utf8_wordwrap($line, 40, "\n", true);
				$line = mb_split("\n", $line);

				// append other columns
				$line[0] = $line[0] . str_pad($data['price'], 8, ' ', STR_PAD_LEFT);
				$line[0] = $line[0] . str_pad($data['amount'], 6, ' ', STR_PAD_LEFT);
				$line[0] = $line[0] . str_pad($data['total'], 8, ' ', STR_PAD_LEFT);

				// add this item to text table
				$text_table .= implode("\n", $line) . "\n\n";

				// form html row
				$row = '<tr><td>' . $data['name'][$language];

				if (!empty($data['description']))
					$row .= ' <small>' . $data['description'] . '</small>';

				$row .= '</td><td>' . $data['price'] . '</td>';
				$row .= '<td>' . $data['amount'] . '</td>';
				$row .= '<td>' . $data['total'] . '</td></tr>';

				// update subtotal
				$subtotal += $data['total'];
			}

			// close text table
			$text_table .= str_repeat('-', 40 + 8 + 6 + 8) . "\n";
			$html_table .= '</tbody>';

			// create totals
			$text_table .= str_pad($this->_parent->getLanguageConstant('column_subtotal'), 15);
			$text_table .= str_pad($subtotal, 10, ' ', STR_PAD_LEFT) . "\n";

			$text_table .= str_pad($this->_parent->getLanguageConstant('column_shipping'), 15);
			$text_table .= str_pad($transaction->shipping, 10, ' ', STR_PAD_LEFT) . "\n";

			$text_table .= str_pad($this->_parent->getLanguageConstant('column_handling'), 15);
			$text_table .= str_pad($transaction->handling, 10, ' ', STR_PAD_LEFT) . "\n";

			$text_table .= str_repeat('-', 25);
			$text_table .= str_pad($this->_parent->getLanguageConstant('column_total'), 15);
			$text_table .= str_pad($transaction->total, 10, ' ', STR_PAD_LEFT) . "\n";

			$html_table .= '<tfoot>';
			$html_table .= '<tr><td colspan="2"></td><td>' . $this->_parent->getLanguageConstant('column_subtotal') . '</td>';
			$html_table .= '<td>' . $subtotal . '</td></tr>';

			$html_table .= '<tr><td colspan="2"></td><td>' . $this->_parent->getLanguageConstant('column_shipping') . '</td>';
			$html_table .= '<td>' . $transaction->shipping . '</td></tr>';

			$html_table .= '<tr><td colspan="2"></td><td>' . $this->_parent->getLanguageConstant('column_handling') . '</td>';
			$html_table .= '<td>' . $transaction->handling . '</td></tr>';

			$html_table .= '<tr><td colspan="2"></td><td><b>' . $this->_parent->getLanguageConstant('column_total') . '</b></td>';
			$html_table .= '<td><b>' . $transaction->total . '</b></td></tr>';

			$html_table .= '</tfoot>';

			// close table
			$html_table .= '</table>';

			// create HTML version of email
			$html_body = Markdown($body);
			$html_body = implode('%items_table_html%', mb_split('%items_table%', $html_body));

			// create email body
			$params = array(
					'first_name'		=> $buyer->first_name,
					'last_name'			=> $buyer->last_name,
					'email'				=> $buyer->email,
					'address_name'		=> $address->name,
					'address_street'	=> $address->street,
					'address_city'		=> $address->city,
					'address_zip'		=> $address->zip,
					'address_state'		=> $address->state,
					'address_country'	=> $address->country,
					'items_table'		=> $text_table,
					'items_table_html'	=> $html_table
				);

			foreach ($params as $needle => $replacement) {
				$body = implode($replacement, mb_split("%{$needle}%", $body));
				$html_body = implode($replacement, mb_split("%{$needle}%", $html_body));
			}

			$result = $contact_form->sendFromModule($to, $subject, $body, $html_body);
		}

		return $result;
	}
}

?>
