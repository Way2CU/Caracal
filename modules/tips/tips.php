<?php

/**
 * Tips Module
 *
 * Module that provides simple, single-field, data structure for
 * showing tips.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;


class tips extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);

		// register backend
		if (ModuleHandler::is_loaded('backend')) {
			$backend = backend::get_instance();

			$tips_menu = new backend_MenuItem(
					$this->get_language_constant('menu_tips'),
					URL::from_file_path($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);

			$tips_menu->addChild('', new backend_MenuItem(
								$this->get_language_constant('menu_tips_manage'),
								URL::from_file_path($this->path.'images/manage.svg'),

								window_Open( // on click open window
											'tips',
											500,
											$this->get_language_constant('title_tips_manage'),
											true, true,
											backend_UrlMake($this->name, 'tips')
										),
								$level=5
							));

			$backend->addMenu($this->name, $tips_menu);
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
				case 'show':
					$this->tag_Tip($params, $children);
					break;

				case 'show_list':
					$this->tag_TipList($params, $children);
					break;

				case 'json_tip':
					$this->json_Tip();
					break;

				case 'json_tip_list':
					$this->json_TipList();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'tips':
					$this->showTips();
					break;

				case 'tips_new':
					$this->addTip();
					break;

				case 'tips_change':
					$this->changeTip();
					break;

				case 'tips_save':
					$this->saveTip();
					break;

				case 'tips_delete':
					$this->deleteTip();
					break;

				case 'tips_delete_commit':
					$this->deleteTip_Commit();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function on_init() {
		global $db;

		$sql = Query::load_file('tips.sql', $this);
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function on_disable() {
		global $db;

		$tables = array('tips');
		$db->drop_tables($tables);
	}

	/**
	 * Show tips management form
	 */
	private function showTips() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->get_language_constant('new'),
										'tips_new', 400,
										$this->get_language_constant('title_tips_new'),
										true, false,
										$this->name,
										'tips_new'
									),
					);

		$template->register_tag_handler('_tip_list', $this, 'tag_TipList');
		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Print form for adding new tip to the system
	 */
	private function addTip() {
		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'tips_save'),
					'cancel_action'	=> window_Close('tips_new')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Change tip form
	 */
	private function changeTip() {
		$id = fix_id($_REQUEST['id']);
		$manager = TipManager::get_instance();

		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('change.xml', $this->path.'templates/');
			$template->set_mapped_module($this->name);

			$params = array(
						'id'			=> $item->id,
						'content'		=> unfix_chars($item->content),
						'visible'		=> $item->visible,
						'form_action'	=> backend_UrlMake($this->name, 'tips_save'),
						'cancel_action'	=> window_Close('tips_change')
					);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Save changed or new data
	 */
	private function saveTip() {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = TipManager::get_instance();
		$data = array(
					'content'	=> $this->get_multilanguage_field('content'),
					'visible'	=> fix_id($_REQUEST['visible'])
				);

		if (is_null($id)) {
			$window = 'tips_new';
			$manager->insert_item($data);
		} else {
			$window = 'tips_change';
			$manager->update_items($data,	array('id' => $id));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_tip_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('tips'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Show confirmation dialog before removing tip
	 */
	private function deleteTip() {
		global $language;

		$id = fix_id($_REQUEST['id']);
		$manager = TipManager::get_instance();

		$item = $manager->get_single_item(array('content'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'		=> $this->get_language_constant("message_tip_delete"),
					'name'			=> $item->content[$language],
					'yes_text'		=> $this->get_language_constant("delete"),
					'no_text'		=> $this->get_language_constant("cancel"),
					'yes_action'	=> window_LoadContent(
											'tips_delete',
											URL::make_query(
												'backend_module',
												'transfer_control',
												array('module', $this->name),
												array('backend_action', 'tips_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('tips_delete')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Perform tip removal
	 */
	private function deleteTip_Commit() {
		$id = fix_id($_REQUEST['id']);
		$manager = TipManager::get_instance();

		$manager->delete_items(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant("message_tip_deleted"),
					'button'	=> $this->get_language_constant("close"),
					'action'	=> window_Close('tips_delete').";".window_ReloadContent('tips')
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Tip tag handler
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Tip($tag_params, $children) {
		$manager = TipManager::get_instance();
		$order_by = array();
		$conditions = array();

		if (isset($tag_params['id'])) {
			$conditions['id'] = fix_id($tag_params['id']);

		} else if (isset($tag_params['random']) && $tag_params['random']) {
			$order_by[] = 'RAND()';

		} else {
			$order_by[] = 'id';
		}

		$item = $manager->get_single_item($manager->get_field_names(), $conditions, $order_by, false);

		$template = $this->load_template($tag_params, 'tip.xml');
		$template->set_template_params_from_array($children);

		if (is_object($item)) {
			$params = array(
						'id'		=> $item->id,
						'content'	=> $item->content,
						'visible'	=> $item->visible,
					);

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}

	/**
	 * Tag handler for tip list
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_TipList($tag_params, $children) {
		$manager = TipManager::get_instance();
		$conditions = array();
		$limit = null;
		$order_by = array('id');
		$order_asc = true;

		if (isset($tag_params['only_visible']) && $tag_params['only_visible'] == 1)
			$conditions['visible'] = 1;

		if (isset($tag_params['order_by']))
			$order_by = explode(',', fix_chars($tag_params['order_by']));

		if (isset($tag_params['order_asc']))
			$order_asc = $tag_params['order_asc'] == '1' || $tag_params['order_asc'] == 'yes';

		if (isset($tag_params['limit']))
			$limit = fix_id($tag_params['limit']);

		$template = $this->load_template($tag_params, 'list_item.xml');
		$template->set_template_params_from_array($children);
		$template->set_mapped_module($this->name);

		// get items
		$items = $manager->get_items($manager->get_field_names(), $conditions, $order_by, $order_asc, $limit);

		if (count($items) > 0)
			foreach($items as $item) {
				$params = array(
							'id'			=> $item->id,
							'content'		=> $item->content,
							'visible'		=> $item->visible,
							'item_change'	=> URL::make_hyperlink(
													$this->get_language_constant('change'),
													window_Open(
														'tips_change', 		// window id
														400,				// width
														$this->get_language_constant('title_tips_change'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'tips_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> URL::make_hyperlink(
													$this->get_language_constant('delete'),
													window_Open(
														'tips_delete', 	// window id
														400,				// width
														$this->get_language_constant('title_tips_delete'), // title
														false, false,
														URL::make_query(
															'backend_module',
															'transfer_control',
															array('module', $this->name),
															array('backend_action', 'tips_delete'),
															array('id', $item->id)
														)
													)
												),
						);

				$template->restore_xml();
				$template->set_local_params($params);
				$template->parse();
			}
	}

	/**
	 * Generate JSON object for specified tip
	 */
	private function json_Tip() {
		global $language;

		$conditions = array();
		$order_by = isset($_REQUEST['random']) && $_REQUEST['random'] == 'yes' ? 'RAND()' : 'id';
		$order_asc = isset($_REQUEST['order_asc']) && $_REQUEST['order_asc'] == 'yes';
		$all_languages = isset($_REQUEST['all_languages']) && $_REQUEST['all_languages'] == 'yes';

		if (isset($_REQUEST['id']))
			$conditions['id'] = fix_id(explode(',', $_REQUEST['id']));

		if (isset($_REQUEST['only_visible']) && $_REQUEST['only_visible'] == 'yes')
			$conditions['visible'] = 1;

		$manager = TipManager::get_instance();

		$item = $manager->get_single_item(
								$manager->get_field_names(),
								$conditions,
								array($order_by),
								$order_asc
							);

		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'item'			=> array()
				);

		if (is_object($item)) {
			$result['item'] = array(
							'id'		=> $item->id,
							'content'	=> $all_languages ? $item->content : $item->content[$language],
							'visible'	=> $item->visible
						);
		}

		print json_encode($result);
	}

	/**
	 * Return JSON list of tips.
	 */
	public function json_TipList() {
		global $language;

		$conditions = array();
		$limit = null;
		$order_by = isset($_REQUEST['random']) && $_REQUEST['random'] == 'yes' ? 'RAND()' : 'id';
		$order_asc = isset($_REQUEST['order_asc']) && $_REQUEST['order_asc'] == 'yes';
		$all_languages = isset($_REQUEST['all_languages']) && $_REQUEST['all_languages'] == 'yes';

		if (isset($_REQUEST['id']))
			$conditions['id'] = fix_id(explode(',', $_REQUEST['id']));

		if (isset($_REQUEST['only_visible']) && $_REQUEST['only_visible'] == 'yes')
			$conditions['visible'] = 1;

		if (isset($_REQUEST['limit']))
			$limit = fix_id($_REQUEST['limit']);

		$manager = TipManager::get_instance();

		$items = $manager->get_items(
								$manager->get_field_names(),
								$conditions,
								array($order_by),
								$order_asc,
								$limit
							);

		$result = array(
					'error'			=> false,
					'error_message'	=> '',
					'items'			=> array()
				);

		if (count($items) > 0)
			foreach ($items as $item)
				$result['items'][] = array(
								'id'		=> $item->id,
								'content'	=> $all_languages ? $item->content : $item->content[$language],
								'visible'	=> $item->visible
							);

		print json_encode($result);
	}
}


class TipManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('tips');

		$this->add_property('id', 'int');
		$this->add_property('content', 'ml_text');
		$this->add_property('visible', 'boolean');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}
