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

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// connect to events
		Events::connect('head-tag', 'before-print', 'add_description_tag', $this);

		// register backend
		if (ModuleHandler::is_loaded('backend') && $section == 'backend') {
			$backend = backend::get_instance();

			$menu_item = new backend_MenuItem(
					$this->get_language_constant('menu_page_descriptions'),
					URL::from_file_path($this->path.'images/icon.svg'),
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
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function on_init() {
		global $db;

		$list = Language::get_languages(false);
		$sql = "
			CREATE TABLE `page_descriptions` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`url` varchar(200) NOT NULL,
			";

		foreach($list as $language)
			$sql .= "`content_{$language}` VARCHAR(160) NOT NULL DEFAULT '',";

		$sql .= "
				PRIMARY KEY (`id`),
				KEY `index_by_url` (`url`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function on_disable() {
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
					'content'       => $item->content,
					'form_action'   => backend_UrlMake($this->name, 'save'),
					'cancel_action' => window_Close('page_descriptions_change_'.$id)
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
		$content = $this->get_multilanguage_field('content');
		$manager = Manager::get_instance();

		// update data
		$manager->update_items(array('content' => $content), array('id' => $id));

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
	 * Handle head tag event and add page description to the page if
	 * it's not already added.
	 */
	public function add_description_tag() {
		global $language;

		// don't handle backend links
		if (isset($_REQUEST['section']))
			return;

		$value = '';
		$head_tag = head_tag::get_instance();
		$manager = Manager::get_instance();

		// get query string
		$query_string = URL::get_query_string();

		// get page description
		$item = $manager->get_single_item($manager->get_field_names(), array('url' => $query_string));

		if (!is_object($item))
			$manager->insert_item(array('url' => $query_string)); else
			$value = $item->content[$language];

		// add description to head tag
		$head_tag->addTag('meta',
					array(
						'name'		=> 'description',
						'content'	=> $value
					));
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
