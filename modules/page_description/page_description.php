<?php

/**
 * Page Description Module
 *
 * This module provides simple and unified way to change and display
 * page descriptions.
 *
 * Author: Mladen Mijatov
 */
use Core\Events;
use Core\Module;

require_once('units/manager.php');

use Modules\PageDescription\Manager as Manager;


class page_description extends Module {
	private static $_instance;
	private $skip_page = false;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// connect to events
		Events::connect('head-tag', 'before-title-print', 'set_title_and_description', $this);

		// register backend
		if (ModuleHandler::is_loaded('backend') && $section == 'backend') {
			$backend = backend::get_instance();

			$menu_item = new backend_MenuItem(
					$this->get_language_constant('menu_page_descriptions'),
					$this->path.'images/icon.svg',
					window_Open(
							'page_descriptions',
							600,
							$this->get_language_constant('title_manage'),
							true, true,
							backend_UrlMake($this->name, 'show')
						),
					$level=7
				);

			$backend->addMenu($this->name, $menu_item);
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
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'show':
					$this->show_page_descriptions();
					break;

				case 'change':
					$this->change_page_description();
					break;

				case 'save':
					$this->save_page_description();
					break;

				default:
					break;
			}

		if (isset($params['action']))
			switch ($params['action']) {
				case 'set_page_skip':
					$this->skip_page = true;
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function initialize() {
		global $db;

		$sql = Query::load_file('descriptions.sql', $this);
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function cleanup() {
		global $db;

		$tables = array('page_descriptions');
		$db->drop_tables($tables);
	}

	/**
	 * Show list of page descriptions for management.
	 */
	private function show_page_descriptions() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:list', $this, 'tag_DescriptionList');

		$template->restore_xml();
		$template->parse();
	}

	/**
	 * Show form for changing description.
	 */
	private function change_page_description() {
		$id = fix_id($_REQUEST['id']);
		$manager = Manager::get_instance();

		// get item from the database
		$item = $manager->get_single_item($manager->get_field_names(), array('id' => $id));

		if (!is_object($item))
			return;

		// load template
		$template = new TemplateHandler('change.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		// prepare parameters
		$params = array(
					'id'            => $item->id,
					'url'           => $item->url,
					'title'         => $item->title,
					'content'       => $item->content,
					'form_action'   => backend_UrlMake($this->name, 'save'),
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Save page description.
	 */
	private function save_page_description() {
		$id = fix_id($_REQUEST['id']);
		$manager = Manager::get_instance();

		// update data
		$data = array(
				'title'   => $this->get_multilanguage_field('title'),
				'content' => $this->get_multilanguage_field('content')
			);
		$manager->update_items($data, array('id' => $id));

		// show message
		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);

		$params = array(
					'message'	=> $this->get_language_constant('message_saved'),
					'button'	=> $this->get_language_constant('close'),
					'action'	=> window_Close('page_descriptions_change_'.$id)
				);

		$template->restore_xml();
		$template->set_local_params($params);
		$template->parse();
	}

	/**
	 * Handle head tag event and set page title and add page description to the
	 * page if it's not already added.
	 */
	public function set_title_and_description() {
		global $language;

		$result = array('', -1);  // ignore title with negative priority

		// skip page if requested
		if ($this->skip_page)
			return $result;

		// don't handle backend links
		if (isset($_REQUEST['section']))
			return $result;

		// get request path
		$request_path = URL::get_request_path();

		// get page description
		$manager = Manager::get_instance();
		$item = $manager->get_single_item($manager->get_field_names(), array('url' => $request_path));

		if (is_object($item)) {
			// add description to head tag
			$head_tag = head_tag::get_instance();
			$head_tag->addTag('meta',
						array(
							'name'		=> 'description',
							'content'	=> $item->content[$language]
						));

			// store title in return value
			if (!empty($item->title[$language]))
				$result = array($item->title[$language], 10);

		} else {
			// no entry was found, insert and ignore
			$manager->insert_item(array('url' => $request_path));
		}

		return $result;
	}

	/**
	 * Render function for page description tag.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_DescriptionList($tag_params, $children) {
		global $language;

		$manager = Manager::get_instance();
		$conditions = array();

		// get page descriptions from database
		$items = $manager->get_items($manager->get_field_names(), $conditions);

		if (count($items) == 0)
			return;

		// load template
		$template = $this->load_template($tag_params, 'list_item.xml');
		$template->set_template_params_from_array($children);

		// parse template
		foreach ($items as $item) {
			$params = array(
				'id'          => $item->id,
				'url'         => $item->url,
				'title'       => $item->title,
				'content'     => $item->content,
				'filled'      => empty($item->content[$language]) ? CHAR_UNCHECKED : CHAR_CHECKED,
				'item_change' => URL::make_hyperlink(
									$this->get_language_constant('change'),
									window_Open(
										'page_descriptions_change_'.$item->id, 	// window id
										350,		// width
										$this->get_language_constant('title_change'), // title
										false, false,
										URL::make_query(
											'backend_module',
											'transfer_control',
											array('module', $this->name),
											array('backend_action', 'change'),
											array('id', $item->id)
										)
									)
								),
				'item_open'   => URL::make_hyperlink(
									$this->get_language_constant('open'),
									URL::get_base().$item->url, null, null, '_blank'
								));

			$template->restore_xml();
			$template->set_local_params($params);
			$template->parse();
		}
	}
}

?>
