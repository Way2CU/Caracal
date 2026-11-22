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
		Events::connect('backend', 'add-menu-items', 'add_menu_items', $this);
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
	 * Prepare data for export.
	 *
	 * @param array $options
	 * @param object $export_file
	 * @return array
	 */
	public function export_data(&$options, &$export_file) {
		return $this->export_tables($this->get_export_managers());
	}

	/**
	 * Import provided data to system. Existing data is replaced.
	 *
	 * @param array $data
	 * @param array $options
	 * @param object $export_file
	 */
	public function import_data(&$data, &$options, &$export_file) {
		$this->import_tables($data, $this->get_export_managers());
	}

	/**
	 * Get list of managers taking part in export and import.
	 *
	 * @return array
	 */
	private function get_export_managers() {
		return array(
				'descriptions' => Manager::get_instance()
			);
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
	 * Add items to backend menu.
	 */
	public function add_menu_items() {
		$backend = backend::get_instance();

		$menu_item = new backend_MenuItem(
				$this->get_language_constant('menu_page_descriptions'),
				$this->path.'images/icon.svg',
				window_Open(
						'page_descriptions',
						800,
						$this->get_language_constant('title_manage'),
						true, true,
						backend_UrlMake($this->name, 'show')
					),
				$level=7
			);

		$backend->addMenu($this->name, $menu_item);
	}

	/**
	 * Show list of page descriptions for management.
	 */
	private function show_page_descriptions() {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->set_mapped_module($this->name);
		$template->register_tag_handler('cms:list', $this, 'tag_DescriptionList');

		$params = array(
				'form_action' => backend_UrlMake($this->name, 'show')
			);

		$template->set_local_params($params);
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
	 * page if it's not already added. Only pages matched by section handler
	 * are handled, error pages never get entries. Titles provided by modules
	 * take precedence as they know better what their pages are called.
	 */
	public function set_title_and_description() {
		global $language;

		$result = array('', -1);  // ignore title with negative priority
		$is_backend = isset($_REQUEST['section']);
		$is_matched = !is_null(SectionHandler::get_matched_file());

		if (!$this->skip_page && !$is_backend && $is_matched) {
			$url = self::normalize_url(URL::get_request_path());
			$head_tag = head_tag::get_instance();

			// get page description
			$manager = Manager::get_instance();
			$item = $manager->get_single_item($manager->get_field_names(), array('url' => $url));

			if (is_object($item)) {
				// add description to head tag
				if (!empty($item->content[$language]))
					$head_tag->add_tag('meta',
								array(
									'name'		=> 'description',
									'content'	=> $item->content[$language]
								));

				// store title in return value
				if (!empty($item->title[$language]) && !$head_tag->has_title_parts())
					$result = array($item->title[$language], 10);

			} else {
				// no entry was found, insert and ignore
				$manager->insert_item(array('url' => escape_chars($url)));
			}
		}

		return $result;
	}

	/**
	 * Reduce request path to form shared by all variants of the same page.
	 * Language prefix and trailing slash are removed so `/he/about/` and
	 * `/about` point to the same entry.
	 *
	 * @param string $path
	 * @return string
	 */
	public static function normalize_url($path) {
		global $available_languages;

		$parts = explode('/', trim($path, '/'));

		// drop language prefix
		if (count($parts) > 0 && in_array($parts[0], $available_languages))
			array_shift($parts);

		return '/'.implode('/', $parts);
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

		// gather conditions
		if (isset($_REQUEST['query']))
			$conditions['url'] = array(
					'operator' => 'LIKE',
					'value'    => '%'.escape_chars($_REQUEST['query']).'%'
				);

		$only_empty = $this->get_boolean_field('only_empty');

		// get page descriptions from database
		$items = $manager->get_items($manager->get_field_names(), $conditions, array('id'), false, 1000);

		if (count($items) == 0)
			return;

		// load template
		$template = $this->load_template($tag_params, 'list_item.xml');
		$template->set_template_params_from_array($children);

		// parse template
		foreach ($items as $item) {
			if ($only_empty && !empty($item->title[$language]) && !empty($item->content[$language]))
				continue;

			$params = array(
				'id'             => $item->id,
				'url'            => $item->url,
				'title'          => $item->title,
				'content'        => $item->content,
				'filled_title'   => empty($item->title[$language]) ? CHAR_UNCHECKED : CHAR_CHECKED,
				'filled_content' => empty($item->content[$language]) ? CHAR_UNCHECKED : CHAR_CHECKED,
				'item_change'    => URL::make_hyperlink(
									$this->get_language_constant('change'),
									window_Open(
										'page_descriptions_change_'.$item->id, 	// window id
										420,		// width
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
				'item_open'      => URL::make_hyperlink(
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
