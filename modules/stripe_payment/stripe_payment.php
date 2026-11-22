<?php

/**
 * Stripe Payment Support
 *
 * Integrates Stripe hosted checkout with the shop module. Buyer is
 * redirected to checkout session created through Stripe API and outcome
 * is reported back through webhook events. Recurring prices defined in
 * Stripe are synchronized to local table and offered as subscription plans.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;

require_once('units/api.php');
require_once('units/plans_manager.php');
require_once('units/customer_manager.php');
require_once('units/transaction_manager.php');


class stripe_payment extends Module {
	private static $_instance;
	private $method = null;

	// stripe interval to shop interval
	private $interval_map = array(
			'day'   => RecurringPayment::DAY,
			'week'  => RecurringPayment::WEEK,
			'month' => RecurringPayment::MONTH,
			'year'  => RecurringPayment::YEAR
		);

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);

		// configure api access
		if (isset($this->settings['secret_key']))
			Stripe_Api::set_secret_key($this->settings['secret_key']);

		// register backend
		if (ModuleHandler::is_loaded('backend') && ModuleHandler::is_loaded('shop')) {
			$backend = backend::get_instance();
			$method_menu = $backend->getMenu('shop_payment_methods');
			$plans_menu = $backend->getMenu('shop_recurring_plans');

			if (!is_null($method_menu))
				$method_menu->addChild('', new backend_MenuItem(
									$this->get_language_constant('menu_stripe'),
									$this->path.'images/icon.svg',
									window_Open( // on click open window
												'stripe',
												400,
												$this->get_language_constant('title_settings'),
												true, true,
												backend_UrlMake($this->name, 'show_settings')
											),
									$level=5
								));

			if (!is_null($plans_menu))
				$plans_menu->addChild('', new backend_MenuItem(
									$this->get_language_constant('menu_stripe'),
									$this->path.'images/icon.svg',
									window_Open( // on click open window
												'stripe_recurring_plans',
												520,
												$this->get_language_constant('title_recurring_plans'),
												true, true,
												backend_UrlMake($this->name, 'recurring_plans')
											),
									$level=5
								));
		}

		// register payment method
		if (ModuleHandler::is_loaded('shop')) {
			require_once('units/stripe_payment_method.php');
			$this->method = Stripe_PaymentMethod::get_instance($this);
		}
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transfer_control($params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'checkout-start':
					if (!is_null($this->method))
						$this->method->handle_start_checkout();
					break;

				case 'checkout-confirm':
					if (!is_null($this->method))
						$this->method->handle_confirm_checkout();
					break;

				case 'checkout-cancel':
					if (!is_null($this->method))
						$this->method->handle_cancel_checkout();
					break;

				case 'webhook':
					if (!is_null($this->method))
						$this->method->handle_webhook();
					break;

				case 'show_plan_list':
					$this->tag_PlanList($params, $children);
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'show_settings':
					$this->show_settings();
					break;

				case 'save_settings':
					$this->save_settings();
					break;

				case 'recurring_plans':
					$this->show_recurring_plans();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function initialize() {
		global $db;

		$this->save_setting('secret_key', '');
		$this->save_setting('webhook_secret', '');

		// create tables
		$file_list = array('transactions.sql', 'customers.sql', 'recurring_plans.sql');

		foreach ($file_list as $file_name) {
			$sql = Query::load_file($file_name, $this);
			$db->query($sql);
		}
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
		global $db;

		$tables = array('stripe_transactions', 'stripe_customers', 'stripe_recurring_plans');
		$db->drop_tables($tables);
	}

	/**
	 * Show settings form.
	 */
	private function show_settings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
				'form_action' => backend_UrlMake($this->name, 'save_settings'),
				'webhook_url' => is_null($this->method) ? '' : $this->method->get_callback_url('webhook')
			);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save settings.
	 */
	private function save_settings() {
		$secret_key = trim(fix_chars($_REQUEST['secret_key']));
		$webhook_secret = trim(fix_chars($_REQUEST['webhook_secret']));

		$this->save_setting('secret_key', $secret_key);
		$this->save_setting('webhook_secret', $webhook_secret);

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message' => $this->get_language_constant('message_settings_saved'),
					'button'  => $this->get_language_constant('close'),
					'action'  => window_Close('stripe')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show message in backend window.
	 *
	 * @param string $message
	 */
	private function show_window_message($message) {
		$template = new TemplateHandler('window_message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array('message' => $message);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Synchronize local list of recurring plans with active recurring
	 * prices defined in Stripe.
	 *
	 * @throws Stripe_ApiError
	 */
	private function update_recurring_plans() {
		$manager = Stripe_PlansManager::get_instance();
		$known_ids = array();
		$seen_ids = array();
		$params = array(
				'type'        => 'recurring',
				'active'      => 'true',
				'limit'       => 100,
				'expand'      => array('data.product')
			);

		// load known plan ids
		$plans = $manager->get_items(array('text_id'), array());
		foreach ($plans as $plan)
			$known_ids[] = $plan->text_id;

		// walk through all pages of prices
		do {
			$response = Stripe_Api::call('GET', 'prices', $params);
			$prices = isset($response['data']) ? $response['data'] : array();

			foreach ($prices as $price) {
				if (!isset($price['recurring']['interval']) || !isset($price['unit_amount']))
					continue;

				$product = isset($price['product']) && is_array($price['product']) ? $price['product'] : array();
				$interval = $price['recurring']['interval'];

				if (isset($price['nickname']) && !empty($price['nickname']))
					$name = $price['nickname']; else if (isset($product['name']))
					$name = $product['name']; else
					$name = $price['id'];

				$data = array(
						'name'           => fix_chars($name),
						'trial_days'     => isset($price['recurring']['trial_period_days']) ? (int) $price['recurring']['trial_period_days'] : 0,
						'interval'       => isset($this->interval_map[$interval]) ? $this->interval_map[$interval] : RecurringPayment::MONTH,
						'interval_count' => (int) $price['recurring']['interval_count'],
						'price'          => Stripe_Api::from_minor_units($price['unit_amount'], $price['currency']),
						'currency'       => strtoupper($price['currency'])
					);

				if (in_array($price['id'], $known_ids)) {
					$manager->update_items($data, array('text_id' => $price['id']));

				} else {
					$data['text_id'] = $price['id'];
					$manager->insert_item($data);
				}

				$seen_ids[] = $price['id'];
				$params['starting_after'] = $price['id'];
			}

			$has_more = isset($response['has_more']) && $response['has_more'] && count($prices) > 0;
		} while ($has_more);

		// remove plans no longer active in stripe
		$remove_ids = array_diff($known_ids, $seen_ids);
		if (count($remove_ids) > 0)
			$manager->delete_items(array('text_id' => array_values($remove_ids)));
	}

	/**
	 * Show list of recurring plans.
	 */
	private function show_recurring_plans() {
		if (empty($this->settings['secret_key'])) {
			$this->show_window_message($this->get_language_constant('message_missing_api_keys'));
			return;
		}

		try {
			$this->update_recurring_plans();

		} catch (Stripe_ApiError $error) {
			trigger_error($error->getMessage(), E_USER_WARNING);
			$this->show_window_message($error->getMessage());
			return;
		}

		$template = new TemplateHandler('plans_list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:list', $this, 'tag_PlanList');

		$template->restore_xml();
		$template->set_local_params(array());
		$template->parse();
	}

	/**
	 * Print list of plans.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_PlanList($tag_params, $children) {
		$manager = Stripe_PlansManager::get_instance();
		$selected = isset($_SESSION['recurring_plan']) ? $_SESSION['recurring_plan'] : null;

		$items = $manager->get_items($manager->get_field_names(), array(), array('name'));

		$template = $this->load_template($tag_params, 'plans_list_item.xml');
		$template->set_template_params_from_array($children);

		if (count($items) > 0)
			foreach ($items as $item) {
				$params = array(
					'id'             => $item->id,
					'text_id'        => $item->text_id,
					'name'           => $item->name,
					'trial_days'     => $item->trial_days,
					'interval'       => $item->interval,
					'interval_count' => $item->interval_count,
					'price'          => $item->price,
					'currency'       => $item->currency,
					'selected'       => $selected == $item->text_id
				);

				$template->restore_xml();
				$template->set_local_params($params);
				$template->parse();
			}
	}
}

?>
